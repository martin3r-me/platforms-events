<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rechnung {{ $invoice->invoice_number }}</title>
    <style>
        body { margin: 0; font-family: 'DM Sans', -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 880px; margin: 20px auto; padding: 0 16px; }
        .card { background: white; border-radius: 8px; padding: 28px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        h1 { font-size: 1.4rem; margin: 0 0 8px 0; }
        .meta { color: #64748b; font-size: 0.85rem; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        th { text-align: left; background: #f8fafc; padding: 8px; font-size: 0.7rem; text-transform: uppercase; color: #64748b; }
        td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; font-family: 'DM Mono', monospace; }
        .totals { background: #f8fafc; padding: 16px; border-radius: 8px; margin-top: 20px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .brutto { border-top: 1px solid #cbd5e1; padding-top: 8px; margin-top: 6px; font-weight: 700; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Rechnung {{ $invoice->invoice_number }}</h1>
            <div class="meta">
                Event {{ $event->event_number }} · {{ $invoice->invoice_date?->format('d.m.Y') }}
                @if($invoice->due_date) · Fällig {{ $invoice->due_date->format('d.m.Y') }} @endif
            </div>

            <div style="padding: 12px; background: #f8fafc; border-radius: 6px; font-size: 0.85rem; margin-bottom: 16px;">
                <strong>{{ $invoice->customer_company }}</strong><br>
                {{ $invoice->customer_contact }}<br>
                {{ $invoice->customer_address }}<br>
                {{ $invoice->customer_city }}
            </div>

            @if($invoice->items->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th>Bezeichnung</th>
                            <th class="num">Anz</th>
                            <th>Gebinde</th>
                            <th class="num">Einzel</th>
                            <th class="num">MwSt</th>
                            <th class="num">Gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $i)
                            <tr>
                                <td>{{ $i->name }}</td>
                                <td class="num">{{ (float) $i->quantity }}</td>
                                <td>{{ $i->gebinde }}</td>
                                <td class="num">{{ number_format((float) $i->unit_price, 2, ',', '.') }} €</td>
                                <td class="num">{{ $i->mwst_rate }}%</td>
                                <td class="num">{{ number_format((float) $i->total, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="totals">
                <div class="row"><span>Netto</span><span class="num">{{ number_format((float) $invoice->netto, 2, ',', '.') }} €</span></div>
                @if((float) $invoice->mwst_7 != 0)
                    <div class="row"><span>MwSt 7%</span><span class="num">{{ number_format((float) $invoice->mwst_7, 2, ',', '.') }} €</span></div>
                @endif
                @if((float) $invoice->mwst_19 != 0)
                    <div class="row"><span>MwSt 19%</span><span class="num">{{ number_format((float) $invoice->mwst_19, 2, ',', '.') }} €</span></div>
                @endif
                <div class="row brutto"><span>Brutto</span><span class="num">{{ number_format((float) $invoice->brutto, 2, ',', '.') }} €</span></div>
            </div>
        </div>
    </div>
</body>
</html>
