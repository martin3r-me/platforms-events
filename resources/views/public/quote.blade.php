<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Angebot {{ $event->event_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DM Sans', -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 880px; margin: 20px auto; padding: 0 16px; }
        .card { background: white; border-radius: 8px; padding: 28px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        h1 { font-size: 1.4rem; margin: 0 0 8px 0; }
        .meta { color: #64748b; font-size: 0.85rem; }
        h2 { font-size: 1rem; margin: 20px 0 8px 0; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        th { text-align: left; background: #f8fafc; padding: 8px; border-bottom: 1px solid #cbd5e1; font-size: 0.68rem; text-transform: uppercase; color: #64748b; }
        td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; font-family: 'DM Mono', monospace; }
        .status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; border: none; margin-right: 8px; }
        .btn-primary { background: #16a34a; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .totals { background: #f8fafc; padding: 16px; border-radius: 8px; margin-top: 20px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .brutto { border-top: 1px solid #cbd5e1; padding-top: 8px; margin-top: 6px; font-weight: 700; font-size: 1rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Angebot {{ $event->event_number }}</h1>
            <div class="meta">
                {{ $event->name }}
                @if($event->customer) · {{ $event->customer }} @endif<br>
                Version {{ $quote->version }} ·
                @php
                    $statusMap = ['draft' => ['#94a3b8', 'Entwurf'], 'sent' => ['#2563eb', 'Gesendet'], 'accepted' => ['#16a34a', 'Angenommen'], 'rejected' => ['#ef4444', 'Abgelehnt']];
                    $st = $statusMap[$quote->status] ?? ['#94a3b8', $quote->status];
                @endphp
                <span class="status" style="background: {{ $st[0] }}22; color: {{ $st[0] }};">{{ $st[1] }}</span>
            </div>

            @if(session('status'))
                <div style="margin-top: 16px; padding: 12px; background: #dcfce7; border: 1px solid #86efac; border-radius: 6px; color: #166534; font-size: 0.85rem;">
                    {{ session('status') }}
                </div>
            @endif

            @php
                $totalNetto = 0; $totalMwst7 = 0; $totalMwst19 = 0;
            @endphp

            @foreach($event->days->sortBy('sort_order') as $day)
                @php $dayItems = $items->get($day->id, collect()); @endphp
                @if($dayItems->isNotEmpty())
                    <h2>{{ $day->label }} · {{ $day->datum?->format('d.m.Y') }}</h2>
                    @foreach($dayItems as $item)
                        <p style="font-weight: 600; margin: 12px 0 4px 0;">{{ $item->typ }}</p>
                        @if($item->posList->isNotEmpty())
                            <table>
                                <thead>
                                    <tr>
                                        <th>Bezeichnung</th>
                                        <th class="num">Anz</th>
                                        <th>Gebinde</th>
                                        <th class="num">Einzel</th>
                                        <th class="num">Gesamt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($item->posList as $pos)
                                        @php
                                            $g = (float) $pos->gesamt;
                                            $rate = (int) $pos->mwst;
                                            if ($rate === 7) { $totalMwst7 += $g; } else { $totalMwst19 += $g; }
                                            $totalNetto += $g;
                                        @endphp
                                        <tr>
                                            <td>{{ $pos->name }}</td>
                                            <td class="num">{{ $pos->anz }}</td>
                                            <td>{{ $pos->gebinde }}</td>
                                            <td class="num">{{ number_format((float) $pos->preis, 2, ',', '.') }} €</td>
                                            <td class="num">{{ number_format($g, 2, ',', '.') }} €</td>
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
                    <span>Netto</span>
                    <span class="num">{{ number_format($totalNetto, 2, ',', '.') }} €</span>
                </div>
                @if($totalMwst7 > 0)
                    <div class="row">
                        <span>MwSt 7%</span>
                        <span class="num">{{ number_format($totalMwst7 * 0.07, 2, ',', '.') }} €</span>
                    </div>
                @endif
                @if($totalMwst19 > 0)
                    <div class="row">
                        <span>MwSt 19%</span>
                        <span class="num">{{ number_format($totalMwst19 * 0.19, 2, ',', '.') }} €</span>
                    </div>
                @endif
                <div class="row brutto">
                    <span>Brutto</span>
                    <span class="num">{{ number_format($totalNetto + $totalMwst7 * 0.07 + $totalMwst19 * 0.19, 2, ',', '.') }} €</span>
                </div>
            </div>

            @if($quote->status === 'sent')
                <form method="POST" action="{{ route('events.public.quote.respond', ['token' => $quote->token]) }}" style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                    @csrf
                    <label style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 6px; font-weight: 600;">Anmerkung (optional)</label>
                    <textarea name="note" rows="3" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 0.85rem;"></textarea>
                    <div style="margin-top: 12px;">
                        <button type="submit" name="action" value="accept" class="btn btn-primary">Angebot annehmen</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">Angebot ablehnen</button>
                    </div>
                </form>
            @elseif($quote->status === 'accepted' || $quote->status === 'rejected')
                <div style="margin-top: 24px; padding: 16px; background: {{ $quote->status === 'accepted' ? '#dcfce7' : '#fef2f2' }}; border-radius: 6px; font-size: 0.85rem; color: {{ $quote->status === 'accepted' ? '#166534' : '#991b1b' }};">
                    Sie haben das Angebot am {{ $quote->responded_at?->format('d.m.Y H:i') }} {{ $quote->status === 'accepted' ? 'angenommen' : 'abgelehnt' }}.
                    @if($quote->response_note)
                        <br><em>Ihre Anmerkung:</em> {{ $quote->response_note }}
                    @endif
                </div>
            @endif
        </div>

        <p style="text-align: center; font-size: 0.7rem; color: #94a3b8;">Angebot-Token: {{ $quote->token }}</p>
    </div>
</body>
</html>
