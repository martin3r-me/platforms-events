<div class="space-y-4">
    @php
        $statusMap = [
            'open'        => ['label' => 'Offen',        'bg' => 'bg-slate-100', 'text' => 'text-slate-600'],
            'in_progress' => ['label' => 'In Arbeit',    'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
            'packed'      => ['label' => 'Gepackt',      'bg' => 'bg-cyan-50',   'text' => 'text-cyan-700'],
            'loaded'      => ['label' => 'Verladen',     'bg' => 'bg-green-100', 'text' => 'text-green-700'],
        ];
        $itemStatusMap = [
            'open'   => ['label' => 'Offen',    'icon' => 'heroicon-o-circle-stack', 'class' => 'text-slate-500'],
            'picked' => ['label' => 'Gepickt',  'icon' => 'heroicon-o-check',        'class' => 'text-blue-600'],
            'packed' => ['label' => 'Gepackt',  'icon' => 'heroicon-o-cube',         'class' => 'text-cyan-600'],
            'loaded' => ['label' => 'Verladen', 'icon' => 'heroicon-o-truck',        'class' => 'text-green-600'],
        ];
    @endphp

    <x-ui-panel>
        <div class="p-4 flex justify-between items-start gap-4 flex-wrap">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] mb-2">Packlisten</h3>
                @if($lists->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)]">Keine Packliste angelegt.</p>
                @else
                    <div class="flex gap-2 flex-wrap">
                        @foreach($lists as $l)
                            @php $sb = $statusMap[$l->status] ?? $statusMap['open']; @endphp
                            <button wire:click="selectList({{ $l->id }})"
                                    class="px-3 py-1.5 rounded-md border text-xs flex items-center gap-2 transition-colors
                                           {{ $active && $active->id === $l->id
                                              ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]'
                                              : 'border-[var(--ui-border)] bg-white text-[var(--ui-muted)]' }}">
                                <span class="font-semibold">{{ $l->title }}</span>
                                <span class="text-[0.62rem] font-bold px-1.5 py-0.5 rounded-full {{ $sb['bg'] }} {{ $sb['text'] }}">{{ $sb['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                    @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Neue Packliste
                </x-ui-button>
                <x-ui-button variant="secondary-outline" size="sm" wire:click="generateFromOrders"
                             wire:confirm="Aus aktuellen Bestellungen generieren?">
                    @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5 inline') Aus Bestellungen
                </x-ui-button>
            </div>
        </div>

        @if($active)
            <div class="p-4 border-t border-[var(--ui-border)] flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold">{{ $active->title }}</span>
                    <select wire:change="setStatus($event.target.value)"
                            class="border border-[var(--ui-border)] rounded-md px-2 py-1 text-xs bg-white">
                        @foreach($statusMap as $key => $meta)
                            <option value="{{ $key }}" @if($active->status === $key) selected @endif>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <span class="text-[0.62rem] text-[var(--ui-muted)]">Token: <span class="font-mono">{{ Str::limit($active->token, 12) }}</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openItems">
                        Positionen ({{ $items->count() }})
                    </x-ui-button>
                    <a href="{{ route('events.public.picklist', ['token' => $active->token]) }}" target="_blank"
                       class="text-xs text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                        @svg('heroicon-o-eye', 'w-3.5 h-3.5') Mobile-Ansicht
                    </a>
                    <x-ui-button variant="danger-outline" size="sm"
                                 wire:click="deleteList({{ $active->id }})"
                                 wire:confirm="Packliste löschen?">
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                    </x-ui-button>
                </div>
            </div>

            <div class="p-4 border-t border-[var(--ui-border)]">
                @if($items->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)] text-center">Noch keine Positionen.</p>
                @else
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
                                <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gruppe</th>
                                <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Name</th>
                                <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Anz</th>
                                <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gebinde</th>
                                <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Status</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php $im = $itemStatusMap[$item->status] ?? $itemStatusMap['open']; @endphp
                                <tr class="border-b border-[var(--ui-border)]/60">
                                    <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $item->gruppe }}</td>
                                    <td class="px-2 py-1 text-xs text-[var(--ui-secondary)]">{{ $item->name }}</td>
                                    <td class="px-2 py-1 text-xs font-mono text-right">{{ $item->quantity }}</td>
                                    <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $item->gebinde }}</td>
                                    <td class="px-2 py-1 text-xs">
                                        <button wire:click="toggleItemStatus({{ $item->id }})" class="flex items-center gap-1 {{ $im['class'] }}">
                                            @svg($im['icon'], 'w-3.5 h-3.5')
                                            {{ $im['label'] }}
                                        </button>
                                    </td>
                                    <td class="px-2 py-1">
                                        <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Löschen?" class="text-red-500 hover:text-red-700">
                                            @svg('heroicon-o-trash', 'w-3 h-3')
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </x-ui-panel>

    <x-ui-modal wire:model="showCreateModal" size="md" :hideFooter="true">
        <x-slot name="header">Neue Packliste</x-slot>
        <form wire:submit.prevent="createList" class="space-y-4">
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Titel *</label>
                <input wire:model="newTitle" type="text" placeholder="z.B. Hauptpackliste"
                       class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('showCreateModal', false)">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Anlegen</x-ui-button>
            </div>
        </form>
    </x-ui-modal>

    <x-ui-modal wire:model="showItemsModal" size="lg" :hideFooter="true">
        <x-slot name="header">Pack-Positionen</x-slot>
        <div class="space-y-4">
            <div class="bg-[var(--ui-muted-5)] rounded-md p-3 space-y-2">
                <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Position</p>
                <div class="grid grid-cols-5 gap-2">
                    <input wire:model="newItem.gruppe" type="text" placeholder="Gruppe" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.name" type="text" placeholder="Name" class="col-span-2 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.quantity" type="number" placeholder="Anz" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono">
                    <input wire:model="newItem.gebinde" type="text" placeholder="Gebinde" class="border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                </div>
                <input wire:model="newItem.lagerort" type="text" placeholder="Lagerort" class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs">
                <div class="flex justify-end">
                    <x-ui-button wire:click="addItem" variant="primary" size="sm">Hinzufügen</x-ui-button>
                </div>
            </div>
            <div class="flex justify-end pt-3 border-t border-[var(--ui-border)]">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showItemsModal', false)">Schließen</x-ui-button>
            </div>
        </div>
    </x-ui-modal>
</div>
