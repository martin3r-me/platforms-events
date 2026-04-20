<div class="space-y-4">
    @php
        $typeMap = [
            'rechnung'        => ['label' => 'Rechnung',         'bg' => 'bg-blue-50',  'text' => 'text-blue-700'],
            'teilrechnung'    => ['label' => 'Teilrechnung',     'bg' => 'bg-cyan-50',  'text' => 'text-cyan-700'],
            'schlussrechnung' => ['label' => 'Schlussrechnung',  'bg' => 'bg-indigo-50','text' => 'text-indigo-700'],
            'gutschrift'      => ['label' => 'Gutschrift',       'bg' => 'bg-green-50', 'text' => 'text-green-700'],
            'storno'          => ['label' => 'Storno',           'bg' => 'bg-red-50',   'text' => 'text-red-700'],
        ];
        $statusMap = [
            'draft'     => ['label' => 'Entwurf',   'bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
            'sent'      => ['label' => 'Versandt',  'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
            'paid'      => ['label' => 'Bezahlt',   'bg' => 'bg-green-100', 'text' => 'text-green-700'],
            'overdue'   => ['label' => 'Ueberfallig','bg' => 'bg-orange-50','text' => 'text-orange-700'],
            'cancelled' => ['label' => 'Storniert', 'bg' => 'bg-red-50',    'text' => 'text-red-700'],
        ];
    @endphp

    <x-ui-panel>
        <div class="p-4 flex justify-between items-start gap-4 flex-wrap">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] mb-2">Rechnungen</h3>
                @if($invoices->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)]">Keine Rechnung angelegt.</p>
                @else
                    <div class="flex gap-2 flex-wrap">
                        @foreach($invoices as $inv)
                            @php
                                $tm = $typeMap[$inv->type] ?? $typeMap['rechnung'];
                                $sm = $statusMap[$inv->status] ?? $statusMap['draft'];
                            @endphp
                            <button wire:click="selectInvoice({{ $inv->id }})"
                                    class="px-3 py-1.5 rounded-md border text-xs flex items-center gap-2 transition-colors
                                           {{ $active && $active->id === $inv->id
                                              ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]'
                                              : 'border-[var(--ui-border)] bg-white text-[var(--ui-muted)] hover:border-[var(--ui-primary)]/40' }}">
                                <span class="font-mono font-bold">{{ $inv->invoice_number }}</span>
                                <span class="text-[0.62rem] font-bold px-1.5 py-0.5 rounded-full {{ $tm['bg'] }} {{ $tm['text'] }}">{{ $tm['label'] }}</span>
                                <span class="text-[0.62rem] font-bold px-1.5 py-0.5 rounded-full {{ $sm['bg'] }} {{ $sm['text'] }}">{{ $sm['label'] }}</span>
                                <span class="font-mono text-[0.62rem]">{{ number_format((float) $inv->brutto, 2, ',', '.') }}€</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                    <span class="flex items-center gap-1.5">@svg('heroicon-o-plus', 'w-3.5 h-3.5') Neue Rechnung</span>
                </x-ui-button>
                <span title="DATEV-Export kommt später" class="text-[0.62rem] text-[var(--ui-muted)] italic">
                    DATEV-Export folgt
                </span>
            </div>
        </div>

        @if($active)
            <div class="p-4 border-t border-[var(--ui-border)] flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-mono font-bold text-[var(--ui-primary)]">{{ $active->invoice_number }}</span>
                    <select wire:change="setStatus($event.target.value)"
                            class="border border-[var(--ui-border)] rounded-md px-2 py-1 text-xs bg-white">
                        @foreach($statusMap as $key => $meta)
                            <option value="{{ $key }}" @if($active->status === $key) selected @endif>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <span class="text-[0.62rem] text-[var(--ui-muted)]">
                        faellig: <span class="font-mono">{{ $active->due_date?->format('d.m.Y') ?: '—' }}</span>
                    </span>
                    @if($active->reminder_level > 0)
                        <span class="text-[0.62rem] font-bold text-orange-600">Mahnstufe {{ $active->reminder_level }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openItems">
                        Positionen ({{ $active->items->count() }})
                    </x-ui-button>
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="markReminded"
                                 wire:confirm="Mahnstufe erhöhen?">
                        Mahnung
                    </x-ui-button>
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="createGutschrift"
                                 wire:confirm="Gutschrift erstellen?">
                        Gutschrift
                    </x-ui-button>
                    <a href="{{ route('events.public.invoice', ['token' => $active->token]) }}" target="_blank"
                       class="text-xs text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                        @svg('heroicon-o-eye', 'w-3.5 h-3.5') Public
                    </a>
                    <x-ui-button variant="secondary-outline" size="sm"
                                 :href="route('events.invoice.pdf', ['event' => $event->slug, 'invoiceId' => $active->id])">
                        @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5 inline mr-1') PDF
                    </x-ui-button>
                    <x-ui-button variant="danger-outline" size="sm"
                                 wire:click="deleteInvoice({{ $active->id }})"
                                 wire:confirm="Rechnung löschen?">
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                    </x-ui-button>
                </div>
            </div>

            <div class="p-4 border-t border-[var(--ui-border)] grid grid-cols-4 gap-3 text-xs">
                <div>
                    <div class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Netto</div>
                    <div class="font-mono font-bold">{{ number_format((float) $active->netto, 2, ',', '.') }} €</div>
                </div>
                <div>
                    <div class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">MwSt 7%</div>
                    <div class="font-mono">{{ number_format((float) $active->mwst_7, 2, ',', '.') }} €</div>
                </div>
                <div>
                    <div class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">MwSt 19%</div>
                    <div class="font-mono">{{ number_format((float) $active->mwst_19, 2, ',', '.') }} €</div>
                </div>
                <div>
                    <div class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Brutto</div>
                    <div class="font-mono font-bold text-[var(--ui-primary)]">{{ number_format((float) $active->brutto, 2, ',', '.') }} €</div>
                </div>
            </div>
        @endif
    </x-ui-panel>

    {{-- Modal: Create --}}
    <x-ui-modal wire:model="showCreateModal" size="md" :hideFooter="true">
        <x-slot name="header">Neue Rechnung</x-slot>
        <form wire:submit.prevent="createInvoice" class="space-y-4">
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                <select wire:model="newType" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    @foreach($typeMap as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-[0.62rem] text-[var(--ui-muted)]">Rechnungsnummer wird automatisch vergeben (RE-YYYY-####). Kunde wird aus dem Event vorbefüllt und kann später angepasst werden.</p>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('showCreateModal', false)">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Rechnung anlegen</x-ui-button>
            </div>
        </form>
    </x-ui-modal>

    {{-- Modal: Items --}}
    <x-ui-modal wire:model="showItemsModal" size="xl" :hideFooter="true">
        <x-slot name="header">Rechnungs-Positionen</x-slot>
        <div class="space-y-4">
            @if($active && $active->items->isEmpty())
                <p class="text-xs text-[var(--ui-muted)] text-center py-4">Noch keine Positionen.</p>
            @elseif($active)
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gruppe</th>
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Name</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Anz</th>
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gebinde</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Einzelpreis</th>
                            <th class="px-2 py-1.5 text-center text-[0.62rem] font-bold text-[var(--ui-muted)]">MwSt</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Gesamt</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($active->items as $it)
                            <tr class="border-b border-[var(--ui-border)]/60">
                                <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $it->gruppe }}</td>
                                <td class="px-2 py-1 text-xs text-[var(--ui-secondary)]">{{ $it->name }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ (float) $it->quantity }}</td>
                                <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $it->gebinde }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ number_format((float) $it->unit_price, 2, ',', '.') }}</td>
                                <td class="px-2 py-1 text-xs text-center">{{ $it->mwst_rate }}%</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ number_format((float) $it->total, 2, ',', '.') }}</td>
                                <td class="px-2 py-1">
                                    <button wire:click="deleteItem({{ $it->id }})" wire:confirm="Löschen?" class="text-red-500 hover:text-red-700">
                                        @svg('heroicon-o-trash', 'w-3 h-3')
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="bg-[var(--ui-muted-5)] rounded-md p-3 space-y-2">
                <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Rechnungsposition</p>
                <div class="grid grid-cols-8 gap-2">
                    <input wire:model="newItem.gruppe" type="text" placeholder="Gruppe" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.name" type="text" placeholder="Name" class="col-span-2 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.quantity" type="number" step="0.01" placeholder="Anz" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                    <input wire:model="newItem.gebinde" type="text" placeholder="Gebinde" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.unit_price" type="number" step="0.01" placeholder="Einzelpr." class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                    <select wire:model="newItem.mwst_rate" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                        <option value="7">7%</option>
                        <option value="19">19%</option>
                    </select>
                    <input wire:model="newItem.total" type="number" step="0.01" placeholder="Gesamt" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                </div>
                <div class="flex justify-end">
                    <x-ui-button wire:click="addItem" variant="primary" size="sm">Position hinzufügen</x-ui-button>
                </div>
            </div>

            <div class="flex justify-end pt-3 border-t border-[var(--ui-border)]">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeItems">Schließen</x-ui-button>
            </div>
        </div>
    </x-ui-modal>
</div>
