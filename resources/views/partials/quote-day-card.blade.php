@php
    $fmt = $fmt ?? fn($v) => number_format((float)$v, 2, ',', '.');
    $dayItems = $dayItems ?? collect();
@endphp
<div class="bg-white border border-[var(--ui-border)] rounded-lg overflow-hidden">
    <div class="flex items-center justify-between px-3.5 py-2.5 border-b border-slate-100 bg-slate-50">
        <div class="flex items-center gap-2.5">
            <div class="w-1 h-5 rounded-sm flex-shrink-0" style="background: {{ $day->color ?? '#2563eb' }};"></div>
            <span class="text-[0.8rem] font-bold text-[var(--ui-secondary)]">{{ $day->label ?? $day->day_of_week }}</span>
            <span class="text-[0.62rem] text-[var(--ui-muted)]">
                {{ $day->datum?->format('d.m.Y') }}
                · {{ $dayItems->count() }} {{ $dayItems->count() === 1 ? 'Vorgang' : 'Vorgänge' }}
                · {{ $dayItems->sum('artikel') }} Artikel
            </span>
        </div>
        <div class="flex items-center gap-1.5">
            @if($dayItems->isNotEmpty())
                <button wire:click="convertAllQuoteItemsOfDayToOrder({{ $day->id }})"
                        wire:confirm="Alle {{ $dayItems->count() }} Vorgänge dieses Tages in Bestellungen überführen?"
                        class="flex items-center gap-1 px-2 py-1 rounded border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-700 text-[0.6rem] font-bold cursor-pointer"
                        title="Alle Vorgänge dieses Tages in Bestellungen überführen">
                    @svg('heroicon-o-arrows-right-left', 'w-3 h-3')
                    Alle in Bestellung
                </button>
            @endif
            <button wire:click="openItemCreate({{ $day->id }})"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white border-0 cursor-pointer text-[0.62rem] font-semibold">
                @svg('heroicon-o-plus', 'w-2.5 h-2.5')
                Neuer Vorgang
            </button>
        </div>
    </div>

    @if($dayItems->isEmpty())
        <div class="px-3.5 py-4 text-center text-[0.65rem] text-[var(--ui-muted)]">
            Noch keine Vorgänge — über „+ Neuer Vorgang" anlegen.
        </div>
    @else
        <div class="flex flex-wrap">
            @foreach($dayItems as $i => $v)
                <div class="p-3.5 flex-1 min-w-[220px] flex flex-col gap-2.5 {{ $i > 0 ? 'border-l border-slate-100' : '' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <div class="w-[26px] h-[26px] rounded-md flex items-center justify-center flex-shrink-0"
                                 style="background: {{ ($day->color ?? '#2563eb').'20' }};">
                                @svg(strtolower($v->typ) === 'speisen' ? 'heroicon-o-shopping-cart' : 'heroicon-o-squares-2x2', 'w-3.5 h-3.5', ['style' => 'color: '.($day->color ?? '#2563eb').';'])
                            </div>
                            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">{{ $v->typ }}</span>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <span class="text-[0.58rem] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 whitespace-nowrap">{{ $v->status }}</span>
                            <button wire:click="deleteItem({{ $v->id }})" wire:confirm="Vorgang «{{ $v->typ }}» mit allen Positionen wirklich löschen?"
                                    class="w-6 h-6 border border-red-200 rounded bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center flex-shrink-0">
                                @svg('heroicon-o-trash', 'w-2.5 h-2.5')
                            </button>
                        </div>
                    </div>

                    @if($v->positionen > 0)
                        <div class="grid grid-cols-3 gap-1.5">
                            <div class="bg-slate-50 rounded px-2 py-1.5 text-center">
                                <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $v->artikel }}</p>
                                <p class="text-[0.55rem] text-[var(--ui-muted)] mt-1 font-medium">Artikel</p>
                            </div>
                            <div class="bg-slate-50 rounded px-2 py-1.5 text-center">
                                <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $v->positionen }}</p>
                                <p class="text-[0.55rem] text-[var(--ui-muted)] mt-1 font-medium">Positionen</p>
                            </div>
                            <div class="bg-green-50 rounded px-2 py-1.5 text-center">
                                <p class="text-[0.85rem] font-bold text-green-700 m-0 leading-none font-mono">{{ $fmt($v->umsatz) }}</p>
                                <p class="text-[0.55rem] text-[var(--ui-muted)] mt-1 font-medium">Umsatz €</p>
                            </div>
                        </div>
                    @else
                        <div class="px-2.5 py-1.5 bg-slate-50 rounded-md border border-dashed border-slate-200 text-center">
                            <span class="text-[0.62rem] text-slate-400">Noch keine Artikel</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        @if($v->positionen > 0)
                            @php
                                $positions = $v->posList ?? collect();
                                $mwstSums = $positions
                                    ->groupBy(fn($p) => (string) ($p->mwst ?: '0%'))
                                    ->map(function ($group, $rate) {
                                        $net = (float) $group->sum('gesamt');
                                        $pct = (float) filter_var($rate, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                                        return ['rate' => $rate, 'pct' => $pct, 'tax' => round($net * ($pct / 100), 2)];
                                    })
                                    ->sortBy('pct')
                                    ->values();
                            @endphp
                            <div class="flex items-center gap-1.5 flex-wrap text-[0.58rem] text-[var(--ui-muted)]">
                                @foreach($mwstSums as $ms)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-50 border border-slate-200">
                                        MwSt {{ $ms['rate'] }}
                                        <span class="font-mono font-semibold text-slate-600">{{ $fmt($ms['tax']) }} €</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span></span>
                        @endif
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button wire:click="convertQuoteItemToOrder({{ $v->id }})"
                                    wire:confirm="Vorgang „{{ $v->typ }}" mit allen Positionen in Bestellung überführen?"
                                    class="text-[0.6rem] font-semibold text-amber-600 hover:text-amber-700 flex items-center gap-1 bg-transparent border-0 cursor-pointer"
                                    title="Alle Positionen als Bestell-Vorgang kopieren">
                                @svg('heroicon-o-arrows-right-left', 'w-2.5 h-2.5')
                                In Bestellung
                            </button>
                            <button wire:click="syncQuoteItemToOrder({{ $v->id }})"
                                    wire:confirm="Bestellung für „{{ $v->typ }}" mit aktuellen Angebots-Positionen aktualisieren?"
                                    class="text-[0.6rem] font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 bg-transparent border-0 cursor-pointer"
                                    title="Existierende Bestellung mit aktuellem Angebot synchronisieren">
                                @svg('heroicon-o-arrow-path', 'w-2.5 h-2.5')
                                Sync
                            </button>
                            <button wire:click="openPositions({{ $v->id }})"
                                    class="text-[0.62rem] font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 bg-transparent border-0 cursor-pointer">
                                Öffnen
                                @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
