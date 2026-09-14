<?php

namespace App\Services\Reporting;

use App\Models\Report;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Convierte el informe a PDF con dompdf (PHP puro, sin navegador headless).
 *
 * Se usa una plantilla especifica para PDF: dompdf no interpreta grid,
 * flexbox ni variables CSS, asi que el documento imprimible se construye con
 * tablas y estilos basicos.
 */
class ReportPdfRenderer
{
    public function __construct(private readonly ReportBuilder $builder) {}

    public function render(Report $report): string
    {
        $options = new Options;
        // Sin recursos remotos: el informe solo lleva texto y tablas.
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setDefaultFont('DejaVu Sans');
        $options->setTempDir($this->tempDir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->builder->renderPdfHtml($report), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function filename(Report $report): string
    {
        return sprintf(
            'informe-%s-%s.pdf',
            str_replace(' ', '-', mb_strtolower($report->client->slug ?? 'cliente')),
            $report->period_start->format('Y-m'),
        );
    }

    private function tempDir(): string
    {
        $path = storage_path('app/dompdf');

        if (! is_dir($path)) {
            mkdir($path, 0o777, true);
        }

        return $path;
    }
}
