<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Projekt-Function {{ $event['event_number'] ?? '' }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a1a; margin: 0; padding: 20px 28px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; padding: 4px 8px; font-size: 8pt; border-bottom: 2px solid #cbd5e1; }
        td { padding: 3px 8px; border-bottom: 1px solid #e2e8f0; font-size: 8.5pt; }
        .page-break { page-break-before: always; }
        .header { text-align: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #2563eb; }
        .header h1 { font-size: 16pt; font-weight: bold; margin: 0 0 4px; letter-spacing: 2px; color: #1e293b; }
        .header p { font-size: 9pt; color: #64748b; margin: 0; }
        .info-grid { width: 100%; margin-bottom: 16px; }
        .info-grid td { border: none; padding: 2px 8px 2px 0; vertical-align: top; }
        .info-label { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 9pt; font-weight: bold; }
        .section-title { font-size: 11pt; font-weight: bold; margin: 12px 0 6px; padding: 4px 0; border-bottom: 1px solid #e2e8f0; color: #1e293b; }
        .section-text { font-size: 8.5pt; color: #374151; margin: 0 0 12px; white-space: pre-wrap; line-height: 1.5; }
        .day-header { font-size: 14pt; font-weight: bold; margin: 0 0 8px; color: #1e293b; }
        .vorgang-header { font-size: 10pt; font-weight: bold; text-transform: uppercase; color: #2563eb; margin: 10px 0 4px; padding: 3px 0; border-bottom: 1px solid #dbeafe; }
        .headline-row td { font-weight: bold; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .speisentext-row td { font-style: italic; color: #64748b; font-size: 8pt; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding: 4px 0; }
        .mini-schedule th { font-size: 7.5pt; padding: 3px 6px; }
        .mini-schedule td { font-size: 8pt; padding: 2px 6px; }
    </style>
</head>
<body>

    {{-- ===== FOOTER ===== --}}
    <div class="footer">
        Projekt Function &middot; {{ $event['event_number'] }} &middot; Erstellt: {{ $generated_at }} von {{ $generated_by }}
    </div>

    {{-- ===== PAGE 1: OVERVIEW ===== --}}
    <div class="header">
        <h1>PROJEKT FUNCTION</h1>
        <p>{{ $event['name'] }} &middot; {{ $event['event_number'] }}</p>
    </div>

    {{-- Event Info Grid --}}
    <table class="info-grid">
        <tr>
            <td style="width:33%;">
                <div class="info-label">Anlass</div>
                <div class="info-value">{{ $event['event_type'] ?: '—' }}</div>
            </td>
            <td style="width:33%;">
                <div class="info-label">Status</div>
                <div class="info-value">{{ $event['status'] ?: '—' }}</div>
            </td>
            <td style="width:34%;">
                <div class="info-label">VA-Nr</div>
                <div class="info-value">{{ $event['event_number'] }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="info-label">Veranstalter</div>
                <div class="info-value">{{ $event['customer'] ?: '—' }}</div>
            </td>
            <td colspan="2">
                <div class="info-label">Lieferadresse</div>
                <div class="info-value">{{ $event['location'] ?: '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="info-label">ASP</div>
                <div class="info-value">{{ $event['organizer_contact'] ?: '—' }}</div>
            </td>
            <td colspan="2">
                <div class="info-label">ASP Vor Ort</div>
                <div class="info-value">{{ $event['organizer_contact_onsite'] ?: '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="info-label">Projektleiter</div>
                <div class="info-value">{{ $event['responsible'] ?: '—' }}</div>
            </td>
            <td>
                <div class="info-label">KST</div>
                <div class="info-value">{{ $event['cost_center'] ?: '—' }}</div>
            </td>
            <td>
                <div class="info-label">Kostenträger</div>
                <div class="info-value">{{ $event['cost_carrier'] ?: '—' }}</div>
            </td>
        </tr>
        @if(!empty($event['delivery_location']) || !empty($event['delivery_address']) || !empty($event['delivery_note']))
        <tr>
            <td>
                <div class="info-label">Lieferadresse</div>
                <div class="info-value">{{ $event['delivery_location'] ?: ($event['delivery_address'] ?: '—') }}</div>
            </td>
            <td colspan="2">
                <div class="info-label">Bemerkung</div>
                <div class="info-value">{{ $event['delivery_note'] ?: '—' }}</div>
            </td>
        </tr>
        @endif
        <tr>
            <td colspan="3">
                <div class="info-label">Personen</div>
                <div class="info-value">
                    @foreach($days as $day)
                        {{ $day['datum'] }}:
                        @if($day['pers_von'] && $day['pers_bis'])
                            {{ $day['pers_von'] }}–{{ $day['pers_bis'] }}
                        @elseif($day['pers_von'])
                            {{ $day['pers_von'] }}
                        @else
                            —
                        @endif
                        @if(!$loop->last) &middot; @endif
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    {{-- Liefertext --}}
    @if(!empty($liefertext))
        <div class="section-title">Liefertext</div>
        <div class="section-text">{{ $liefertext }}</div>
    @endif

    {{-- Vereinbarungen --}}
    @if(!empty($vereinbarungen))
        <div class="section-title">Vereinbarungen</div>
        <div class="section-text">{{ $vereinbarungen }}</div>
    @endif

    {{-- Ablaufplan (global) --}}
    @if(count($schedule) > 0)
        <div class="section-title">Ablaufplan</div>
        <table>
            <thead>
                <tr>
                    <th style="width:14%;">Datum</th>
                    <th style="width:9%;">Von</th>
                    <th style="width:9%;">Bis</th>
                    <th style="width:32%;">Aktivität</th>
                    <th style="width:14%;">Raum</th>
                    <th style="width:22%;">Bemerkung</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedule as $item)
                <tr>
                    <td>{{ $item['datum'] }}</td>
                    <td>{{ $item['von'] }}</td>
                    <td>{{ $item['bis'] }}</td>
                    <td>{{ $item['beschreibung'] }}</td>
                    <td>{{ $item['raum'] }}</td>
                    <td>{{ $item['bemerkung'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ===== PAGE 2+: PER DAY ===== --}}
    @foreach($days as $day)
        @php
            $hasVorgaenge = collect($day['vorgaenge'])->contains(fn($v) => count($v['positionen']) > 0);
        @endphp
        @if($hasVorgaenge)
        <div class="page-break"></div>

        <div class="day-header">{{ $day['full_day'] }}, {{ $day['datum'] }}</div>

        @if($day['von'] || $day['bis'])
            <div style="font-size:8pt; color:#64748b; margin-bottom:8px;">
                Veranstaltungszeit: {{ $day['von'] }}@if($day['bis']) – {{ $day['bis'] }}@endif
                @if($day['pers_von'] || $day['pers_bis'])
                    &middot; Personen: {{ $day['pers_von'] }}@if($day['pers_bis'])–{{ $day['pers_bis'] }}@endif
                @endif
            </div>
        @endif

        {{-- Day schedule --}}
        @if(count($day['schedule']) > 0)
            <table class="mini-schedule" style="margin-bottom:10px;">
                <thead>
                    <tr>
                        <th style="width:12%;">Von</th>
                        <th style="width:12%;">Bis</th>
                        <th style="width:38%;">Aktivität</th>
                        <th style="width:16%;">Raum</th>
                        <th style="width:22%;">Bemerkung</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($day['schedule'] as $sItem)
                    <tr>
                        <td>{{ $sItem['von'] }}</td>
                        <td>{{ $sItem['bis'] }}</td>
                        <td>{{ $sItem['beschreibung'] }}</td>
                        <td>{{ $sItem['raum'] }}</td>
                        <td>{{ $sItem['bemerkung'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Vorgaenge --}}
        @foreach($day['vorgaenge'] as $vorgang)
            @if(count($vorgang['positionen']) > 0)
                <div class="vorgang-header">{{ $vorgang['typ'] }}</div>
                @php $cols = ($showPrices ?? false) ? 7 : 4; @endphp
                <table>
                    <thead>
                        <tr>
                            <th style="width:8%;">Anz.</th>
                            <th style="width:{{ ($showPrices ?? false) ? '28%' : '42%' }};">Artikel</th>
                            <th style="width:12%;">Gebinde</th>
                            @if($showPrices ?? false)
                                <th style="width:10%; text-align:right;">EK €</th>
                                <th style="width:10%; text-align:right;">VK €</th>
                                <th style="width:10%; text-align:right;">Gesamt €</th>
                            @endif
                            <th style="width:{{ ($showPrices ?? false) ? '22%' : '35%' }};">Bemerkung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vorgang['positionen'] as $pos)
                            @if(($pos['gruppe'] ?? '') === 'Headline')
                                <tr class="headline-row">
                                    <td colspan="{{ $cols }}">{{ $pos['name'] }}</td>
                                </tr>
                            @elseif(in_array(($pos['gruppe'] ?? ''), ['Speisentexte', 'Trenntext'], true))
                                <tr class="speisentext-row">
                                    <td colspan="{{ $cols }}">{{ $pos['name'] }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td>{{ $pos['anz'] }}</td>
                                    <td>
                                        {{ $pos['name'] }}
                                        @if(!empty($pos['inhalt']))
                                            <br><span style="font-size:7.5pt; color:#64748b;">{{ $pos['inhalt'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $pos['gebinde'] }}</td>
                                    @if($showPrices ?? false)
                                        <td style="text-align:right; font-family:'DejaVu Sans Mono', monospace; font-size:8pt;">{{ $pos['ek'] }}</td>
                                        <td style="text-align:right; font-family:'DejaVu Sans Mono', monospace; font-size:8pt;">{{ $pos['preis'] }}</td>
                                        <td style="text-align:right; font-family:'DejaVu Sans Mono', monospace; font-size:8pt; font-weight:bold;">{{ $pos['gesamt'] }}</td>
                                    @endif
                                    <td>{{ $pos['bemerkung'] }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
        @endif
    @endforeach

</body>
</html>
