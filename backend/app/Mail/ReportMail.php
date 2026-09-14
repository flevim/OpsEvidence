<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo con el informe mensual adjunto en PDF.
 *
 * El texto esta escrito para el cliente final, no para un tecnico: abre con lo
 * importante y evita jerga (ver docs/product.md, seccion 24).
 */
class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Report $report,
        public readonly string $pdf,
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Informe de infraestructura — %s — %s',
                $this->report->client->name,
                $this->report->periodLabel(),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report',
            with: [
                'report' => $this->report,
                'client' => $this->report->client,
                'account' => $this->report->account,
                'summary' => $this->report->summary ?? [],
                'snapshot' => $this->report->snapshot ?? [],
                'note' => $this->note,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->pdfFilename())
                ->withMime('application/pdf'),
        ];
    }

    private function pdfFilename(): string
    {
        return sprintf('informe-%s.pdf', $this->report->period_start->format('Y-m'));
    }
}
