<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\ReportStatus;
use App\Http\Controllers\Api\Concerns\HandlesListQuery;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Report;
use App\Services\Reporting\ReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    use HandlesListQuery;

    public function __construct(private readonly ReportBuilder $builder) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Report::class);

        $query = Report::query()
            ->with(['client:id,name'])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()));

        $query = $this->applySorting($query, $request, ['period_end', 'created_at', 'health_score', 'status'], 'period_end');

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Report::class);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $from = CarbonImmutable::parse($data['period_start'])->startOfDay();
        $to = CarbonImmutable::parse($data['period_end'])->endOfDay();

        $maxDays = (int) config('opsevidence.reporting.max_period_days');

        if ($from->diffInDays($to) > $maxDays) {
            throw ValidationException::withMessages([
                'period_end' => "El periodo no puede superar {$maxDays} días.",
            ]);
        }

        $client = Client::findOrFail($data['client_id']);
        $this->authorize('view', $client);

        $report = $this->builder->build($client, $from, $to, $request->user()->id);

        return response()->json($report->load('client:id,name'), 201);
    }

    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json($report->load('client:id,name'));
    }

    /**
     * Informe en HTML, listo para imprimir o enviar por correo.
     */
    public function html(Report $report): Response
    {
        $this->authorize('view', $report);

        return response($this->builder->renderHtml($report))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Marca el informe como enviado al cliente.
     *
     * Es la instrumentacion de la hipotesis de negocio: mide si el informe
     * realmente se entrega (ver docs/product.md, seccion 8).
     */
    public function markSent(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $report->forceFill([
            'status' => ReportStatus::Sent,
            'sent_at' => now(),
        ])->save();

        AuditLog::record(
            event: 'report.sent',
            subject: $report,
            changes: ['period_end' => $report->period_end->toDateString()],
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json($report->fresh()->load('client:id,name'));
    }
}
