<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Vertrag {{ $event->event_number }} v{{ $contract->version }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.5; }
        h1 { font-size: 18pt; margin: 0 0 6px 0; }
        h2 { font-size: 13pt; margin: 14px 0 6px 0; }
        h3 { font-size: 11pt; margin: 10px 0 4px 0; }
        p { margin: 6px 0; }
        ul, ol { margin: 6px 0 6px 18px; padding: 0; }
        li { margin: 2px 0; }
        table { border-collapse: collapse; width: 100%; margin: 8px 0; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; }
        img { max-width: 100%; height: auto; }
        code { background: #f1f5f9; padding: 1px 4px; border-radius: 3px; }
        hr { border: 0; border-top: 1px solid #e2e8f0; margin: 10px 0; }
        .meta { color: #64748b; font-size: 9pt; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px; }
        .body { font-size: 10pt; }
        .signatures { margin-top: 60px; display: table; width: 100%; }
        .sig { display: table-cell; width: 50%; padding: 0 10px; }
        .sig-line { border-top: 1px solid #1e293b; padding-top: 6px; font-size: 9pt; color: #64748b; }
    </style>
</head>
<body>
    <h1>
        @switch($contract->type)
            @case('nutzungsvertrag') Nutzungsvertrag @break
            @case('optionsbestaetigung') Optionsbestätigung @break
            @default {{ ucfirst(str_replace('-', ' ', $contract->type)) }}
        @endswitch
    </h1>
    <div class="meta">
        {{ $event->event_number }} · v{{ $contract->version }} · {{ now()->format('d.m.Y') }}<br>
        {{ $event->name }}@if($event->customer) · {{ $event->customer }} @endif
    </div>

    <div class="body">{!! \Platform\Events\Services\ContractRenderer::renderHtml($contract, $event, 'pdf') !!}</div>

    <div class="signatures">
        <div class="sig">
            <div style="height: 40px;"></div>
            <div class="sig-line">{{ $event->sign_left ?: 'Unterschrift Auftraggeber' }}</div>
        </div>
        <div class="sig">
            <div style="height: 40px;"></div>
            <div class="sig-line">{{ $event->sign_right ?: 'Unterschrift Auftragnehmer' }}</div>
        </div>
    </div>
</body>
</html>
