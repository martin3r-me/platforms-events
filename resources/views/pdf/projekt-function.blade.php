<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Projekt-Function {{ $event->event_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; }
        h1 { font-size: 18pt; margin: 0 0 4px 0; }
        h2 { font-size: 12pt; margin: 16px 0 6px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        h3 { font-size: 10.5pt; margin: 10px 0 4px 0; color: #334155; }
        .meta { color: #64748b; font-size: 9pt; margin-bottom: 16px; }
        ul { margin: 4px 0 12px 20px; }
        li { font-size: 9.5pt; margin-bottom: 3px; }
        .muted { color: #64748b; font-style: italic; }
        table.pos { border-collapse: collapse; width: 100%; margin: 4px 0 12px 0; font-size: 8.5pt; }
        table.pos th, table.pos td { border: 1px solid #e2e8f0; padding: 3px 5px; text-align: left; vertical-align: top; }
        table.pos th { background: #f8fafc; font-weight: 700; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.03em; color: #475569; }
        table.pos td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .typ-chip { display: inline-block; padding: 1px 6px; border-radius: 8px; background: #eef2ff; color: #3730a3; font-size: 7.5pt; font-weight: 700; }
        .sum { text-align: right; font-weight: 700; margin-top: 2px; }
    </style>
</head>
<body>
    <h1>Projekt-Function</h1>
    <div class="meta">
        {{ $event->event_number }} · {{ $event->name }}
        @if($event->customer) · {{ $event->customer }} @endif<br>
        Zeitraum: {{ $event->start_date?->format('d.m.Y') }}
        @if($event->end_date) – {{ $event->end_date->format('d.m.Y') }} @endif
        @if($event->responsible) · Verantwortlich: {{ $event->responsible }} @endif
    </div>

    @foreach($event->days->sortBy('sort_order') as $day)
        @php
            $bookings = $event->bookings->filter(fn($b) => $b->datum === $day->datum?->format('Y-m-d'));
            $schedule = $event->scheduleItems->filter(fn($s) => $s->datum === $day->datum?->format('Y-m-d') || $s->datum === $day->datum?->format('d.m.Y'));
            $items = ($day->quoteItems ?? collect())->sortBy('sort_order');
            if (($mode ?? 'kitchen') === 'kitchen') {
                $items = $items->filter(function ($q) {
                    $t = mb_strtolower((string) ($q->typ ?? ''));
                    return str_contains($t, 'speis') || str_contains($t, 'getr');
                });
            }
        @endphp
        <h2>{{ $day->label }} · {{ $day->datum?->format('d.m.Y') }} · {{ $day->day_of_week }}</h2>

        @if($bookings->isNotEmpty())
            <strong>Räume</strong>
            <ul>
                @foreach($bookings as $b)
                    <li><strong>{{ $b->location?->kuerzel ?: $b->raum }}</strong>: {{ $b->beginn }}–{{ $b->ende }}@if($b->pers) · {{ $b->pers }} Pers @endif@if($b->bestuhlung) · {{ $b->bestuhlung }} @endif</li>
                @endforeach
            </ul>
        @endif

        @if($schedule->isNotEmpty())
            <strong>Ablauf</strong>
            <ul>
                @foreach($schedule as $s)
                    <li>{{ $s->von }}@if($s->bis)–{{ $s->bis }}@endif · {{ $s->beschreibung }}@if($s->raum) @ {{ $s->raum }} @endif</li>
                @endforeach
            </ul>
        @endif

        @foreach($items as $item)
            @php $positions = ($item->posList ?? collect())->sortBy('sort_order'); @endphp
            <h3>
                <span class="typ-chip">{{ $item->typ ?: 'Vorgang' }}</span>
                @if($item->status) <span class="muted" style="font-weight: normal;">· {{ $item->status }}</span>@endif
            </h3>
            @if($positions->isEmpty())
                <p class="muted">Keine Positionen</p>
            @else
                <table class="pos">
                    <thead>
                        <tr>
                            <th style="width:100px;">Gruppe</th>
                            <th>Name</th>
                            <th style="width:40px;">Anz</th>
                            <th style="width:60px;">Uhrzeit</th>
                            @if(($mode ?? 'kitchen') === 'manager')
                                <th style="width:60px;">Preis</th>
                                <th style="width:60px;">Gesamt</th>
                            @endif
                            <th>Bemerkung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($positions as $p)
                            <tr>
                                <td>{{ $p->gruppe }}</td>
                                <td>{{ $p->name }}</td>
                                <td class="num">{{ $p->anz }}@if($p->anz2) / {{ $p->anz2 }}@endif</td>
                                <td>{{ $p->uhrzeit }}@if($p->bis)–{{ $p->bis }}@endif</td>
                                @if(($mode ?? 'kitchen') === 'manager')
                                    <td class="num">@if($p->preis !== null && $p->preis !== '') {{ number_format((float) $p->preis, 2, ',', '.') }} @endif</td>
                                    <td class="num">@if($p->gesamt !== null && $p->gesamt !== '') {{ number_format((float) $p->gesamt, 2, ',', '.') }} @endif</td>
                                @endif
                                <td>{{ $p->bemerkung }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(($mode ?? 'kitchen') === 'manager' && $item->umsatz)
                    <div class="sum">Umsatz: {{ number_format((float) $item->umsatz, 2, ',', '.') }} €</div>
                @endif
            @endif
        @endforeach

        @if($bookings->isEmpty() && $schedule->isEmpty() && $items->isEmpty())
            <p class="muted">Keine Räume/Ablaufpunkte/Vorgänge</p>
        @endif
    @endforeach
</body>
</html>
