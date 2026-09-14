<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $incident->title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#16181d;">

@php
    $tone = match ($incident->severity->value) {
        'critical' => '#b3261e',
        'warning' => '#9a6400',
        default => '#1f6feb',
    };
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border:1px solid #e3e6ea; border-radius:8px; padding:28px;">

                <tr>
                    <td>
                        <p style="margin:0 0 6px; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:{{ $tone }}; font-weight:bold;">
                            {{ $incident->severity->label() }}
                        </p>
                        <h1 style="margin:0 0 6px; font-size:19px;">{{ $incident->title }}</h1>
                        <p style="margin:0 0 20px; font-size:13px; color:#5b6472;">
                            {{ $client->name }}@if ($asset) · {{ $asset->name }}@endif
                            · detectado {{ $incident->opened_at->diffForHumans() }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="border-top:1px solid #e3e6ea; padding-top:20px;">
                        <p style="margin:0 0 16px; font-size:14px;">{{ $incident->description }}</p>

                        @if ($recommendation)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8f9; border-left:3px solid {{ $tone }}; margin-bottom:16px;">
                                <tr>
                                    <td style="padding:12px 14px; font-size:13px; color:#3f4a57;">
                                        <strong>Qué hacer:</strong> {{ $recommendation }}
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <p style="margin:0; font-size:12px; color:#8b94a1;">
                            Regla que lo detectó: <code>{{ $incident->rule_key->value }}</code>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:22px;">
                        <a href="{{ $panelUrl }}/issues"
                           style="display:inline-block; background-color:#2f6f4e; color:#ffffff; text-decoration:none; padding:10px 18px; border-radius:6px; font-size:14px;">
                            Ver en OpsEvidence
                        </a>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
