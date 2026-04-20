<div class="space-y-4 max-w-[960px]">
    @php
        $statusMeta = [
            'open'        => ['label' => 'Offen',         'bg' => '#f1f5f9', 'color' => '#64748b', 'bar' => '#2563eb'],
            'in_progress' => ['label' => 'In Bearbeitung','bg' => '#dbeafe', 'color' => '#2563eb', 'bar' => '#2563eb'],
            'packed'      => ['label' => 'Gepackt',       'bg' => '#fef3c7', 'color' => '#d97706', 'bar' => '#d97706'],
            'loaded'      => ['label' => 'Verladen',      'bg' => '#dcfce7', 'color' => '#16a34a', 'bar' => '#16a34a'],
        ];
        $itemStatusMap = [
            'open'   => ['label' => 'Offen',    'icon' => 'heroicon-o-circle-stack', 'class' => 'text-slate-500'],
            'picked' => ['label' => 'Gepickt',  'icon' => 'heroicon-o-check',        'class' => 'text-blue-600'],
            'packed' => ['label' => 'Gepackt',  'icon' => 'heroicon-o-cube',         'class' => 'text-cyan-600'],
            'loaded' => ['label' => 'Verladen', 'icon' => 'heroicon-o-truck',        'class' => 'text-green-600'],
        ];
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2.5">
            <div class="w-[3px] h-4 bg-blue-600 rounded-sm"></div>
            <h2 class="text-[0.9rem] font-bold text-[var(--ui-secondary)]">Packliste</h2>
        </div>
        <div class="flex items-center gap-1.5">
            <button type="button" wire:click="openCreate"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 border-0 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-[0.68rem] font-bold cursor-pointer">
                @svg('heroicon-o-plus', 'w-3 h-3')
                Neue Packliste
            </button>
            <button type="button" wire:click="generateFromOrders" wire:confirm="Aus aktuellen Bestellungen generieren?"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.65rem] font-semibold cursor-pointer">
                @svg('heroicon-o-arrow-path', 'w-3 h-3')
                Aus Bestellungen
            </button>
        </div>
    </div>

    {{-- Empty state --}}
    @if($lists->isEmpty())
        <div class="bg-white border border-[var(--ui-border)] rounded-xl p-10 text-center">
            @svg('heroicon-o-cube', 'w-10 h-10 text-slate-300 mx-auto mb-3')
            <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">
                Noch keine Packlisten. Klicke oben auf „Neue Packliste" oder „Aus Bestellungen", um eine Liste anzulegen.
            </p>
        </div>
    @else
        {{-- Existing pick-lists --}}
        <div class="flex flex-col gap-2 mb-4">
            @foreach($lists as $pl)
                @php
                    $s = $statusMeta[$pl->status] ?? $statusMeta['open'];
                    $total = $pl->items_count ?? $pl->items()->count();
                    $picked = $pl->picked_count ?? $pl->items()->whereIn('status', ['picked','packed','loaded'])->count();
                    $pct = $total > 0 ? ($picked / $total) * 100 : 0;
                    $isActive = $active && $active->id === $pl->id;
                @endphp
                <div class="bg-white border rounded-xl px-4 py-3 transition {{ $isActive ? 'border-blue-300 ring-1 ring-blue-200' : 'border-[var(--ui-border)]' }}">
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 rounded text-[0.58rem] font-bold whitespace-nowrap"
                              style="background: {{ $s['bg'] }}; color: {{ $s['color'] }};">{{ $s['label'] }}</span>
                        <button wire:click="selectList({{ $pl->id }})" class="flex-1 min-w-0 text-left">
                            <p class="text-[0.72rem] font-semibold text-[var(--ui-secondary)] m-0">{{ $pl->title }}</p>
                            <div class="flex gap-2 mt-0.5">
                                <span class="text-[0.58rem] text-[var(--ui-muted)] font-mono">{{ $total }} Positionen</span>
                                <span class="text-[0.58rem] text-slate-500">{{ $pl->created_at->format('d.m.Y') }}</span>
                            </div>
                        </button>
                        <div class="w-[60px] flex-shrink-0">
                            <div class="h-1 bg-slate-100 rounded-sm overflow-hidden">
                                <div class="h-full transition-all" style="width: {{ $pct }}%; background: {{ $s['bar'] }};"></div>
                            </div>
                            <p class="text-[0.55rem] text-[var(--ui-muted)] mt-0.5 text-center font-mono">{{ $picked }}/{{ $total }}</p>
                        </div>
                        <div class="flex gap-1 flex-shrink-0">
                            <a href="{{ route('events.public.picklist', ['token' => $pl->token]) }}" target="_blank"
                               class="px-2.5 py-1 rounded text-[0.6rem] font-semibold text-blue-600 bg-blue-50 border border-blue-100 hover:bg-blue-100 no-underline">
                                Öffnen
                            </a>
                            <button type="button"
                                    x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText('{{ route('events.public.picklist', ['token' => $pl->token]) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                    :class="copied ? 'bg-green-100 border-green-300 text-green-600' : 'bg-slate-100 border-slate-200 text-slate-500'"
                                    class="px-2 py-1 rounded border cursor-pointer text-[0.6rem]" title="Picking-Link kopieren">
                                @svg('heroicon-o-link', 'w-3 h-3')
                            </button>
                            <button wire:click="deleteList({{ $pl->id }})" wire:confirm="Packliste wirklich löschen?"
                                    class="px-2 py-1 rounded bg-red-50 border border-red-200 text-red-500 hover:bg-red-100 cursor-pointer">
                                @svg('heroicon-o-trash', 'w-3 h-3')
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Active pick-list details --}}
        @if($active)
            <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <span class="text-[0.72rem] font-semibold text-[var(--ui-secondary)]">{{ $active->title }}</span>
                        <select wire:change="setStatus($event.target.value)"
                                class="border border-[var(--ui-border)] rounded-md px-2 py-1 text-[0.65rem] bg-white cursor-pointer">
                            @foreach($statusMeta as $key => $meta)
                                <option value="{{ $key }}" @selected($active->status === $key)>{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                        <span class="text-[0.58rem] text-[var(--ui-muted)]">Token: <span class="font-mono">{{ \Illuminate\Support\Str::limit($active->token, 12) }}</span></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button wire:click="openItems"
                                class="px-2.5 py-1 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.65rem] font-semibold">
                            Position hinzufügen
                        </button>
                    </div>
                </div>

                @if($items->isEmpty())
                    <div class="px-4 py-6 text-center">
                        <p class="text-[0.7rem] text-[var(--ui-muted)] m-0">Noch keine Positionen in dieser Packliste.</p>
                    </div>
                @else
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-[var(--ui-border)]">
                                <th class="px-2 py-1.5 text-left text-[0.58rem] font-bold text-[var(--ui-muted)] uppercase tracking-wider">Gruppe</th>
                                <th class="px-2 py-1.5 text-left text-[0.58rem] font-bold text-[var(--ui-muted)] uppercase tracking-wider">Name</th>
                                <th class="px-2 py-1.5 text-right text-[0.58rem] font-bold text-[var(--ui-muted)] uppercase tracking-wider">Anz.</th>
                                <th class="px-2 py-1.5 text-left text-[0.58rem] font-bold text-[var(--ui-muted)] uppercase tracking-wider">Gebinde</th>
                                <th class="px-2 py-1.5 text-left text-[0.58rem] font-bold text-[var(--ui-muted)] uppercase tracking-wider">Lagerort</th>
                                <th class="px-2 py-1.5 text-left text-[0.58rem] font-bold text-[var(--ui-muted)] uppercase tracking-wider">Status</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php $im = $itemStatusMap[$item->status] ?? $itemStatusMap['open']; @endphp
                                <tr class="border-b border-slate-100">
                                    <td class="px-2 py-1.5 text-[0.65rem] text-[var(--ui-muted)]">{{ $item->gruppe }}</td>
                                    <td class="px-2 py-1.5 text-[0.68rem] text-[var(--ui-secondary)]">{{ $item->name }}</td>
                                    <td class="px-2 py-1.5 text-[0.68rem] font-mono text-right">{{ $item->quantity }}</td>
                                    <td class="px-2 py-1.5 text-[0.65rem] text-[var(--ui-muted)]">{{ $item->gebinde }}</td>
                                    <td class="px-2 py-1.5 text-[0.65rem] text-[var(--ui-muted)]">{{ $item->lagerort }}</td>
                                    <td class="px-2 py-1.5 text-[0.65rem]">
                                        <button wire:click="toggleItemStatus({{ $item->id }})"
                                                class="flex items-center gap-1 {{ $im['class'] }}">
                                            @svg($im['icon'], 'w-3 h-3')
                                            {{ $im['label'] }}
                                        </button>
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Löschen?"
                                                class="text-red-500 hover:text-red-700">
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
    @endif

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
        <x-slot name="header">Position hinzufügen</x-slot>
        <div class="space-y-4">
            <div class="bg-slate-50 rounded-md p-3 space-y-2">
                <div class="grid grid-cols-5 gap-2">
                    <input wire:model="newItem.gruppe" type="text" placeholder="Gruppe" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.name" type="text" placeholder="Name" class="col-span-2 border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.quantity" type="number" placeholder="Anz" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs font-mono">
                    <input wire:model="newItem.gebinde" type="text" placeholder="Gebinde" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                </div>
                <input wire:model="newItem.lagerort" type="text" placeholder="Lagerort" class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs">
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
