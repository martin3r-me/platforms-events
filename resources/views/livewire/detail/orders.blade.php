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

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-[1rem] font-bold text-[var(--ui-secondary)]">Bestellungen · Übersicht</p>
            <p class="text-[0.65rem] text-[var(--ui-muted)]">Alle Vorgänge je Tag mit Artikel- und Einkaufszusammenfassung</p>
        </div>
    </div>

    {{-- Gesamt-KPIs --}}
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
                            @if($dayQuoteItems->isNotEmpty())
                                <select onchange="if(this.value) { @this.convertFromQuote(this.value); this.value = ''; }"
                                        class="border border-slate-200 rounded-md px-2 py-1 text-[0.62rem] bg-white cursor-pointer">
                                    <option value="">Aus Angebot übernehmen …</option>
                                    @foreach($dayQuoteItems as $qi)
                                        <option value="{{ $qi->id }}">{{ $qi->typ }} ({{ $qi->positionen }} Pos.)</option>
                                    @endforeach
                                </select>
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
                            Noch keine Vorgänge — über „+ Neuer Vorgang" oder „Aus Angebot übernehmen" anlegen.
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
                                                class="text-[0.62rem] font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 bg-transparent border-0 cursor-pointer">
                                            Öffnen
                                            @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
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

    {{-- Modal: Positionen --}}
    <x-ui-modal wire:model="showPositionsModal" size="xl" :hideFooter="true">
        <x-slot name="header">Bestellpositionen{{ $activeItem ? ' – ' . $activeItem->typ : '' }}</x-slot>
        <div class="space-y-4">
            @if($positions->isEmpty())
                <p class="text-xs text-[var(--ui-muted)] text-center py-4">Noch keine Bestellpositionen.</p>
            @else
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-[var(--ui-border)]">
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gruppe</th>
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Name</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Anz</th>
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gebinde</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">EK</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Gesamt</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($positions as $p)
                            <tr class="border-b border-[var(--ui-border)]/60">
                                <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $p->gruppe }}</td>
                                <td class="px-2 py-1 text-xs text-[var(--ui-secondary)]">{{ $p->name }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ $p->anz }}</td>
                                <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $p->gebinde }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ $fmt($p->ek) }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ $fmt($p->gesamt) }}</td>
                                <td class="px-2 py-1">
                                    <button wire:click="deletePosition({{ $p->id }})" wire:confirm="Löschen?" class="text-red-500 hover:text-red-700">
                                        @svg('heroicon-o-trash', 'w-3 h-3')
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="bg-slate-50 rounded-md p-3 space-y-2">
                <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Bestell-Position</p>
                <div class="grid grid-cols-8 gap-2">
                    <input wire:model="newPosition.gruppe" type="text" placeholder="Gruppe" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.name" type="text" placeholder="Name" class="col-span-2 border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.anz" type="text" placeholder="Anz" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs font-mono">
                    <input wire:model="newPosition.gebinde" type="text" placeholder="Gebinde" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.ek" type="number" step="0.01" placeholder="EK" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs font-mono">
                    <select wire:model="newPosition.mwst" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                        <option value="7%">7%</option>
                        <option value="19%">19%</option>
                    </select>
                    <input wire:model="newPosition.gesamt" type="number" step="0.01" placeholder="Gesamt" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs font-mono">
                </div>
                <input wire:model="newPosition.bemerkung" type="text" placeholder="Bemerkung" class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                <div class="flex justify-end">
                    <x-ui-button wire:click="addPosition" variant="primary" size="sm">Position hinzufügen</x-ui-button>
                </div>
            </div>

            <div class="flex justify-end pt-3 border-t border-[var(--ui-border)]">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closePositions">Schließen</x-ui-button>
            </div>
        </div>
    </x-ui-modal>
</div>
