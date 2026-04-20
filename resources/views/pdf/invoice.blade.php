<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rechnung {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; }
        h1 { font-size: 18pt; margin: 0 0 6px 0; }
        .meta { color: #64748b; font-size: 9pt; }
        .header { display: table; width: 100%; margin-bottom: 20px; }
        .hl, .hr { display: table-cell; vertical-align: top; }
        .hr { text-align: right; }
        .customer { background: #f1f5f9; padding: 10px; border-radius: 4px; margin-bottom: 12px; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px 0; }
        th { background: #f1f5f9; text-align: left; padding: 6px; border-bottom: 1px solid #cbd5e1; font-size: 8.5pt; font-weight: bold; }
        td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9pt; }
        .num { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .totals { margin-top: 16px; padding: 10px; background: #f1f5f9; border-radius: 4px; }
        .row { display: table; width: 100%; }
        .cell { display: table-cell; padding: 2px 6px; }
        .cell-right { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .brutto { font-weight: bold; font-size: 11pt; border-top: 1px solid #64748b; padding-top: 6px; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="hl">
            <h1>@switch($invoice->type)
                @case('rechnung') Rechnung @break
                @case('teilrechnung') Teilrechnung @break
                @case('schlussrechnung') Schlussrechnung @break
                @case('gutschrift') Gutschrift @break
                @case('storno') Storno @break
                @default {{ ucfirst($invoice->type) }}
            @endswitch</h1>
            <div class="meta">Rechnungs-Nr.: <strong>{{ $invoice->invoice_number }}</strong></div>
        </div>
        <div class="hr">
            <div class="meta">
                Datum: {{ $invoice->invoice_date?->format('d.m.Y') ?: now()->format('d.m.Y') }}<br>
                @if($invoice->due_date) Fällig: {{ $invoice->due_date->format('d.m.Y') }}<br> @endif
                Event: {{ $event->event_number }}
            </div>
        </div>
    </div>

    <div class="customer">
        <strong>{{ $invoice->customer_company }}</strong><br>
        {{ $invoice->customer_contact }}<br>
        {{ $invoice->customer_address }}<br>
        {{ $invoice->customer_city }}
    </div>

    @if($invoice->items->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Pos.</th>
                    <th>Bezeichnung</th>
                    <th class="num">Menge</th>
                    <th>Gebinde</th>
                    <th class="num">Einzelpreis</th>
                    <th class="num">MwSt</th>
                    <th class="num">Gesamt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->name }}
                            @if($item->description)<br><span style="font-size: 8pt; color: #64748b;">{{ $item->description }}</span>@endif
                        </td>
                        <td class="num">{{ (float) $item->quantity }}</td>
                        <td>{{ $item->gebinde }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 2, ',', '.') }} €</td>
                        <td class="num">{{ $item->mwst_rate }}%</td>
                        <td class="num">{{ number_format((float) $item->total, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="totals">
        <div class="row">
            <div class="cell">Netto</div>
            <div class="cell cell-right">{{ number_format((float) $invoice->netto, 2, ',', '.') }} €</div>
        </div>
        @if((float) $invoice->mwst_7 != 0)
            <div class="row">
                <div class="cell">MwSt 7%</div>
                <div class="cell cell-right">{{ number_format((float) $invoice->mwst_7, 2, ',', '.') }} €</div>
            </div>
        @endif
        @if((float) $invoice->mwst_19 != 0)
            <div class="row">
                <div class="cell">MwSt 19%</div>
                <div class="cell cell-right">{{ number_format((float) $invoice->mwst_19, 2, ',', '.') }} €</div>
            </div>
        @endif
        <div class="row brutto">
            <div class="cell">Brutto</div>
            <div class="cell cell-right">{{ number_format((float) $invoice->brutto, 2, ',', '.') }} €</div>
        </div>
    </div>

    @if($invoice->notes)
        <div style="margin-top: 20px; font-size: 9pt;">{{ $invoice->notes }}</div>
    @endif
</body>
</html>
