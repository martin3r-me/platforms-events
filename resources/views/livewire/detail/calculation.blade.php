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
