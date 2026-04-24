<div class="space-y-4 max-w-[1100px]">
    @php
        $fmt = fn($v) => number_format((float)$v, 2, ',', '.');
        $fmtPct = fn($v) => number_format((float)$v, 1, ',', '');
        $dbColorHex = fn($pct) => $pct >= 30 ? '#16a34a' : ($pct >= 15 ? '#d97706' : '#dc2626');
        $dbColorCls = fn($pct) => $pct >= 30 ? 'text-green-600' : ($pct >= 15 ? 'text-amber-600' : 'text-red-600');
    @endphp

    {{-- Header --}}
    <div class="flex items-start justify-between mb-4 flex-wrap gap-2.5">
        <div>
            <p class="text-[1rem] font-bold text-[var(--ui-secondary)]">Kalkulation · Deckungsbeitrag</p>
            <p class="text-[0.65rem] text-[var(--ui-muted)]">Umsatz, Einkauf und DB-Analyse je Kostenbereich</p>
        </div>
    </div>

    {{-- Gesamt-Banner --}}
    <div class="rounded-xl px-6 py-5 mb-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-white"
         style="background: linear-gradient(135deg, #1e293b, #334155);">
        <div>
            <p class="text-[0.58rem] font-semibold text-slate-400 uppercase tracking-wider mb-1">Umsatz gesamt</p>
            <p class="text-[1.1rem] font-bold text-white m-0 font-mono">{{ $fmt($gesamtUmsatz) }} €</p>
        </div>
        <div>
            <p class="text-[0.58rem] font-semibold text-slate-400 uppercase tracking-wider mb-1">EK gesamt</p>
            <p class="text-[1.1rem] font-bold text-red-400 m-0 font-mono">{{ $fmt($gesamtEk) }} €</p>
        </div>
        <div>
            <p class="text-[0.58rem] font-semibold text-slate-400 uppercase tracking-wider mb-1">DB gesamt</p>
            <p class="text-[1.1rem] font-bold m-0 font-mono" style="color: {{ $gesamtDb >= 0 ? '#4ade80' : '#f87171' }};">{{ $fmt($gesamtDb) }} €</p>
        </div>
        <div>
            <p class="text-[0.58rem] font-semibold text-slate-400 uppercase tracking-wider mb-1">DB-Quote</p>
            <p class="text-[1.1rem] font-bold m-0 font-mono" style="color: {{ $dbColorHex($gesamtDbPct) }};">{{ $fmtPct($gesamtDbPct) }} %</p>
            <div class="mt-2 bg-slate-600 rounded-full h-1 overflow-hidden">
                <div class="h-full rounded-full transition-all"
                     style="width: {{ min(max($gesamtDbPct, 0), 100) }}%; background: {{ $dbColorHex($gesamtDbPct) }};"></div>
            </div>
        </div>
    </div>

    {{-- Pauschal-Anwendungen (regelbasiert) --}}
    @if(session('calcError'))
        <div class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-[0.7rem] text-red-700 flex items-center gap-1.5">
            @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5') {{ session('calcError') }}
        </div>
    @endif

    @if(!$flatRateApplications->isEmpty() || count($event->days) > 0)
        <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-[var(--ui-border)]">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-calculator', 'w-4 h-4 text-emerald-600')
                    <span class="text-[0.78rem] font-bold text-[var(--ui-secondary)]">Angewendete Pauschalen</span>
                    <span class="text-[0.6rem] text-[var(--ui-muted)] bg-white px-1.5 py-0.5 rounded-full border border-[var(--ui-border)]">{{ $flatRateApplications->count() }}</span>
                </div>
                <a href="{{ route('events.settings', ['tab' => 'flat_rates']) }}"
                   class="flex items-center gap-1 text-[0.6rem] font-semibold text-[var(--ui-muted)] hover:text-[var(--ui-primary)]">
                    @svg('heroicon-o-cog-6-tooth', 'w-3 h-3') Regeln verwalten
                </a>
            </div>
            @if($flatRateApplications->isEmpty())
                <div class="px-4 py-6 text-[0.7rem] text-[var(--ui-muted)] text-center italic">
                    Noch keine Pauschalen auf diesem Event angewendet. In Angebots-Vorgängen über den Button „Pauschale“ starten.
                </div>
            @else
                <ul class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($flatRateApplications as $app)
                        @php
                            $pos       = $app->quotePosition;
                            $item      = $app->quoteItem;
                            $isRemoved = !$pos;
                            $current   = $pos ? (float) $pos->preis : null;
                            $computed  = (float) $app->result_value;
                            $isOverridden = $pos && abs($current - $computed) > 0.01;
                            $diff      = $current !== null ? $current - $computed : 0;
                            $dayLabel  = $item?->eventDay?->datum?->format('d.m.Y') ?? '—';
                        @endphp
                        <li class="px-4 py-3">
                            <div class="flex items-start gap-3 flex-wrap">
                                <div class="flex-1 min-w-[240px]">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[0.76rem] font-bold text-[var(--ui-secondary)]">{{ $app->rule?->name ?? '(gelöschte Regel)' }}</span>
                                        <span class="text-[0.55rem] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-blue-50 text-blue-700">{{ $item?->typ }}</span>
                                        <span class="text-[0.58rem] text-[var(--ui-muted)] font-mono">{{ $dayLabel }}</span>
                                        @if($isRemoved)
                                            <span class="text-[0.55rem] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-700">Position gelöscht</span>
                                        @elseif($isOverridden)
                                            <span class="text-[0.55rem] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700" title="Preis manuell angepasst">manuell</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex items-center gap-3 text-[0.68rem] font-mono">
                                        <span class="text-[var(--ui-muted)]">berechnet: <span class="text-[var(--ui-secondary)] font-semibold">{{ $fmt($computed) }} €</span></span>
                                        @if($pos)
                                            <span class="text-[var(--ui-muted)]">aktuell: <span class="font-semibold {{ $isOverridden ? 'text-amber-700' : 'text-[var(--ui-secondary)]' }}">{{ $fmt($current) }} €</span></span>
                                            @if($isOverridden)
                                                <span class="font-semibold {{ $diff >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                                    {{ $diff >= 0 ? '+' : '' }}{{ $fmt($diff) }} €
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                    <button wire:click="toggleApp({{ $app->id }})" type="button"
                                            class="mt-1 text-[0.58rem] font-semibold text-[var(--ui-muted)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 transition-transform {{ ($openApp[$app->id] ?? false) ? 'rotate-90' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        Kontext + Formel
                                    </button>
                                    @if($openApp[$app->id] ?? false)
                                        <div class="mt-1 p-2 bg-slate-50 border border-[var(--ui-border)] rounded-md space-y-1">
                                            <div class="text-[0.58rem]">
                                                <span class="font-bold text-[var(--ui-muted)]">Formel:</span>
                                                <code class="font-mono text-[0.6rem] break-all">{{ $app->rule?->formula ?? '—' }}</code>
                                            </div>
                                            <div class="text-[0.58rem]">
                                                <span class="font-bold text-[var(--ui-muted)]">Eingesetzte Variablen zum Anwendungszeitpunkt:</span>
                                                <pre class="mt-0.5 text-[0.55rem] font-mono whitespace-pre-wrap break-all text-[var(--ui-muted)]">{{ json_encode($app->input_snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                            <div class="text-[0.55rem] text-[var(--ui-muted)]">Angewendet am {{ $app->created_at->format('d.m.Y H:i') }}</div>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if($isOverridden)
                                        <button wire:click="reapplyFlatRate({{ $app->id }})"
                                                wire:confirm="Aktueller Wert ({{ $fmt($current) }} €) wurde manuell geaendert. Durch Neu-Berechnung wird er mit {{ $fmt($computed) }} € ueberschrieben. Wirklich?"
                                                class="flex items-center gap-1 px-2 py-1 rounded border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-700 text-[0.6rem] font-bold cursor-pointer">
                                            @svg('heroicon-o-arrow-path', 'w-3 h-3') Neu berechnen
                                        </button>
                                    @else
                                        <button wire:click="reapplyFlatRate({{ $app->id }})"
                                                class="flex items-center gap-1 px-2 py-1 rounded border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[0.6rem] font-bold cursor-pointer">
                                            @svg('heroicon-o-arrow-path', 'w-3 h-3') Neu berechnen
                                        </button>
                                    @endif
                                    <button wire:click="removeFlatRate({{ $app->id }})"
                                            wire:confirm="Pauschale-Position „{{ $app->rule?->output_name ?? '' }}“ aus Vorgang „{{ $item?->typ }}“ entfernen?"
                                            class="flex items-center gap-1 px-2 py-1 rounded border border-red-200 bg-white hover:bg-red-50 text-red-600 text-[0.6rem] font-bold cursor-pointer">
                                        @svg('heroicon-o-trash', 'w-3 h-3') Entfernen
                                    </button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if(empty($kategorien))
        <div class="bg-white border border-[var(--ui-border)] rounded-lg p-8 text-center">
            <p class="text-[0.72rem] text-[var(--ui-muted)] mb-0">
                Noch keine Kalkulationsdaten vorhanden. Lege Angebots- und Bestell-Positionen an, damit hier Umsatz und Einkauf je Kostenbereich erscheinen.
            </p>
        </div>
    @else
        {{-- Category Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-2.5 mb-5">
            @foreach($kategorien as $kat)
                <button type="button" wire:click="toggleKat('{{ $kat['key'] }}')"
                        class="rounded-lg p-3.5 cursor-pointer transition text-left"
                        style="{{ $kat['open'] ? 'background: '.$kat['bg'].'; border: 1.5px solid '.$kat['color'].'55;' : 'background: white; border: 1.5px solid #e8edf5;' }}">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-[0.68rem] font-bold" style="color: {{ $kat['color'] }};">{{ $kat['label'] }}</span>
                        <div class="w-[7px] h-[7px] rounded-full" style="background: {{ $kat['color'] }};"></div>
                    </div>
                    <p class="text-[0.58rem] text-[var(--ui-muted)] uppercase tracking-wider m-0 mb-0.5">Umsatz</p>
                    <p class="text-[0.72rem] font-semibold text-[var(--ui-secondary)] m-0 mb-2 font-mono">{{ $fmt($kat['umsatz']) }} €</p>
                    <p class="text-[0.58rem] text-[var(--ui-muted)] uppercase tracking-wider m-0 mb-0.5">DB</p>
                    <p class="text-[0.72rem] font-bold m-0 mb-2 font-mono" style="color: {{ $dbColorHex($kat['dbPct']) }};">{{ $fmt($kat['db']) }} €</p>
                    <div class="bg-slate-200 rounded-full h-[3px] overflow-hidden">
                        <div class="h-full rounded-full transition-all"
                             style="width: {{ min(max($kat['dbPct'], 0), 100) }}%; background: {{ $kat['color'] }};"></div>
                    </div>
                    <p class="text-[0.6rem] font-bold mt-1 text-right font-mono" style="color: {{ $dbColorHex($kat['dbPct']) }};">{{ $fmtPct($kat['dbPct']) }} %</p>
                </button>
            @endforeach
        </div>

        {{-- Category Accordion Sections --}}
        <div class="flex flex-col gap-2">
            @foreach($kategorien as $kat)
                <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden">
                    <div wire:click="toggleKat('{{ $kat['key'] }}')"
                         class="flex items-center justify-between px-4 py-2.5 cursor-pointer transition"
                         style="{{ $kat['open'] ? 'background: '.$kat['bg'].'; border-left: 3px solid '.$kat['color'].';' : 'border-left: 3px solid #e2e8f0;' }}">
                        <div class="flex items-center gap-3.5 flex-wrap">
                            <span class="text-[0.78rem] font-bold text-[var(--ui-secondary)]">{{ $kat['label'] }}</span>
                            <span class="text-[0.62rem] text-slate-500 font-mono">Umsatz: {{ $fmt($kat['umsatz']) }} €</span>
                            <span class="text-[0.62rem] text-[var(--ui-muted)] font-mono">EK: {{ $fmt($kat['ek']) }} €</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[0.72rem] font-bold font-mono" style="color: {{ $dbColorHex($kat['dbPct']) }};">
                                DB: {{ $fmt($kat['db']) }} € · {{ $fmtPct($kat['dbPct']) }} %
                            </span>
                            <svg class="w-3.5 h-3.5 text-[var(--ui-muted)] transition-transform {{ $kat['open'] ? 'rotate-180' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    @if($kat['open'])
                        <table class="w-full border-collapse text-[0.65rem]">
                            <thead>
                                <tr class="bg-slate-50 border-t border-slate-100 border-b border-[var(--ui-border)]">
                                    <th class="text-left py-1.5 px-3.5 text-[0.57rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Tag</th>
                                    <th class="text-left py-1.5 px-2 text-[0.57rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Datum</th>
                                    <th class="text-right py-1.5 px-2 text-[0.57rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider whitespace-nowrap">Artikel · Pos</th>
                                    <th class="text-right py-1.5 px-2 text-[0.57rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider whitespace-nowrap">Umsatz €</th>
                                    <th class="text-right py-1.5 px-2 text-[0.57rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider whitespace-nowrap">EK €</th>
                                    <th class="text-right py-1.5 px-2 text-[0.57rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider whitespace-nowrap">DB €</th>
                                    <th class="text-right py-1.5 px-2 text-[0.57rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider whitespace-nowrap">DB %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($kat['rows'] as $row)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 px-3.5 font-medium text-slate-700 whitespace-nowrap">{{ $row['day_label'] }}</td>
                                        <td class="py-2 px-2 font-mono text-slate-600">{{ $row['datum'] }}</td>
                                        <td class="py-2 px-2 text-right font-mono text-slate-700">{{ $row['articles'] }} · {{ $row['positionen'] }}</td>
                                        <td class="py-2 px-2 text-right font-mono text-slate-700">{{ $fmt($row['revenue']) }}</td>
                                        <td class="py-2 px-2 text-right font-mono text-slate-500">{{ $fmt($row['cost']) }}</td>
                                        <td class="py-2 px-2 text-right font-mono font-semibold {{ $row['margin'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $fmt($row['margin']) }}</td>
                                        <td class="py-2 px-2 text-right font-mono font-bold {{ $dbColorCls($row['marginPct']) }}">{{ $fmtPct($row['marginPct']) }} %</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-3 px-4 text-center text-[var(--ui-muted)] italic">Keine Tage mit Umsatz oder Einkauf für diesen Bereich.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-50 border-t-2 border-slate-200">
                                    <td colspan="3" class="py-2 px-3.5 text-[0.62rem] font-bold text-slate-700">{{ $kat['label'] }} gesamt</td>
                                    <td class="py-2 px-2 text-right font-mono font-bold text-slate-700">{{ $fmt($kat['umsatz']) }}</td>
                                    <td class="py-2 px-2 text-right font-mono font-bold text-red-600">{{ $fmt($kat['ek']) }}</td>
                                    <td class="py-2 px-2 text-right font-mono font-bold {{ $kat['db'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $fmt($kat['db']) }}</td>
                                    <td class="py-2 px-2 text-right font-mono font-bold {{ $dbColorCls($kat['dbPct']) }}">{{ $fmtPct($kat['dbPct']) }} %</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="text-[0.6rem] text-[var(--ui-muted)] mt-4 text-center leading-relaxed">
            Umsatz wird aus Angebotspositionen je Event-Tag gezogen, EK aus den zugehörigen Bestellpositionen.<br>
            Artikelweise EK-Overrides (Lagerware, Eigenpersonal, Sponsor) folgen in einer späteren Etappe.
        </p>
    @endif
</div>
