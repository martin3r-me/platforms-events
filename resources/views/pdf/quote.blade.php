<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Angebot {{ $event->event_number }} v{{ $quote->version }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; }
        h1 { font-size: 18pt; margin: 0 0 4px 0; color: #1e293b; }
        h2 { font-size: 13pt; margin: 16px 0 8px 0; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .header { border-bottom: 2px solid #1e293b; padding-bottom: 12px; margin-bottom: 16px; }
        .meta { color: #64748b; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px 0; }
        th { background: #f1f5f9; text-align: left; padding: 6px; border-bottom: 1px solid #cbd5e1; font-size: 8.5pt; font-weight: bold; }
        td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9pt; }
        .num { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .totals { margin-top: 16px; padding: 10px; background: #f1f5f9; border-radius: 4px; }
        .totals .row { display: table; width: 100%; }
        .totals .cell { display: table-cell; padding: 2px 6px; font-size: 10pt; }
        .totals .cell-right { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .totals .brutto { font-weight: bold; font-size: 11pt; border-top: 1px solid #64748b; padding-top: 6px; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Angebot {{ $event->event_number }}</h1>
        <div class="meta">
            Version {{ $quote->version }} · {{ now()->format('d.m.Y') }}<br>
            {{ $event->name }}
            @if($event->customer) · {{ $event->customer }} @endif
            @if($event->start_date)
                <br>Zeitraum: {{ $event->start_date->format('d.m.Y') }}
                @if($event->end_date && $event->end_date != $event->start_date)
                    – {{ $event->end_date->format('d.m.Y') }}
                @endif
            @endif
        </div>
    </div>

    @php
        $totalNetto = 0;
        $totalMwst7 = 0;
        $totalMwst19 = 0;
    @endphp

    @foreach($days as $day)
        @php $dayItems = $items->get($day->id, collect()); @endphp
        @if($dayItems->isNotEmpty())
            <h2>{{ $day->label }} · {{ $day->datum?->format('d.m.Y') }}</h2>

            @foreach($dayItems as $item)
                <p style="margin: 4px 0; font-weight: bold;">{{ $item->typ }}</p>
                @if($item->posList->isNotEmpty())
                    <table>
                        <thead>
                            <tr>
                                <th>Gruppe</th>
                                <th>Bezeichnung</th>
                                <th class="num">Anz</th>
                                <th>Gebinde</th>
                                <th class="num">Einzelpreis</th>
                                <th class="num">Gesamt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($item->posList as $pos)
                                @php
                                    $gesamt = (float) $pos->gesamt;
                                    $mwstRate = (int) $pos->mwst;
                                    if ($mwstRate === 7) {
                                        $totalMwst7 += $gesamt;
                                    } else {
                                        $totalMwst19 += $gesamt;
                                    }
                                    $totalNetto += $gesamt;
                                @endphp
                                <tr>
                                    <td>{{ $pos->gruppe }}</td>
                                    <td>{{ $pos->name }}</td>
                                    <td class="num">{{ $pos->anz }}</td>
                                    <td>{{ $pos->gebinde }}</td>
                                    <td class="num">{{ number_format((float) $pos->preis, 2, ',', '.') }} €</td>
                                    <td class="num">{{ number_format($gesamt, 2, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        @endif
    @endforeach

    <div class="totals">
        <div class="row">
            <div class="cell">Netto</div>
            <div class="cell cell-right">{{ number_format($totalNetto, 2, ',', '.') }} €</div>
        </div>
        @if($totalMwst7 > 0)
            <div class="row">
                <div class="cell">MwSt 7%</div>
                <div class="cell cell-right">{{ number_format($totalMwst7 * 0.07, 2, ',', '.') }} €</div>
            </div>
        @endif
        @if($totalMwst19 > 0)
            <div class="row">
                <div class="cell">MwSt 19%</div>
                <div class="cell cell-right">{{ number_format($totalMwst19 * 0.19, 2, ',', '.') }} €</div>
            </div>
        @endif
        <div class="row brutto">
            <div class="cell">Brutto</div>
            <div class="cell cell-right">{{ number_format($totalNetto + $totalMwst7 * 0.07 + $totalMwst19 * 0.19, 2, ',', '.') }} €</div>
        </div>
    </div>

    @if($quote->valid_until)
        <p style="margin-top: 16px; font-size: 9pt; color: #64748b;">Gültig bis {{ $quote->valid_until->format('d.m.Y') }}</p>
    @endif

    @if($quote->shouldAttachFloorPlans())
        @php $floorPlanLocations = $quote->floorPlanLocations(); @endphp
        @if($floorPlanLocations->isNotEmpty())
            <div style="page-break-before: always;"></div>
            <h2 style="margin-top: 0;">Raumgrundrisse</h2>
            <p style="font-size: 9pt; color: #64748b; margin: 0 0 12px 0;">
                Grundrisse der im Angebot gebuchten Raeume.
            </p>

            @foreach($floorPlanLocations as $loc)
                <div style="margin-bottom: 14px; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                    <div style="font-size: 10.5pt; font-weight: bold; color: #1e293b; margin-bottom: 6px;">
                        {{ $loc->name }}
                        @if($loc->kuerzel) <span style="color: #64748b; font-weight: normal; font-size: 9pt;">({{ $loc->kuerzel }})</span>@endif
                    </div>
                    @if($loc->floorPlanIsImage())
                        @php
                            $raw = $loc->floorPlanContents();
                            $mime = $loc->floorPlanMimeType();
                        @endphp
                        @if($raw && $mime)
                            <img src="data:{{ $mime }};base64,{{ base64_encode($raw) }}"
                                 style="max-width: 100%; max-height: 620px; display: block; margin: 0 auto;" />
                        @else
                            <p style="font-size: 9pt; color: #b45309; margin: 4px 0 0 0;">Grundriss konnte nicht geladen werden.</p>
                        @endif
                    @elseif($loc->floorPlanIsPdf())
                        <p style="font-size: 9pt; color: #64748b; margin: 4px 0 0 0;">
                            PDF-Grundriss hinterlegt. Einsehbar im Online-Angebot unter dem Kunden-Link.
                        </p>
                    @endif
                </div>
                @if(!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        @endif
    @endif
</body>
</html>
