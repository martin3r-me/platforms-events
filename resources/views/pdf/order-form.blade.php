<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bestellschein {{ $event->event_number }} · {{ $item->typ }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.45; }
        h1 { font-size: 15pt; margin: 0 0 4px 0; }
        p { margin: 6px 0; }
        hr { border: 0; border-top: 1px solid #e2e8f0; margin: 12px 0; }
        img { max-width: 100%; }

        /* Positions-Tabelle (vom OrderFormRenderer erzeugt) */
        table.positions { width: 100%; border-collapse: collapse; margin: 8px 0 4px 0; }
        table.positions th { background: #f1f5f9; text-align: left; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; font-size: 8.5pt; font-weight: bold; }
        table.positions td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9pt; vertical-align: top; }
        table.positions .num { text-align: right; font-family: 'DejaVu Sans Mono', monospace; white-space: nowrap; }
        table.positions th.num { text-align: right; }

        .footer { margin-top: 28px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 8pt; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    {!! \Platform\Events\Services\OrderFormRenderer::renderHtml($item, $event, 'pdf') !!}

    <div class="footer">www.broichcatering.com</div>
</body>
</html>
