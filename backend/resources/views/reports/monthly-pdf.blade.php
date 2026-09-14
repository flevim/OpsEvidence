<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de infraestructura — {{ $client->name }} — {{ $report->periodLabel() }}</title>
    <style>
        @page { margin: 2cm 1.6cm; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            color: #16181d;
            line-height: 1.45;
        }
        h1 { font-size: 17pt; margin: 0 0 2pt; }
        h2 { font-size: 11pt; margin: 16pt 0 6pt; border-bottom: 1pt solid #c9ced6; padding-bottom: 3pt; }
        h3 { font-size: 9.5pt; margin: 10pt 0 4pt; color: #5b6472; }
        p { margin: 0 0 6pt; }
        .eyebrow { font-size: 7.5pt; letter-spacing: 1pt; color: #5b6472; margin: 0 0 4pt; }
        .muted { color: #5b6472; }
        .small { font-size: 8pt; }
        .header { border-bottom: 2pt solid #16181d; padding-bottom: 8pt; margin-bottom: 12pt; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: #5b6472; border-bottom: 1pt solid #c9ced6; padding: 5pt 4pt; }
        td { padding: 5pt 4pt; border-bottom: 0.5pt solid #e3e6ea; vertical-align: top; }
        .score-table td { border: 0; padding: 0; }
        .score-value { font-size: 30pt; font-weight: bold; }
        .score-box { background-color: #f2f4f6; border: 1pt solid #d7dbe0; padding: 10pt 12pt; }
        .card { width: 100%; border-collapse: separate; border-spacing: 6pt 0; }
        .card td { background-color: #f7f8f9; border: 1pt solid #e3e6ea; padding: 7pt 8pt; border-bottom: 1pt solid #e3e6ea; }
        .card .label { font-size: 7.5pt; color: #5b6472; }
        .card .value { font-size: 13pt; font-weight: bold; margin-top: 2pt; }
        .t-success { color: #1a7f4b; }
        .t-warning { color: #9a6400; }
        .t-error { color: #b3261e; }
        .t-neutral { color: #5b6472; }
        .banner { background-color: #fff6e0; border-left: 2pt solid #9a6400; padding: 7pt 9pt; margin-bottom: 10pt; font-size: 8.5pt; }
        .pill { font-size: 7.5pt; font-weight: bold; padding: 1pt 5pt; }
        .footer { margin-top: 18pt; border-top: 1pt solid #c9ced6; padding-top: 6pt; font-size: 7.5pt; color: #5b6472; }
    </style>
</head>
<body>

@php
    $health = $snapshot['health'] ?? [];
    $components = $health['components'] ?? [];
    $assets = $snapshot['assets'] ?? [];
    $openIncidents = $snapshot['open_incidents'] ?? [];
    $resolvedIncidents = $snapshot['resolved_incidents'] ?? [];
    $activities = $snapshot['activities'] ?? [];
    $withoutData = (int) ($metrics['checks']['without_data'] ?? 0);
    $withData = (int) ($metrics['checks']['with_data'] ?? 0);
    $tone = fn (?string $t) => 't-'.($t ?? 'neutral');
@endphp

<div class="header">
    <p class="eyebrow">INFORME DE INFRAESTRUCTURA</p>
    <h1>{{ $client->name }}</h1>
    <p class="small muted">
        Periodo: <strong>{{ $report->periodLabel() }}</strong>
        ({{ $report->period_start->format('d/m/Y') }} – {{ $report->period_end->format('d/m/Y') }})
        &nbsp;·&nbsp; Preparado por: {{ $account->name }}
        &nbsp;·&nbsp; Emitido: {{ $generatedAt->format('d/m/Y H:i') }}
    </p>
</div>

@if ($withData === 0)
    <div class="banner">
        <strong>Este informe no contiene datos de monitorización.</strong>
        No se recibió evidencia de ningún activo en el periodo. Las cifras se muestran como
        «Sin datos» y no como cero: la ausencia de información no es lo mismo que un buen resultado.
    </div>
@elseif ($withoutData > 0)
    <div class="banner">
        <strong>{{ $withoutData }} comprobación(es) no reportaron datos en el periodo</strong>
        y se excluyen del índice de salud para no distorsionar el resultado.
    </div>
@endif

<h2>Resumen</h2>

<table class="score-table">
    <tr>
        <td width="38%">
            <div class="score-box">
                <div class="score-value">{{ $report->health_score !== null ? $report->health_score : '—' }}</div>
                <div class="small muted">Índice de salud sobre 100</div>
            </div>
        </td>
        <td width="62%" style="padding-left: 12pt;">
            <p><strong>{{ $health['band_label'] ?? 'Sin datos suficientes' }}</strong></p>
            <p class="small muted">
                Media ponderada de los componentes con datos.
                @if (! empty($health['excluded']))
                    No evaluados por falta de información: {{ implode(', ', $health['excluded']) }}.
                @endif
            </p>
            <p class="small muted">No constituye una garantía ni un acuerdo de nivel de servicio.</p>
        </td>
    </tr>
</table>

<table class="card" style="margin-top: 10pt;">
    <tr>
        @foreach (collect($summary)->reject(fn ($item) => $item['key'] === 'health_score')->chunk(3)->first() ?? [] as $item)
            <td width="33%">
                <div class="label">{{ $item['label'] }}</div>
                <div class="value {{ $tone($item['tone'] ?? null) }}">{{ $item['display'] }}</div>
                <div class="small muted">{{ $item['detail'] }}</div>
            </td>
        @endforeach
    </tr>
    <tr>
        @foreach (collect($summary)->reject(fn ($item) => $item['key'] === 'health_score')->slice(3)->take(3) as $item)
            <td width="33%">
                <div class="label">{{ $item['label'] }}</div>
                <div class="value {{ $tone($item['tone'] ?? null) }}">{{ $item['display'] }}</div>
                <div class="small muted">{{ $item['detail'] }}</div>
            </td>
        @endforeach
    </tr>
</table>

@if (! empty($components))
    <h2>Desglose del índice</h2>
    <table>
        <thead>
            <tr><th width="30%">Componente</th><th width="15%">Puntuación</th><th>Detalle</th></tr>
        </thead>
        <tbody>
        @foreach ($components as $key => $component)
            <tr>
                <td>{{ $component['label'] ?? $key }}</td>
                <td>
                    @if ($component['score'] === null)
                        <span class="muted">Sin datos</span>
                    @else
                        {{ rtrim(rtrim(number_format((float) $component['score'], 1, ',', '.'), '0'), ',') }}/100
                    @endif
                </td>
                <td class="muted">{{ $component['detail'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="small muted">Fórmula v{{ $health['version'] ?? '1.0' }}.</p>
@endif

<h2>Infraestructura</h2>
<table>
    <thead>
        <tr><th width="30%">Activo</th><th width="18%">Tipo</th><th width="16%">Estado</th><th>Última observación</th></tr>
    </thead>
    <tbody>
    @forelse ($assets as $asset)
        <tr>
            <td><strong>{{ $asset['name'] }}</strong>
                @if (! empty($asset['title']))<br><span class="small muted">{{ $asset['title'] }}</span>@endif
            </td>
            <td class="muted">{{ $asset['type_label'] }}</td>
            <td>
                @if ($asset['status'])
                    <span class="pill {{ $asset['status'] === 'HEALTHY' ? 't-success' : ($asset['status'] === 'CRITICAL' ? 't-error' : ($asset['status'] === 'WARNING' ? 't-warning' : 't-neutral')) }}">
                        {{ $asset['status_label'] }}
                    </span>
                @else
                    <span class="pill t-neutral">Sin datos</span>
                @endif
            </td>
            <td class="small muted">
                {{ $asset['collected_at'] ? \Carbon\CarbonImmutable::parse($asset['collected_at'])->format('d/m/Y H:i') : '—' }}
            </td>
        </tr>
    @empty
        <tr><td colspan="4" class="muted">Sin activos registrados.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Disponibilidad y backups</h2>
@if (($metrics['availability']['percentage'] ?? null) !== null)
    <p>
        Se realizaron <strong>{{ number_format((int) $metrics['availability']['samples'], 0, ',', '.') }}</strong>
        comprobaciones sobre los servicios publicados, con una disponibilidad observada de
        <strong>{{ number_format((float) $metrics['availability']['percentage'], 2, ',', '.') }} %</strong>.
    </p>
@else
    <p class="muted">No hay comprobaciones HTTP configuradas para este cliente.</p>
@endif

@if (($metrics['backups']['total'] ?? 0) > 0)
    <p>
        Se registraron <strong>{{ $metrics['backups']['total'] }}</strong> ejecuciones de backup:
        <strong>{{ $metrics['backups']['ok'] }}</strong> correctas,
        {{ $metrics['backups']['warning'] }} con advertencias y
        {{ $metrics['backups']['failed'] }} fallidas.
    </p>
@else
    <p class="muted">No se registraron backups en el periodo.</p>
@endif

@if (($metrics['containers']['available'] ?? false))
    <p>
        Contenedores en ejecución: <strong>{{ $metrics['containers']['running'] }}</strong>
        de <strong>{{ $metrics['containers']['total'] }}</strong>.
    </p>
@endif

@if (($metrics['deployments']['available'] ?? false))
    <p>
        Despliegues: <strong>{{ $metrics['deployments']['total'] }}</strong> en el periodo,
        {{ $metrics['deployments']['successful'] }} exitosos y
        {{ $metrics['deployments']['failed'] }} fallidos.
    </p>
@endif

<h2>Incidentes</h2>
@if (empty($openIncidents) && empty($resolvedIncidents))
    <p class="muted">No se detectaron incidentes en el periodo.</p>
@else
    @if (! empty($openIncidents))
        <h3>Riesgos abiertos al cierre del periodo</h3>
        <table>
            <thead><tr><th width="34%">Incidente</th><th width="14%">Severidad</th><th width="16%">Abierto</th><th>Acción recomendada</th></tr></thead>
            <tbody>
            @foreach ($openIncidents as $incident)
                <tr>
                    <td><strong>{{ $incident['title'] }}</strong></td>
                    <td>{{ $incident['severity_label'] }}</td>
                    <td class="small muted">{{ \Carbon\CarbonImmutable::parse($incident['opened_at'])->format('d/m/Y H:i') }}</td>
                    <td class="small muted">{{ $incident['recommendation'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($resolvedIncidents))
        <h3>Incidentes atendidos</h3>
        <table>
            <thead><tr><th width="40%">Incidente</th><th width="18%">Abierto</th><th width="18%">Resuelto</th><th>Nota</th></tr></thead>
            <tbody>
            @foreach ($resolvedIncidents as $incident)
                <tr>
                    <td>{{ $incident['title'] }}</td>
                    <td class="small muted">{{ \Carbon\CarbonImmutable::parse($incident['opened_at'])->format('d/m/Y') }}</td>
                    <td class="small muted">{{ \Carbon\CarbonImmutable::parse($incident['resolved_at'])->format('d/m/Y') }}</td>
                    <td class="small muted">{{ $incident['note'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

<h2>Trabajo realizado</h2>
@if (empty($activities))
    <p class="muted">No se registraron actividades manuales en el periodo.</p>
@else
    <table>
        <thead><tr><th width="14%">Fecha</th><th width="34%">Actividad</th><th>Detalle</th></tr></thead>
        <tbody>
        @foreach ($activities as $activity)
            <tr>
                <td class="small muted">{{ \Carbon\CarbonImmutable::parse($activity['performed_at'])->format('d/m/Y') }}</td>
                <td><strong>{{ $activity['title'] }}</strong><br><span class="small muted">{{ $activity['type_label'] }}</span></td>
                <td class="small muted">{{ $activity['description'] ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Informe generado automáticamente por OpsEvidence a partir de evidencia técnica recopilada entre el
    {{ $report->period_start->format('d/m/Y') }} y el {{ $report->period_end->format('d/m/Y') }}.
    El índice de salud es un indicador interno orientativo y no constituye una garantía de servicio.
    Los datos sin información se reportan explícitamente como «Sin datos».
</div>

</body>
</html>
