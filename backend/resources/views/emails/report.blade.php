<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de infraestructura — {{ $client->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#16181d;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border:1px solid #e3e6ea; border-radius:8px; padding:28px;">

                <tr>
                    <td>
                        <p style="margin:0 0 4px; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#5b6472;">
                            Informe de infraestructura
                        </p>
                        <h1 style="margin:0 0 4px; font-size:20px;">{{ $client->name }}</h1>
                        <p style="margin:0 0 20px; font-size:13px; color:#5b6472;">
                            Periodo: <strong>{{ $report->periodLabel() }}</strong>
                            ({{ $report->period_start->format('d/m/Y') }} – {{ $report->period_end->format('d/m/Y') }})
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="border-top:1px solid #e3e6ea; padding-top:20px;">
                        @php($saludo = $client->contact_name ? 'Hola '.$client->contact_name : 'Hola')
                        <p style="margin:0 0 16px; font-size:14px;">
                            {{ $saludo }}, adjuntamos el informe de infraestructura correspondiente al periodo
                            indicado. Te dejamos el resumen; el documento completo va en el PDF adjunto.
                        </p>

                        @if ($note)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8f9; border-left:3px solid #5b6472; margin-bottom:16px;">
                                <tr><td style="padding:12px 14px; font-size:13px; color:#3f4a57;">{{ $note }}</td></tr>
                            </table>
                        @endif

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                            @foreach ($summary as $item)
                                <tr>
                                    <td style="padding:9px 0; border-bottom:1px solid #eef0f3; font-size:13px; color:#5b6472; width:52%;">
                                        {{ $item['label'] }}
                                    </td>
                                    <td style="padding:9px 0; border-bottom:1px solid #eef0f3; font-size:14px; font-weight:bold; text-align:right;
                                        color:{{ ($item['tone'] ?? 'neutral') === 'success' ? '#1a7f4b' : ((($item['tone'] ?? '') === 'error') ? '#b3261e' : ((($item['tone'] ?? '') === 'warning') ? '#9a6400' : '#16181d')) }};">
                                        {{ $item['display'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                @php($open = $snapshot['open_incidents'] ?? [])
                @if (! empty($open))
                    <tr>
                        <td style="padding-top:22px;">
                            <p style="margin:0 0 8px; font-size:13px; font-weight:bold;">Puntos que requieren atención</p>
                            <ul style="margin:0; padding-left:18px; font-size:13px; color:#3f4a57;">
                                @foreach (array_slice($open, 0, 5) as $incident)
                                    <li style="margin-bottom:5px;">
                                        {{ $incident['title'] }}
                                        @if (! empty($incident['recommendation']))
                                            <br><span style="color:#5b6472; font-size:12px;">{{ $incident['recommendation'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding-top:24px;">
                        <p style="margin:0; font-size:13px; color:#5b6472;">
                            Cualquier consulta sobre el informe, respóndenos a este correo.
                        </p>
                        <p style="margin:14px 0 0; font-size:13px;">
                            {{ $account->name }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:24px; border-top:1px solid #e3e6ea;">
                        <p style="margin:0; font-size:11px; color:#8b94a1;">
                            Informe generado automáticamente por OpsEvidence a partir de evidencia técnica del periodo.
                            El índice de salud es un indicador orientativo y no constituye una garantía de servicio.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
