<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RiseCMS</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f7fb;
            color: #172033;
            font: 600 20px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            padding: 24px;
            text-align: center;
            max-width: 680px;
        }

        p {
            margin: 12px 0 0;
            font: 500 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #4c5873;
        }

        code {
            display: inline-block;
            margin-top: 16px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #e8edf7;
            color: #172033;
            font: 700 12px/1.2 ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        dl {
            margin: 18px auto 0;
            max-width: 560px;
            display: grid;
            grid-template-columns: minmax(140px, 1fr) minmax(0, 2fr);
            gap: 8px 14px;
            text-align: left;
            font: 500 13px/1.45 ui-monospace, SFMono-Regular, Menlo, monospace;
            color: #29344d;
        }

        dt {
            color: #69748c;
        }

        dd {
            margin: 0;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body>
<main>
    <div>{{ $message ?? 'Under Maintenance' }}</div>
    @if(filled($details ?? null))
        <p>{{ $details }}</p>
    @endif
    @if(filled($errorCode ?? null))
        <code>{{ $errorCode }}</code>
    @endif
    @if(!empty($localDiagnostics ?? []))
        <dl>
            @foreach($localDiagnostics as $label => $value)
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            @endforeach
        </dl>
    @endif
</main>
</body>
</html>
