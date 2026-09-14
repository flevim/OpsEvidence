<?php

namespace App\Jobs;

use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\UserRole;
use App\Mail\IncidentAlertMail;
use App\Models\Account;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa al equipo que administra cuando se abre un incidente.
 *
 * El destinatario es el equipo del MSP, no el cliente final: la alerta es una
 * herramienta de trabajo, el informe es el entregable.
 */
class SendIncidentAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly int $incidentId)
    {
        $this->onQueue('alerts');
    }

    public function handle(): void
    {
        $incident = Incident::withoutGlobalScopes()
            ->with(['client', 'asset'])
            ->find($this->incidentId);

        if ($incident === null) {
            return;
        }

        // Lo informativo no despierta a nadie.
        if ($incident->severity === IncidentSeverity::Info) {
            return;
        }

        $recipients = $this->recipients($incident);

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send(new IncidentAlertMail($incident));
    }

    /**
     * @return array<int, string>
     */
    private function recipients(Incident $incident): array
    {
        $account = Account::withoutGlobalScopes()->find($incident->account_id);

        // Dirección adicional configurable en la cuenta, útil para un buzón
        // compartido del equipo.
        $extra = data_get($account?->settings, 'alert_email');

        $team = User::withoutGlobalScopes()
            ->where('account_id', $incident->account_id)
            ->where('is_active', true)
            ->whereIn('role', [
                UserRole::Owner->value,
                UserRole::Admin->value,
                UserRole::Technician->value,
            ])
            ->pluck('email')
            ->all();

        if (is_string($extra) && $extra !== '') {
            $team[] = $extra;
        }

        return array_values(array_unique(array_filter($team)));
    }
}
