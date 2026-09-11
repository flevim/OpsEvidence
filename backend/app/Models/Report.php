<?php

namespace App\Models;

use App\Domain\Enums\ReportStatus;
use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'client_id',
        'period_start',
        'period_end',
        'status',
        'health_score',
        'summary',
        'metrics',
        'snapshot',
        'sent_at',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'health_score' => 'integer',
            'summary' => 'array',
            'metrics' => 'array',
            'snapshot' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function periodLabel(): string
    {
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return ($months[(int) $this->period_start->format('n')] ?? '').' '.$this->period_start->format('Y');
    }

    public function isSent(): bool
    {
        return $this->status === ReportStatus::Sent;
    }
}
