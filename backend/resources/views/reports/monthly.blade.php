<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informe de infraestructura · {{ $client->name }} · {{ $report->periodLabel() }}</title>
    <style>
        :root {
            --ink: #16181d;
            --muted: #5b6472;
            --line: #e3e6ea;
            --bg: #ffffff;
            --soft: #f6f7f9;
            --ok: #1a7f4b;
            --warn: #9a6400;
            --bad: #b3261e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.55;
        }
        .sheet { max-width: 880px; margin: 0 auto; padding: 48px 32px 80px; }
        header.report { border-bottom: 2px solid var(--ink); padding-bottom: 20px; margin-bottom: 32px; }
        .eyebrow { text-transform: uppercase; letter-spacing: .12em; font-size: 11px; color: var(--muted); margin: 0 0 8px; }
        h1 { font-size: 26px; margin: 0 0 6px; letter-spacing: -.01em; }
        h2 { font-size: 17px; margin: 40px 0 14px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
        h3 { font-size: 14px; margin: 24px 0 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
        .meta { color: var(--muted); font-size: 13px; display: flex; flex-wrap: wrap; gap: 18px; margin-top: 10px; }
        .score-card { display: flex; align-items: center; gap: 24px; background: var(--soft); border: 1px solid var(--line); border-radius: 10px; padding: 22px 24px; }
        .score-value { font-size: 46px; font-weight: 650; line-height: 1; letter-spacing: -.02em; }
        .score-meta { color: var(--muted); font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin: 20px 0 0; }
        .card { border: 1px solid var(--line); border-radius: 10px; padding: 16px 18px; }
        .card .label { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 8px; }
        .card .value { font-size: 24px; font-weight: 620; line-height: 1.15; }
        .card .detail { font-size: 13px; color: var(--muted); margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 600; }
        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 999px; border: 1px solid currentColor; white-space: nowrap; }
        .t-success { color: var(--ok); }
        .t-warning { color: var(--warn); }
        .t-error { color: var(--bad); }
        .t-neutral { color: var(--muted); }
        .note { background: var(--soft); border-left: 3px solid var(--muted); padding: 12px 16px; font-size: 14px; color: var(--muted); border-radius: 0 6px 6px 0; }
        .banner-warning { background: #fff8e6; border-left: 3px solid var(--warn); padding: 12px 16px; border-radius: 0 6px 6px 0; }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }
        footer.report { margin-top: 56px; padding-top: 18px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); }
        @media print {
            .sheet { padding: 0 0 24px; max-width: none; }
            h2 { page-break-after: avoid; }
            .card, .score-card { break-inside: avoid; }
            body { font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="sheet">

    <header class="report">
        <p class="eyebrow">Informe de infraestructura</p>
        <h1>{{ $client->name }}</h1>
        <div class="meta">
            <span><strong>Periodo:</strong> {{ $report->periodLabel() }}
                ({{ $report->period_start->format('d/m/Y') }} – {{ $report->period_end->format('d/m/Y') }})</span>
            <span><strong>Preparado por:</strong> {{ $account->name }}</span>
            <span><strong>Emitido:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</span>
        </div>
    </header>

    @php
        $metrics = $metrics ?? [];
        $snapshot = $snapshot ?? [];
        $health = $snapshot['health'] ?? [];
        $withoutData = (int) ($metrics['checks']['without_data'] ?? 0);
        $withData = (int) ($metrics['checks']['with_data'] ?? 0);
    @endphp

    @if ($withData === 0)
        <div class="banner-warning">
            <strong>Este informe no contiene datos de monitorización.</strong>
            No se recibió evidencia de ningún activo en el periodo. Las cifras se muestran como
            «Sin datos» y no como cero: la ausencia de información no es lo mismo que un buen resultado.
        </div>
    @elseif ($withoutData > 0)
        <div class="banner-warning">
            <strong>{{ $withoutData }} comprobación(es) no reportaron datos en el periodo.</strong>
            Se excluyen del índice de salud para no distorsionar el resultado.
        </div>
    @endif

    <h2>Resumen</h2>

    <div class="score-card">
        <div>
            <div class="score-value">
                {{ $report->health_score !== null ? $report->health_score : '—' }}<span class="muted" style="font-size:20px;">/100</span>
            </div>
            <div class="score-meta">Índice de salud de la infraestructura</div>
        </div>
        <div>
            <div class="pill t-{{ $summary[0]['tone'] ?? 'neutral' }}">{{ $health['band_label'] ?? 'Sin datos suficientes' }}</div>
            <div class="score-meta" style="margin-top:8px;">
                Media ponderada de los componentes con datos.
                @if (!empty($health['excluded']))
                    No se pudo evaluar: {{ implode(', ', $health['excluded']) }}.
                @endif
            </div>
        </div>
    </div>

    <div class="grid">
        @foreach ($summary as $item)
            @continue ($item['key'] === 'health_score')
            <div class="card">
                <div class="label">{{ $item['label'] }}</div>
                <div class="value t-{{ $item['tone'] }}">{{ $item['display'] }}</div>
                <div class="detail">{{ $item['detail'] }}</div>
            </div>
        @endforeach
    </div>

    @if (!empty($health['components']))
        <h3>Desglose del índice</h3>
        <table>
            <thead>
                <tr><th>Componente</th><th>Puntuación</th><th>Detalle</th></tr>
            </thead>
            <tbody>
            @foreach ($health['components'] as $key => $component)
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
        <p class="small muted">
            Fórmula v{{ $health['version'] ?? '1.0' }}. No constituye una garantía ni un acuerdo de nivel de servicio.
        </p>
    @endif

    <h2>Infraestructura</h2>
    <table>
        <thead>
            <tr><th>Activo</th><th>Tipo</th><th>Estado</th><th>Última observación</th></tr>
        </thead>
        <tbody>
        @forelse (($snapshot['assets'] ?? []) as $asset)
            <tr>
                <td><strong>{{ $asset['name'] }}</strong><div class="small muted">{{ $asset['title'] ?? '' }}</div></td>
                <td class="muted">{{ $asset['type_label'] }}</td>
                <td>
                    @if ($asset['status'])
                        <span class="pill t-{{ $asset['status'] === 'HEALTHY' ? 'success' : ($asset['status'] === 'CRITICAL' ? 'error' : ($asset['status'] === 'WARNING' ? 'warning' : 'neutral')) }}">
                            {{ $asset['status_label'] }}
                        </span>
                    @else
                        <span class="pill t-neutral">Sin datos</span>
                    @endif
                </td>
                <td class="muted small">
                    {{ $asset['collected_at'] ? \Carbon\CarbonImmutable::parse($asset['collected_at'])->format('d/m/Y H:i') : '—' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Sin activos registrados.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Disponibilidad</h2>
    @if (($metrics['availability']['percentage'] ?? null) !== null)
        <p>
            Se realizaron <strong>{{ number_format((int) $metrics['availability']['samples'], 0, ',', '.') }}</strong>
            comprobaciones sobre los servicios publicados, con una disponibilidad observada de
            <strong>{{ number_format((float) $metrics['availability']['percentage'], 2, ',', '.') }} %</strong>.
            @if (($metrics['availability']['down'] ?? 0) > 0)
                Se registraron {{ $metrics['availability']['down'] }} respuestas no disponibles.
            @endif
        </p>
    @else
        <div class="note">No hay comprobaciones HTTP configuradas para este cliente.</div>
    @endif

    <h2>Backups</h2>
    @if (($metrics['backups']['total'] ?? 0) > 0)
        <p>
            Se registraron <strong>{{ $metrics['backups']['total'] }}</strong> ejecuciones de backup:
            <strong>{{ $metrics['backups']['ok'] }}</strong> correctas,
            {{ $metrics['backups']['warning'] }} con advertencias y
            {{ $metrics['backups']['failed'] }} fallidas.
        </p>
        @if (($metrics['backups']['last_at'] ?? null) !== null)
            <p class="muted small">
                Último backup registrado:
                {{ \Carbon\CarbonImmutable::parse($metrics['backups']['last_at'])->format('d/m/Y H:i') }}.
            </p>
        @endif
    @else
        <div class="note">No se registraron backups en el periodo. Si existen, aún no reportan a OpsEvidence.</div>
    @endif

    @if (($metrics['containers']['available'] ?? false))
        <h2>Contenedores</h2>
        <p>
            <strong>{{ $metrics['containers']['running'] }}</strong> de
            <strong>{{ $metrics['containers']['total'] }}</strong> contenedores en ejecución.
        </p>
    @endif

    @if (($metrics['deployments']['available'] ?? false))
        <h2>Despliegues</h2>
        <p>
            <strong>{{ $metrics['deployments']['total'] }}</strong> ejecuciones de despliegue en el periodo,
            {{ $metrics['deployments']['successful'] }} exitosas y
            {{ $metrics['deployments']['failed'] }} fallidas.
        </p>
    @endif

    <h2>Incidentes</h2>
    @php($opened = $snapshot['open_incidents'] ?? [])
    @php($resolved = $snapshot['resolved_incidents'] ?? [])
    @if (empty($opened) && empty($resolved))
        <p class="muted">No se detectaron incidentes en el periodo.</p>
    @else
        @if (!empty($opened))
            <h3>Riesgos abiertos al cierre del periodo</h3>
            <table>
                <thead><tr><th>Incidente</th><th>Severidad</th><th>Abierto</th><th>Acción recomendada</th></tr></thead>
                <tbody>
                @foreach ($opened as $incident)
                    <tr>
                        <td><strong>{{ $incident['title'] }}</strong></td>
                        <td><span class="pill t-{{ $incident['severity'] === 'critical' ? 'error' : 'warning' }}">{{ $incident['severity_label'] }}</span></td>
                        <td class="muted small">{{ \Carbon\CarbonImmutable::parse($incident['opened_at'])->format('d/m/Y H:i') }}</td>
                        <td class="muted small">{{ $incident['recommendation'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if (!empty($resolved))
            <h3>Incidentes atendidos</h3>
            <table>
                <thead><tr><th>Incidente</th><th>Abierto</th><th>Resuelto</th><th>Nota</th></tr></thead>
                <tbody>
                @foreach ($resolved as $incident)
                    <tr>
                        <td>{{ $incident['title'] }}</td>
                        <td class="muted small">{{ \Carbon\CarbonImmutable::parse($incident['opened_at'])->format('d/m/Y') }}</td>
                        <td class="muted small">{{ \Carbon\CarbonImmutable::parse($incident['resolved_at'])->format('d/m/Y') }}</td>
                        <td class="muted small">{{ $incident['note'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h2>Trabajo realizado</h2>
    @php($activities = $snapshot['activities'] ?? [])
    @if (empty($activities))
        <div class="note">
            No se registraron actividades manuales en el periodo. Las tareas realizadas pueden
            registrarse en OpsEvidence para que queden reflejadas en el próximo informe.
        </div>
    @else
        <table>
            <thead><tr><th>Fecha</th><th>Actividad</th><th>Detalle</th></tr></thead>
            <tbody>
            @foreach ($activities as $activity)
                <tr>
                    <td class="muted small">{{ \Carbon\CarbonImmutable::parse($activity['performed_at'])->format('d/m/Y') }}</td>
                    <td><strong>{{ $activity['title'] }}</strong><div class="small muted">{{ $activity['type_label'] }}</div></td>
                    <td class="muted small">{{ $activity['description'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <footer class="report">
        <p>
            Informe generado automáticamente por OpsEvidence a partir de evidencia técnica recopilada
            entre el {{ $report->period_start->format('d/m/Y') }} y el {{ $report->period_end->format('d/m/Y') }}.
        </p>
        <p>
            El índice de salud es un indicador interno orientativo y no constituye una garantía de servicio.
            Los datos sin información se reportan explícitamente como «Sin datos».
        </p>
    </footer>
</div>
</body>
</html>
