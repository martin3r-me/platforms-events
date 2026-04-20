<div class="space-y-4">
    @if($days->isEmpty())
        <x-ui-panel>
            <div class="p-8 text-center text-xs text-[var(--ui-muted)]">
                Lege zuerst Event-Tage an, bevor du Bestellpositionen hinzufügen kannst.
            </div>
        </x-ui-panel>
    @else
        @foreach($days as $day)
            @php
                $dayItems = $items->get($day->id, collect());
                $dayQuoteItems = $quoteItems->get($day->id, collect());
            @endphp
            <x-ui-panel>
                <div class="p-4 flex items-center justify-between border-b border-[var(--ui-border)]">
                    <div>
                        <h4 class="text-sm font-bold text-[var(--ui-secondary)]">{{ $day->label }}</h4>
                        <p class="text-[0.62rem] text-[var(--ui-muted)] font-mono">{{ $day->datum?->format('d.m.Y') }} · {{ $day->day_of_week }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($dayQuoteItems->isNotEmpty())
                            <select onchange="if(this.value) { @this.convertFromQuote(this.value); this.value = ''; }"
                                    class="border border-[var(--ui-border)] rounded-md px-2 py-1 text-xs bg-white">
                                <option value="">Aus Angebot übernehmen…</option>
                                @foreach($dayQuoteItems as $qi)
                                    <option value="{{ $qi->id }}">{{ $qi->typ }} ({{ $qi->positionen }} Pos.)</option>
                                @endforeach
                            </select>
                        @endif
                        <x-ui-button variant="primary" size="sm" wire:click="openItemCreate({{ $day->id }})">
                            @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Position
                        </x-ui-button>
                    </div>
                </div>

                @if($dayItems->isEmpty())
                    <p class="p-4 text-xs text-[var(--ui-muted)] text-center">Keine Bestellpositionen für diesen Tag.</p>
                @else
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
                                <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Typ</th>
                                <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Status</th>
                                <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Lieferant</th>
                                <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Positionen</th>
                                <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Einkauf</th>
                                <th class="px-3 py-2 w-36"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dayItems as $it)
                                <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/40">
                                    <td class="px-3 py-2 text-xs font-semibold text-[var(--ui-secondary)]">{{ $it->typ }}</td>
                                    <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $it->status }}</td>
                                    <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $it->lieferant ?: '—' }}</td>
                                    <td class="px-3 py-2 text-xs font-mono text-right">{{ $it->positionen }}</td>
                                    <td class="px-3 py-2 text-xs font-mono text-right">{{ number_format((float) $it->einkauf, 2, ',', '.') }} €</td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-ui-button variant="secondary-outline" size="sm" wire:click="openPositions({{ $it->id }})">
                                                Positionen ({{ $it->positionen }})
                                            </x-ui-button>
                                            <x-ui-button variant="danger-outline" size="sm"
                                                         wire:click="deleteItem({{ $it->id }})"
                                                         wire:confirm="Position löschen?">
                                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                            </x-ui-button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-ui-panel>
        @endforeach
    @endif

    {{-- Modal: Item anlegen --}}
    <x-ui-modal wire:model="showItemModal" size="md" :hideFooter="true">
        <x-slot name="header">Neue Bestell-Position</x-slot>
        <form wire:submit.prevent="saveItem" class="space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                    <input wire:model="itemTyp" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Status</label>
                    <input wire:model="itemStatus" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Lieferant</label>
                    <input wire:model="itemLieferant" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
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
        <x-slot name="header">Bestellpositionen {{ $activeItem ? ' – ' . $activeItem->typ : '' }}</x-slot>
        <div class="space-y-4">
            @if($positions->isEmpty())
                <p class="text-xs text-[var(--ui-muted)] text-center py-4">Noch keine Bestellpositionen.</p>
            @else
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
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
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ number_format((float) $p->ek, 2, ',', '.') }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ number_format((float) $p->gesamt, 2, ',', '.') }}</td>
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

            <div class="bg-[var(--ui-muted-5)] rounded-md p-3 space-y-2">
                <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Bestell-Position</p>
                <div class="grid grid-cols-8 gap-2">
                    <input wire:model="newPosition.gruppe" type="text" placeholder="Gruppe" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.name" type="text" placeholder="Name" class="col-span-2 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.anz" type="text" placeholder="Anz" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                    <input wire:model="newPosition.gebinde" type="text" placeholder="Gebinde" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.ek" type="number" step="0.01" placeholder="EK" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                    <select wire:model="newPosition.mwst" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                        <option value="7%">7%</option>
                        <option value="19%">19%</option>
                    </select>
                    <input wire:model="newPosition.gesamt" type="number" step="0.01" placeholder="Gesamt" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                </div>
                <input wire:model="newPosition.bemerkung" type="text" placeholder="Bemerkung" class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
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
