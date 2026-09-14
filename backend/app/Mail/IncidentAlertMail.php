<?php

namespace App\Mail;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso inmediato al equipo del MSP cuando se abre un incidente.
 *
 * El informe mensual demuestra lo que pasó; esta alerta evita que pase. Sin
 * ella, un contenedor con 25.000 reinicios o un disco al 95 % esperan a que
 * alguien entre al panel por casualidad.
 *
 * El destinatario es el equipo que administra, no el cliente final: la alerta
 * es una herramienta de trabajo, no un entregable.
 */
class IncidentAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Incident $incident) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[%s] %s — %s',
                mb_strtoupper($this->incident->severity->label()),
                $this->incident->client->name,
                $this->incident->title,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incident-alert',
            with: [
                'incident' => $this->incident,
                'client' => $this->incident->client,
                'asset' => $this->incident->asset,
                'recommendation' => $this->incident->metadata['recommendation'] ?? null,
                'panelUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/'),
            ],
        );
    }
}
