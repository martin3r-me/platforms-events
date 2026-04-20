<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Projekt-Function {{ $event->event_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; }
        h1 { font-size: 18pt; margin: 0 0 4px 0; }
        h2 { font-size: 12pt; margin: 16px 0 6px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .meta { color: #64748b; font-size: 9pt; margin-bottom: 16px; }
        ul { margin: 4px 0 12px 20px; }
        li { font-size: 9.5pt; margin-bottom: 3px; }
        .muted { color: #64748b; font-style: italic; }
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

        @if($bookings->isEmpty() && $schedule->isEmpty())
            <p class="muted">Keine Räume/Ablaufpunkte</p>
        @endif
    @endforeach
</body>
</html>
