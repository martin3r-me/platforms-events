<div class="space-y-4">
    @php
        $quoteStatusBadge = [
            'draft'    => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'label' => 'Entwurf'],
            'sent'     => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700', 'label' => 'Gesendet'],
            'accepted' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'Angenommen'],
            'rejected' => ['bg' => 'bg-red-50',    'text' => 'text-red-700', 'label' => 'Abgelehnt'],
        ];
    @endphp

    {{-- Versionen & Header --}}
    <x-ui-panel>
        <div class="p-4 flex justify-between items-start gap-4 flex-wrap">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] mb-2">Angebote</h3>
                @if($quotes->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)]">Kein Angebot angelegt.</p>
                @else
                    <div class="flex gap-2 flex-wrap">
                        @foreach($quotes as $q)
                            @php $sb = $quoteStatusBadge[$q->status] ?? $quoteStatusBadge['draft']; @endphp
                            <button wire:click="selectQuote({{ $q->id }})"
                                    class="px-3 py-1.5 rounded-md border text-xs flex items-center gap-2 transition-colors
                                           {{ $activeQuote && $activeQuote->id === $q->id
                                              ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]'
                                              : 'border-[var(--ui-border)] bg-white text-[var(--ui-muted)] hover:border-[var(--ui-primary)]/40' }}">
                                <span class="font-mono font-bold">v{{ $q->version }}</span>
                                <span class="text-[0.62rem] font-bold px-1.5 py-0.5 rounded-full {{ $sb['bg'] }} {{ $sb['text'] }}">{{ $sb['label'] }}</span>
                                @if($q->is_current)
                                    <span class="text-[0.6rem] font-bold text-[var(--ui-primary)]">·aktuell</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <x-ui-button variant="primary" size="sm" wire:click="createQuote">
                    <span class="flex items-center gap-1.5">@svg('heroicon-o-plus', 'w-3.5 h-3.5') Neues Angebot</span>
                </x-ui-button>
                @if($activeQuote)
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="newVersion"
                                 wire:confirm="Neue Version anlegen? Aktuelles Angebot wird als History markiert.">
                        <span class="flex items-center gap-1.5">@svg('heroicon-o-document-duplicate', 'w-3.5 h-3.5') Neue Version</span>
                    </x-ui-button>
                @endif
            </div>
        </div>

        @if($activeQuote)
            <div class="p-4 border-t border-[var(--ui-border)] flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-mono font-bold text-[var(--ui-primary)]">v{{ $activeQuote->version }}</span>
                    @php $sb = $quoteStatusBadge[$activeQuote->status] ?? $quoteStatusBadge['draft']; @endphp
                    <select wire:change="setQuoteStatus($event.target.value)"
                            class="border border-[var(--ui-border)] rounded-md px-2 py-1 text-xs bg-white">
                        @foreach($quoteStatusBadge as $key => $meta)
                            <option value="{{ $key }}" @if($activeQuote->status === $key) selected @endif>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <span class="text-[0.62rem] text-[var(--ui-muted)]">
                        Token: <span class="font-mono">{{ Str::limit($activeQuote->token, 12) }}</span>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('events.public.quote', ['token' => $activeQuote->token]) }}" target="_blank"
                       class="text-xs text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                        @svg('heroicon-o-eye', 'w-3.5 h-3.5') Public-Ansicht
                    </a>
                    <x-ui-button variant="secondary-outline" size="sm"
                                 :href="route('events.quote.pdf', ['event' => $event->slug, 'quoteId' => $activeQuote->id])">
                        @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5 inline mr-1') PDF
                    </x-ui-button>
                    <x-ui-button variant="danger-outline" size="sm"
                                 wire:click="deleteQuote({{ $activeQuote->id }})"
                                 wire:confirm="Angebot-Version löschen?">
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                    </x-ui-button>
                </div>
            </div>
        @endif
    </x-ui-panel>

    {{-- Quote-Items pro Tag --}}
    @if($activeQuote && $days->isNotEmpty())
        @foreach($days as $day)
            @php $dayItems = $items->get($day->id, collect()); @endphp
            <x-ui-panel>
                <div class="p-4 flex items-center justify-between border-b border-[var(--ui-border)]">
                    <div>
                        <h4 class="text-sm font-bold text-[var(--ui-secondary)]">{{ $day->label }}</h4>
                        <p class="text-[0.62rem] text-[var(--ui-muted)] font-mono">{{ $day->datum?->format('d.m.Y') }} · {{ $day->day_of_week }}</p>
                    </div>
                    <x-ui-button variant="primary" size="sm" wire:click="openItemCreate({{ $day->id }})">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Position
                    </x-ui-button>
                </div>

                @if($dayItems->isEmpty())
                    <p class="p-4 text-xs text-[var(--ui-muted)] text-center">Keine Angebots-Positionen für diesen Tag.</p>
                @else
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
                                <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Typ</th>
                                <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Status</th>
                                <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Positionen</th>
                                <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Umsatz</th>
                                <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">MwSt</th>
                                <th class="px-3 py-2 w-36"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dayItems as $it)
                                <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/40">
                                    <td class="px-3 py-2 text-xs font-semibold text-[var(--ui-secondary)]">{{ $it->typ }}</td>
                                    <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $it->status }}</td>
                                    <td class="px-3 py-2 text-xs font-mono text-right">{{ $it->positionen }}</td>
                                    <td class="px-3 py-2 text-xs font-mono text-right">{{ number_format((float) $it->umsatz, 2, ',', '.') }} €</td>
                                    <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $it->mwst }}</td>
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
    @elseif($activeQuote && $days->isEmpty())
        <x-ui-panel>
            <div class="p-8 text-center text-xs text-[var(--ui-muted)]">
                Lege zuerst Event-Tage an, bevor du Angebots-Positionen hinzufügen kannst.
            </div>
        </x-ui-panel>
    @endif

    {{-- Modal: Item anlegen --}}
    <x-ui-modal wire:model="showItemModal" size="md" :hideFooter="true">
        <x-slot name="header">Neue Angebots-Position</x-slot>
        <form wire:submit.prevent="saveItem" class="space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                    <input wire:model="itemTyp" type="text" placeholder="Speisen / Getränke / Team / ..."
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Status</label>
                    <input wire:model="itemStatus" type="text"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">MwSt</label>
                    <select wire:model="itemMwst" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <option value="7%">7%</option>
                        <option value="19%">19%</option>
                    </select>
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
        <x-slot name="header">Positionen bearbeiten {{ $activeItem ? ' – ' . $activeItem->typ : '' }}</x-slot>
        <div class="space-y-4">
            @if($positions->isEmpty())
                <p class="text-xs text-[var(--ui-muted)] text-center py-4">Noch keine Positionen angelegt.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
                                <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gruppe</th>
                                <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Name</th>
                                <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Anz</th>
                                <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gebinde</th>
                                <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Preis</th>
                                <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Gesamt</th>
                                <th class="px-2 py-1.5 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($positions as $p)
                                <tr class="border-b border-[var(--ui-border)]/60">
                                    <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $p->gruppe }}</td>
                                    <td class="px-2 py-1 text-xs text-[var(--ui-secondary)]">{{ $p->name }}</td>
                                    <td class="px-2 py-1 text-xs font-mono text-right">{{ $p->anz }}</td>
                                    <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $p->gebinde }}</td>
                                    <td class="px-2 py-1 text-xs font-mono text-right">{{ number_format((float) $p->preis, 2, ',', '.') }}</td>
                                    <td class="px-2 py-1 text-xs font-mono text-right">{{ number_format((float) $p->gesamt, 2, ',', '.') }}</td>
                                    <td class="px-2 py-1">
                                        <button wire:click="deletePosition({{ $p->id }})" wire:confirm="Position löschen?" class="text-red-500 hover:text-red-700">
                                            @svg('heroicon-o-trash', 'w-3 h-3')
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="bg-[var(--ui-muted-5)] rounded-md p-3 space-y-2">
                <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Position</p>
                <div class="grid grid-cols-8 gap-2">
                    <input wire:model="newPosition.gruppe" type="text" placeholder="Gruppe" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.name" type="text" placeholder="Name" class="col-span-2 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.anz" type="text" placeholder="Anz" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                    <input wire:model="newPosition.gebinde" type="text" placeholder="Gebinde" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newPosition.preis" type="number" step="0.01" placeholder="Preis" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
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
