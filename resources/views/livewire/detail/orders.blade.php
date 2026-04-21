<div class="space-y-4 max-w-[960px]">
    @php
        $fmt = fn($v) => number_format((float)$v, 2, ',', '.');

        $totalVorgaenge = 0;
        $totalArtikel = 0;
        $totalEinkauf = 0.0;
        foreach ($days as $day) {
            $dayItems = $items->get($day->id, collect());
            $totalVorgaenge += $dayItems->count();
            $totalArtikel += $dayItems->sum('artikel');
            $totalEinkauf += (float) $dayItems->sum('einkauf');
        }
    @endphp

    @if($view === 'editor' && $activeItem)
        {{-- ================= Einzel-Vorgang (Editor als Seite) ================= --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-1 h-6 rounded-sm flex-shrink-0" style="background: {{ $activeDay?->color ?? '#ea580c' }};"></div>
                <div class="min-w-0">
                    <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 truncate">Bestellung · {{ $activeItem->typ }}</p>
                    <p class="text-[0.65rem] text-[var(--ui-muted)] m-0">
                        @if($activeDay)
                            {{ $activeDay->label ?? $activeDay->day_of_week }} · {{ $activeDay->datum?->format('d.m.Y') }}
                        @endif
                        · <span class="font-mono">{{ $activeItem->positionen }} Pos · {{ $fmt($activeItem->einkauf) }} €</span>
                    </p>
                </div>
            </div>
            <button wire:click="closePositions"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.68rem] font-semibold">
                @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                Zurück
            </button>
        </div>

        @include('events::partials.order-positions-editor')

    @elseif($view === 'day' && $activeDay)
        {{-- ================= Tages-Übersicht ================= --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-1 h-6 rounded-sm flex-shrink-0" style="background: {{ $activeDay->color ?? '#ea580c' }};"></div>
                <div class="min-w-0">
                    <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 truncate">Bestellung · {{ $activeDay->label ?? $activeDay->day_of_week }}</p>
                    <p class="text-[0.65rem] text-[var(--ui-muted)] m-0">
                        {{ $activeDay->datum?->format('d.m.Y') }} · {{ $activeDay->day_of_week }}
                        @php $dayItems = $items->get($activeDay->id, collect()); @endphp
                        · <span class="font-mono">{{ $dayItems->count() }} Vorgänge · {{ $fmt($dayItems->sum('einkauf')) }} €</span>
                    </p>
                </div>
            </div>
            <button wire:click="backToOverview"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.68rem] font-semibold">
                @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                Übersicht
            </button>
        </div>

        @php
            $dayItems = $items->get($activeDay->id, collect());
            $dayQuoteItems = $quoteItems->get($activeDay->id, collect());
        @endphp
        @include('events::partials.order-day-card', [
            'day' => $activeDay,
            'dayItems' => $dayItems,
            'dayQuoteItems' => $dayQuoteItems,
            'fmt' => $fmt,
        ])

    @elseif($view === 'articles')
        {{-- ================= Alle Artikel (flach) ================= --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0">Alle Bestell-Artikel</p>
                <p class="text-[0.65rem] text-[var(--ui-muted)] m-0">{{ $allPositions->count() }} Positionen über alle Tage</p>
            </div>
            <button wire:click="backToOverview"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.68rem] font-semibold">
                @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                Übersicht
            </button>
        </div>

        <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden">
            @if($allPositions->isEmpty())
                <div class="p-10 text-center">
                    @svg('heroicon-o-squares-2x2', 'w-10 h-10 text-slate-300 mx-auto mb-3')
                    <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">Noch keine Artikel angelegt.</p>
                </div>
            @else
                <table class="w-full border-collapse text-[0.65rem]">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left py-1.5 px-2.5 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Tag</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Vorgang</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gruppe</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Name</th>
                            <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Anz</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gebinde</th>
                            <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">EK</th>
                            <th class="text-center py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">MwSt</th>
                            <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $allItemsById = $items->flatten()->keyBy('id');
                            $daysById     = $days->keyBy('id');
                        @endphp
                        @foreach($allPositions as $p)
                            @php
                                $oi = $allItemsById->get($p->order_item_id);
                                $d  = $oi ? $daysById->get($oi->event_day_id) : null;
                            @endphp
                            <tr class="border-b border-slate-100 hover:bg-slate-50/60">
                                <td class="py-1.5 px-2.5">
                                    @if($d)
                                        <button wire:click="openDay({{ $d->id }})"
                                                class="text-[0.62rem] font-semibold text-[var(--ui-secondary)] hover:text-orange-600 bg-transparent border-0 cursor-pointer p-0 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $d->color ?? '#ea580c' }};"></span>
                                            {{ $d->datum?->format('d.m.') }}
                                        </button>
                                    @else —
                                    @endif
                                </td>
                                <td class="py-1.5 px-2">
                                    @if($oi)
                                        <button wire:click="openPositions({{ $oi->id }})"
                                                class="text-[0.62rem] font-semibold text-orange-600 hover:text-orange-700 bg-transparent border-0 cursor-pointer p-0">
                                            {{ $oi->typ }}
                                        </button>
                                    @else — @endif
                                </td>
                                <td class="py-1.5 px-2 text-slate-600">{{ $p->gruppe }}</td>
                                <td class="py-1.5 px-2 text-[var(--ui-secondary)]">{{ $p->name }}</td>
                                <td class="py-1.5 px-2 text-right font-mono">{{ $p->anz }}</td>
                                <td class="py-1.5 px-2 text-slate-500">{{ $p->gebinde }}</td>
                                <td class="py-1.5 px-2 text-right font-mono">{{ $fmt($p->ek) }}</td>
                                <td class="py-1.5 px-2 text-center text-slate-500">{{ $p->mwst }}</td>
                                <td class="py-1.5 px-2 text-right font-mono font-semibold">{{ $fmt($p->gesamt) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    @else
        {{-- ================= Gesamt-Übersicht ================= --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-[1rem] font-bold text-[var(--ui-secondary)]">Bestellungen · Übersicht</p>
                <p class="text-[0.65rem] text-[var(--ui-muted)]">Alle Vorgänge je Tag mit Artikel- und Einkaufszusammenfassung</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-4">
            <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
                <p class="text-[1.3rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $days->count() }}</p>
                <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Veranstaltungstage</p>
            </div>
            <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
                <p class="text-[1.3rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $totalVorgaenge }}</p>
                <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Vorgänge gesamt</p>
            </div>
            <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
                <p class="text-[1.3rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $totalArtikel }}</p>
                <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Artikel gesamt</p>
            </div>
            <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
                <p class="text-[0.95rem] font-bold text-red-600 m-0 leading-none font-mono">{{ $fmt($totalEinkauf) }} €</p>
                <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Einkauf gesamt</p>
            </div>
        </div>

        @if($days->isEmpty())
            <div class="bg-white border border-[var(--ui-border)] rounded-xl p-10 text-center">
                @svg('heroicon-o-calendar-days', 'w-10 h-10 text-slate-300 mx-auto mb-3')
                <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">Lege zuerst Event-Tage an, bevor du Bestellpositionen hinzufügen kannst.</p>
            </div>
        @else
            <div class="flex flex-col gap-2.5">
                @foreach($days as $day)
                    @php
                        $dayItems = $items->get($day->id, collect());
                        $dayQuoteItems = $quoteItems->get($day->id, collect());
                    @endphp
                    @include('events::partials.order-day-card', [
                        'day' => $day,
                        'dayItems' => $dayItems,
                        'dayQuoteItems' => $dayQuoteItems,
                        'fmt' => $fmt,
                    ])
                @endforeach
            </div>
        @endif
    @endif

    {{-- Modal: Item anlegen --}}
    <x-ui-modal wire:model="showItemModal" size="md" :hideFooter="true">
        <x-slot name="header">Neuer Bestell-Vorgang</x-slot>
        <form wire:submit.prevent="saveItem" class="space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                    <input wire:model="itemTyp" type="text" placeholder="Speisen / Getränke / Equipment / …"
                           class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Status</label>
                    <input wire:model="itemStatus" type="text"
                           class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Lieferant</label>
                    <input wire:model="itemLieferant" type="text"
                           class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeItemModal">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Anlegen</x-ui-button>
            </div>
        </form>
    </x-ui-modal>
</div>
