<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Schlussbericht {{ $event->event_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; }
        h1 { font-size: 18pt; margin: 0 0 4px 0; }
        h2 { font-size: 12pt; margin: 16px 0 6px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .meta { color: #64748b; font-size: 9pt; margin-bottom: 16px; }
        .grid { display: table; width: 100%; }
        .grid-cell { display: table-cell; width: 33%; padding: 6px; vertical-align: top; font-size: 9pt; }
        .label { color: #94a3b8; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.05em; }
        .stats { display: table; width: 100%; margin: 12px 0; }
        .stat { display: table-cell; text-align: center; padding: 10px; background: #f1f5f9; border-radius: 4px; }
        .stat-num { font-size: 20pt; font-weight: bold; }
        .stat-label { font-size: 8pt; color: #64748b; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>Schlussbericht</h1>
    <div class="meta">{{ $event->event_number }} · {{ $event->name }} · {{ now()->format('d.m.Y') }}</div>

    <h2>Stammdaten</h2>
    <div class="grid">
        <div class="grid-cell">
            <div class="label">VA-Nr</div><div>{{ $event->event_number }}</div>
        </div>
        <div class="grid-cell">
            <div class="label">Status</div><div>{{ $event->status }}</div>
        </div>
        <div class="grid-cell">
            <div class="label">Kunde</div><div>{{ $event->customer ?: '—' }}</div>
        </div>
        <div class="grid-cell">
            <div class="label">Zeitraum</div><div>{{ $event->start_date?->format('d.m.Y') }}@if($event->end_date) – {{ $event->end_date->format('d.m.Y') }} @endif</div>
        </div>
        <div class="grid-cell">
            <div class="label">Verantwortlich</div><div>{{ $event->responsible ?: '—' }}</div>
        </div>
        <div class="grid-cell">
            <div class="label">Kostenstelle</div><div>{{ $event->cost_center ?: '—' }}</div>
        </div>
    </div>

    <h2>Kennzahlen</h2>
    <div class="stats">
        <div class="stat"><div class="stat-num">{{ $event->days->count() }}</div><div class="stat-label">Tage</div></div>
        <div class="stat"><div class="stat-num">{{ $event->bookings->count() }}</div><div class="stat-label">Räume</div></div>
        <div class="stat"><div class="stat-num">{{ $event->scheduleItems->count() }}</div><div class="stat-label">Ablauf</div></div>
        <div class="stat"><div class="stat-num">{{ $event->quotes->count() }}</div><div class="stat-label">Angebote</div></div>
        <div class="stat"><div class="stat-num">{{ $event->invoices->count() }}</div><div class="stat-label">Rechnungen</div></div>
    </div>

    @if($event->bookings->isNotEmpty())
        <h2>Räume</h2>
        <ul>
            @foreach($event->bookings as $b)
                <li>{{ $b->location?->name ?: $b->raum }} · {{ $b->datum }} · {{ $b->beginn }}–{{ $b->ende }} · {{ $b->optionsrang }}</li>
            @endforeach
        </ul>
    @endif

    @if($event->notes->isNotEmpty())
        <h2>Notizen</h2>
        @foreach($event->notes as $n)
            <div style="margin-bottom: 8px;">
                <strong>{{ $n->type }}</strong> ({{ $n->created_at->format('d.m.Y') }}): {{ $n->text }}
            </div>
        @endforeach
    @endif
</body>
</html>
