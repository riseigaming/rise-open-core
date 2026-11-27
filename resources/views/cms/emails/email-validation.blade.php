@php
    $name = trim((string) ($user->full_name ?? $user->username ?? ''));
    $brand = array_merge([
        'name' => 'Rise Open Core',
        'logo_url' => null,
        'accent' => '#d6ae4a',
        'button' => '#facc15',
    ], (array) ($brand ?? []));
    $logoUrl = trim((string) ($brand['logo_url'] ?? ''));
@endphp
<!doctype html>
<html lang="{{ $locale ?? 'tr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $copy['subject'] }}</title>
    <style>
        html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        table, td { border-collapse: collapse !important; }
        img { border: 0; display: block; line-height: 100%; outline: none; text-decoration: none; }
        a { text-decoration: none; }
        @media only screen and (max-width: 640px) {
            .email-shell { padding: 20px 10px !important; }
            .email-card { border-radius: 18px !important; }
            .email-header { padding: 28px 24px 20px !important; }
            .email-content { padding: 8px 24px 28px !important; }
            .email-title { font-size: 28px !important; line-height: 34px !important; }
            .email-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#050915;color:#f8fbff;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <div style="display:none;font-size:1px;color:#050915;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $copy['preheader'] }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#050915" style="width:100%;background-color:#050915;">
        <tr>
            <td class="email-shell" align="center" style="padding:40px 14px;">
                <table class="email-card" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#0b1530" style="width:100%;max-width:600px;background-color:#0b1530;border:1px solid #203b76;border-radius:24px;overflow:hidden;">
                    <tr>
                        <td height="4" bgcolor="{{ $brand['accent'] }}" style="height:4px;font-size:0;line-height:0;background-color:{{ $brand['accent'] }};">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="email-header" align="center" style="padding:34px 42px 24px;background-color:#0b1530;background-image:linear-gradient(180deg,#101f46 0%,#0b1530 100%);">
                            <a href="{{ url('/') }}" target="_blank" style="display:inline-block;">
                                @if($logoUrl !== '')
                                    <img src="{{ $logoUrl }}" width="168" alt="{{ $brand['name'] }}" style="width:168px;max-width:100%;height:auto;margin:0 auto;">
                                @else
                                    <span style="color:#ffffff;font-size:26px;font-weight:800;line-height:34px;">{{ $brand['name'] }}</span>
                                @endif
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding:8px 42px 38px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding:0 0 17px;color:#facc15;font-size:12px;font-weight:700;line-height:18px;letter-spacing:1.5px;text-transform:uppercase;">{{ $copy['eyebrow'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 9px;color:#aebee1;font-size:15px;line-height:23px;">{{ $copy['greeting'] }}{{ $name !== '' ? ' ' . $name : '' }},</td>
                                </tr>
                                <tr>
                                    <td class="email-title" style="padding:0 0 16px;color:#ffffff;font-size:34px;font-weight:800;line-height:40px;letter-spacing:-0.7px;">{{ $copy['title'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 27px;color:#c0ccea;font-size:16px;line-height:26px;">{{ $copy['body'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 28px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td align="center" bgcolor="{{ $brand['button'] }}" style="background-color:{{ $brand['button'] }};border-radius:12px;mso-padding-alt:15px 25px;">
                                                    <a class="email-button" href="{{ $activationUrl }}" target="_blank" style="display:inline-block;padding:15px 25px;color:#08111f;font-size:16px;font-weight:800;line-height:20px;text-decoration:none;border-radius:12px;">{{ $copy['cta'] }}&nbsp;&nbsp;&rarr;</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 20px;background-color:#081126;border:1px solid #1b3266;border-radius:14px;">
                                        <div style="margin:0 0 7px;color:#9fb1d8;font-size:12px;line-height:18px;">{{ $copy['fallback'] }}</div>
                                        <a href="{{ $activationUrl }}" target="_blank" style="color:#7dd3fc;font-size:12px;line-height:19px;text-decoration:underline;word-break:break-all;overflow-wrap:anywhere;">{{ $activationUrl }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:24px 0 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td width="3" bgcolor="#2b65d9" style="width:3px;background-color:#2b65d9;border-radius:3px;">&nbsp;</td>
                                                <td style="padding:0 0 0 14px;">
                                                    <div style="margin:0 0 4px;color:#f8fbff;font-size:13px;font-weight:700;line-height:19px;">{{ $copy['security_title'] }}</div>
                                                    <div style="color:#8294bc;font-size:12px;line-height:19px;">{{ $copy['security_body'] }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 28px;border-top:1px solid #1a2d58;color:#7184ad;font-size:11px;line-height:18px;">
                            {{ $copy['footer'] }}<br>
                            &copy; {{ date('Y') }} {{ $brand['name'] }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
