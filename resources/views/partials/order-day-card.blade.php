@php
    $fmt = $fmt ?? fn($v) => number_format((float)$v, 2, ',', '.');
    $dayItems = $dayItems ?? collect();
    $dayQuoteItems = $dayQuoteItems ?? collect();
@endphp
<div class="bg-white border border-[var(--ui-border)] rounded-lg overflow-hidden">
    <div class="flex items-center justify-between px-3.5 py-2.5 border-b border-slate-100 bg-slate-50 flex-wrap gap-2">
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
            <button wire:click="openItemCreate({{ $day->id }})"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white border-0 cursor-pointer text-[0.62rem] font-semibold">
                @svg('heroicon-o-plus', 'w-2.5 h-2.5')
                Neuer Vorgang
            </button>
        </div>
    </div>

    @if($dayItems->isEmpty())
        <div class="px-3.5 py-4 text-center text-[0.65rem] text-[var(--ui-muted)]">
            Noch keine Vorgänge — auf der Angebots-Seite „In Bestellung" klicken oder hier „+ Neuer Vorgang" anlegen.
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
                            <button wire:click="deleteItem({{ $v->id }})" wire:confirm="Vorgang «{{ $v->typ }}» mit allen Positionen löschen?"
                                    class="w-6 h-6 border border-red-200 rounded bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center">
                                @svg('heroicon-o-trash', 'w-2.5 h-2.5')
                            </button>
                        </div>
                    </div>

                    @if(!empty($v->lieferant))
                        <div class="text-[0.6rem] text-[var(--ui-muted)]">
                            Lieferant: <span class="font-semibold text-slate-600">{{ $v->lieferant }}</span>
                        </div>
                    @endif

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
                            <div class="bg-red-50 rounded px-2 py-1.5 text-center">
                                <p class="text-[0.85rem] font-bold text-red-600 m-0 leading-none font-mono">{{ $fmt($v->einkauf) }}</p>
                                <p class="text-[0.55rem] text-[var(--ui-muted)] mt-1 font-medium">Einkauf €</p>
                            </div>
                        </div>
                    @else
                        <div class="px-2.5 py-1.5 bg-slate-50 rounded-md border border-dashed border-slate-200 text-center">
                            <span class="text-[0.62rem] text-slate-400">Noch keine Positionen</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-end">
                        <button wire:click="openPositions({{ $v->id }})"
                                class="text-[0.62rem] font-semibold text-orange-600 hover:text-orange-700 flex items-center gap-1 bg-transparent border-0 cursor-pointer">
                            Öffnen
                            @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
