<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Function Sheet {{ $event->event_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; margin: 0; padding: 20px 28px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; padding: 4px 8px; font-size: 8pt; border-bottom: 2px solid #cbd5e1; }
        td { padding: 3px 8px; border-bottom: 1px solid #e2e8f0; font-size: 8.5pt; vertical-align: top; }
        .page-break { page-break-before: always; }
        .header { text-align: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #2563eb; }
        .header h1 { font-size: 16pt; font-weight: bold; margin: 0 0 4px; letter-spacing: 2px; color: #1e293b; }
        .header p { font-size: 9pt; color: #64748b; margin: 0; }
        .info-grid { width: 100%; margin-bottom: 16px; }
        .info-grid td { border: none; padding: 2px 8px 2px 0; vertical-align: top; }
        .info-label { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 9pt; font-weight: bold; }
        .day-block { page-break-inside: avoid; margin-bottom: 14px; }
        .day-header { font-size: 12pt; font-weight: bold; margin: 14px 0 2px; color: #1e293b; padding: 4px 8px; background: #eff6ff; border-left: 4px solid #2563eb; }
        .day-meta { font-size: 8pt; color: #64748b; margin: 0 0 6px; padding: 0 8px 4px; }
        .sub-title { font-size: 9pt; font-weight: bold; text-transform: uppercase; color: #2563eb; margin: 8px 0 3px; }
        .note-type { font-size: 9.5pt; font-weight: bold; text-transform: uppercase; color: #2563eb; margin: 10px 0 4px; padding: 3px 0; border-bottom: 1px solid #dbeafe; }
        .note { font-size: 8.5pt; color: #374151; margin: 0 0 6px; white-space: pre-wrap; line-height: 1.45; }
        .note .note-meta { font-size: 7pt; color: #94a3b8; }
        .muted { color: #94a3b8; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding: 4px 0; }
    </style>
</head>
<body>

    {{-- ===== FOOTER ===== --}}
    <div class="footer">
        Function Sheet &middot; {{ $event->event_number }} &middot; Erstellt: {{ $generated_at }}{{ $generated_by ? ' von ' . $generated_by : '' }}
    </div>

    {{-- ===== HEADER ===== --}}
    <div class="header">
        <h1>FUNCTION SHEET</h1>
        <p>{{ $event->name ?: 'Veranstaltung' }} &middot; {{ $event->event_number }}</p>
    </div>

    {{-- ===== EVENT-INFO ===== --}}
    <table class="info-grid">
        <tr>
            <td style="width:33%;">
                <div class="info-label">Zeitraum</div>
                <div class="info-value">
                    {{ $event->start_date?->format('d.m.Y') ?: '—' }}@if($event->end_date && $event->end_date != $event->start_date) – {{ $event->end_date->format('d.m.Y') }}@endif
                </div>
            </td>
            <td style="width:33%;">
                <div class="info-label">Status</div>
                <div class="info-value">{{ $event->status ?: '—' }}</div>
            </td>
            <td style="width:34%;">
                <div class="info-label">Anlass</div>
                <div class="info-value">{{ $event->event_type ?: '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="info-label">Veranstalter</div>
                <div class="info-value">{{ $event->customer ?: '—' }}</div>
            </td>
            <td>
                <div class="info-label">ASP vor Ort</div>
                <div class="info-value">{{ $event->organizer_contact_onsite ?: ($event->organizer_contact ?: '—') }}</div>
            </td>
            <td>
                <div class="info-label">Projektleitung</div>
                <div class="info-value">{{ $event->responsible_onsite ?: ($event->responsible ?: '—') }}</div>
            </td>
        </tr>
    </table>

    {{-- ===== PRO TAG: BUCHUNGEN + ABLAUF ===== --}}
    @forelse($days as $day)
        @php
            $key = $day->datum?->format('Y-m-d');
            $dayBookings = $key ? ($bookingsByDate[$key] ?? collect()) : collect();
            $daySchedule = $key ? ($scheduleByDate[$key] ?? collect()) : collect();
            $persLabel = $day->pers_von || $day->pers_bis
                ? trim(($day->pers_von ?: '') . ($day->pers_bis && $day->pers_bis !== $day->pers_von ? '–' . $day->pers_bis : '')) . ' Pers.'
                : null;
        @endphp
        <div class="day-block">
            <div class="day-header">
                {{ $day->datum?->format('d.m.Y') ?: '—' }}@if($day->day_of_week) ({{ $day->day_of_week }})@endif
                @if($day->day_type) &middot; {{ $day->day_type }}@endif
            </div>
            <p class="day-meta">
                @if($day->start_time || $day->end_time){{ $day->start_time ?: '—' }} – {{ $day->end_time ?: '—' }} Uhr@endif
                @if($persLabel) &middot; {{ $persLabel }}@endif
                @if($day->label && $day->label !== $day->datum?->format('d.m.Y')) &middot; {{ $day->label }}@endif
            </p>

            <div class="sub-title">Räume</div>
            @if($dayBookings->isEmpty())
                <p class="note muted">Keine Raumbuchungen an diesem Tag.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width:18%;">Raum</th>
                            <th style="width:16%;">Zeit</th>
                            <th style="width:10%;">Pers.</th>
                            <th style="width:18%;">Bestuhlung</th>
                            <th style="width:14%;">Rang</th>
                            <th>Absprache</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dayBookings as $b)
                            <tr>
                                <td><strong>{{ $b->location?->kuerzel ?: $b->raum ?: '—' }}</strong>@if($b->location?->name) <span class="muted">{{ $b->location->name }}</span>@endif</td>
                                <td>@if($b->start_time || $b->end_time){{ $b->start_time ?: '—' }} – {{ $b->end_time ?: '—' }}@else —@endif</td>
                                <td>{{ $b->pers ?: '—' }}</td>
                                <td>{{ $b->bestuhlung ?: '—' }}</td>
                                <td>{{ $b->optionsrang ?: '—' }}</td>
                                <td>{{ $b->absprache ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($daySchedule->isNotEmpty())
                <div class="sub-title">Ablauf</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:16%;">Zeit</th>
                            <th style="width:36%;">Programmpunkt</th>
                            <th style="width:16%;">Raum/Ort</th>
                            <th>Bemerkung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($daySchedule as $s)
                            <tr>
                                <td>@if($s->start_time || $s->end_time){{ $s->start_time ?: '—' }}@if($s->end_time) – {{ $s->end_time }}@endif @else — @endif</td>
                                <td><strong>{{ $s->beschreibung ?: '—' }}</strong></td>
                                <td>{{ $s->raum ?: '—' }}</td>
                                <td>{{ $s->bemerkung ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="note muted">Keine Veranstaltungstage angelegt.</p>
    @endforelse

    {{-- ===== EINTRAEGE OHNE EVENT-TAG (z. B. Anlieferung am Vortag) ===== --}}
    @if($orphanBookings->isNotEmpty() || $orphanSchedule->isNotEmpty())
        <div class="day-block">
            <div class="day-header">Weitere Termine (außerhalb der Event-Tage)</div>
            @if($orphanBookings->isNotEmpty())
                <div class="sub-title">Räume</div>
                <table>
                    <tbody>
                        @foreach($orphanBookings as $b)
                            <tr>
                                <td style="width:16%;">{{ $b->datum ? \Carbon\Carbon::parse($b->datum)->format('d.m.Y') : '—' }}</td>
                                <td style="width:18%;"><strong>{{ $b->location?->kuerzel ?: $b->raum ?: '—' }}</strong></td>
                                <td style="width:16%;">@if($b->start_time || $b->end_time){{ $b->start_time ?: '—' }} – {{ $b->end_time ?: '—' }}@else —@endif</td>
                                <td>{{ $b->absprache ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if($orphanSchedule->isNotEmpty())
                <div class="sub-title">Ablauf</div>
                <table>
                    <tbody>
                        @foreach($orphanSchedule as $s)
                            <tr>
                                <td style="width:16%;">{{ $s->datum ? \Carbon\Carbon::parse($s->datum)->format('d.m.Y') : '—' }}</td>
                                <td style="width:16%;">@if($s->start_time){{ $s->start_time }}@if($s->end_time) – {{ $s->end_time }}@endif @else — @endif</td>
                                <td><strong>{{ $s->beschreibung ?: '—' }}</strong></td>
                                <td>{{ $s->bemerkung ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- ===== NOTIZEN / ABSPRACHEN ===== --}}
    @php
        $noteTypeLabels = [
            'liefertext'   => 'Liefertext',
            'absprache'    => 'Absprachen',
            'vereinbarung' => 'Vereinbarungen',
            'intern'       => 'Interne Notizen',
        ];
    @endphp
    @if($notesByType->isNotEmpty())
        <div class="day-block">
            <div class="day-header">Notizen &amp; Absprachen</div>
            @foreach($noteTypeLabels as $type => $label)
                @if(($notesByType[$type] ?? collect())->isNotEmpty())
                    <div class="note-type">{{ $label }}</div>
                    @foreach($notesByType[$type] as $note)
                        <p class="note">{{ $note->text }} @if($note->user_name)<span class="note-meta">— {{ $note->user_name }}</span>@endif</p>
                    @endforeach
                @endif
            @endforeach
        </div>
    @endif

</body>
</html>
