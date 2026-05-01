<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Artikel-Pakete" icon="heroicon-o-archive-box" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events', 'route' => 'events.dashboard'],
            ['label' => 'Pakete'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openPackageCreate">
                <span class="flex items-center gap-2">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neues Paket
                </span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div aria-hidden="true" style="height:0.625rem;"></div>

        <div class="space-y-4">
            <x-ui-panel title="Artikel-Pakete" subtitle="Vorkonfigurierte Artikel-Zusammenstellungen fuer Angebote und Bestellungen">
                <div class="p-4 border-b border-[var(--ui-border)]">
                    <div class="relative max-w-md">
                        <input wire:model.live.debounce.400ms="search" type="text"
                               placeholder="Paket suchen …"
                               class="w-full border border-[var(--ui-border)] rounded-md pl-9 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                            @svg('heroicon-o-magnifying-glass', 'w-4 h-4')
                        </span>
                    </div>
                </div>

                @if($packages->isEmpty())
                    <div class="p-12 text-center">
                        @svg('heroicon-o-archive-box', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                        <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Pakete</p>
                        <p class="text-xs text-[var(--ui-muted)]">Legen Sie das erste Paket an.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                    <th class="px-3 py-2 text-center text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Items</th>
                                    <th class="px-3 py-2 text-center text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Aktiv</th>
                                    <th class="px-3 py-2 w-32"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($packages as $p)
                                    <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60">
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $p->color }}"></span>
                                                <div>
                                                    <span class="text-xs font-semibold text-[var(--ui-secondary)]">{{ $p->name }}</span>
                                                    @if($p->description)
                                                        <p class="text-[0.62rem] text-[var(--ui-muted)] mt-0.5">{{ Str::limit($p->description, 80) }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-xs font-mono text-center text-[var(--ui-muted)]">{{ $p->items_count }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if($p->is_active)
                                                <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Ja</span>
                                            @else
                                                <span class="text-[0.62rem] text-[var(--ui-muted)]">Nein</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center justify-end gap-1">
                                                <x-ui-button variant="secondary-outline" size="sm" wire:click="openPackageItems('{{ $p->uuid }}')">
                                                    Items
                                                </x-ui-button>
                                                <x-ui-button variant="secondary-outline" size="sm" wire:click="openPackageEdit('{{ $p->uuid }}')">
                                                    @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                                </x-ui-button>
                                                <x-ui-button variant="danger-outline" size="sm"
                                                             wire:click="deletePackage('{{ $p->uuid }}')"
                                                             wire:confirm="Paket loeschen?">
                                                    @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                                </x-ui-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui-panel>
        </div>

        {{-- ========= Modal: Package ========= --}}
        <x-ui-modal wire:model="showPackageModal" size="md" :hideFooter="true">
            <x-slot name="header">{{ $editingPackageUuid ? 'Paket bearbeiten' : 'Neues Paket' }}</x-slot>
            <form wire:submit.prevent="savePackage" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name *</label>
                    <input wire:model="packageName" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    @error('packageName') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Beschreibung</label>
                    <textarea wire:model="packageDescription" rows="2" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Farbe</label>
                        <input wire:model="packageColor" type="color" class="w-full h-[34px] border border-[var(--ui-border)] rounded-md cursor-pointer">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input wire:model="packageIsActive" type="checkbox" class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                            <span class="text-xs text-[var(--ui-secondary)]">Aktiv</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closePackageModal">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

        {{-- ========= Modal: Package Items ========= --}}
        <x-ui-modal wire:model="showPackageItemsModal" size="lg" :hideFooter="true">
            <x-slot name="header">Paket-Items</x-slot>
            <div class="space-y-4">
                @if($packageItems->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)] text-center py-4">Noch keine Items. Fuegen Sie den ersten Eintrag unten hinzu.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <th class="px-2 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Artikel</th>
                                    <th class="px-2 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Gruppe</th>
                                    <th class="px-2 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Anz</th>
                                    <th class="px-2 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Gebinde</th>
                                    <th class="px-2 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">VK</th>
                                    <th class="px-2 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($packageItems as $item)
                                    <tr class="border-b border-[var(--ui-border)]/60">
                                        <td class="px-2 py-1.5 text-xs text-[var(--ui-secondary)]">{{ $item->name }}</td>
                                        <td class="px-2 py-1.5 text-xs text-[var(--ui-muted)]">{{ $item->gruppe }}</td>
                                        <td class="px-2 py-1.5 text-xs font-mono text-right">{{ $item->quantity }}</td>
                                        <td class="px-2 py-1.5 text-xs text-[var(--ui-muted)]">{{ $item->gebinde }}</td>
                                        <td class="px-2 py-1.5 text-xs font-mono text-right text-[var(--ui-muted)]">{{ number_format((float) $item->vk, 2, ',', '.') }}</td>
                                        <td class="px-2 py-1.5">
                                            <button wire:click="deletePackageItem({{ $item->id }})" wire:confirm="Eintrag loeschen?" class="text-red-500 hover:text-red-700">
                                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="bg-[var(--ui-muted-5)] rounded-md p-3 space-y-2">
                    <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neuer Eintrag</p>
                    <div class="grid grid-cols-6 gap-2">
                        {{-- Name mit Artikel-Suche --}}
                        <div class="col-span-2 relative" x-data="{ showArticles: false }">
                            <input wire:model.live.debounce.300ms="newPackageItem.name" type="text"
                                   placeholder="Name / Artikel suchen"
                                   @focus="showArticles = true"
                                   @click.outside="showArticles = false"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @if(($packageArticleMatches ?? collect())->isNotEmpty())
                                <div x-show="showArticles" x-cloak
                                     class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 max-h-64 overflow-y-auto min-w-[320px]">
                                    @foreach($packageArticleMatches as $art)
                                        <button type="button"
                                                wire:click="pickArticleForPackageItem({{ $art['id'] }})"
                                                @click="showArticles = false"
                                                class="w-full flex items-center gap-2 px-2 py-1.5 text-left hover:bg-slate-50 transition border-0 bg-transparent cursor-pointer">
                                            <span class="text-[0.58rem] font-mono font-bold text-slate-500 min-w-[70px] flex-shrink-0">{{ $art['article_number'] }}</span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[0.7rem] text-slate-700 truncate">{{ $art['name'] }}</div>
                                                @if($art['gebinde'] ?? null)
                                                    <div class="text-[0.58rem] text-slate-400">{{ $art['gebinde'] }}</div>
                                                @endif
                                            </div>
                                            @if(($art['vk'] ?? 0) > 0)
                                                <span class="text-[0.62rem] font-mono text-slate-500 flex-shrink-0">{{ number_format($art['vk'], 2, ',', '.') }} €</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Gruppe mit Baustein-Picker --}}
                        <div class="relative" x-data="{ showBausteine: false }">
                            <div class="flex gap-1">
                                <input wire:model="newPackageItem.gruppe" type="text" placeholder="Gruppe"
                                       class="flex-1 min-w-0 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                @if(!empty($bausteine))
                                    <button type="button" @click="showBausteine = !showBausteine"
                                            class="px-2 rounded border border-purple-200 bg-purple-50 hover:bg-purple-100 text-purple-700 text-[0.6rem] font-bold cursor-pointer flex-shrink-0"
                                            title="Baustein waehlen">
                                        @svg('heroicon-o-rectangle-stack', 'w-3 h-3')
                                    </button>
                                @endif
                            </div>
                            @if(!empty($bausteine))
                                <div x-show="showBausteine" x-cloak
                                     @click.outside="showBausteine = false"
                                     class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded p-1 shadow-lg z-50 min-w-[160px]">
                                    @foreach($bausteine as $b)
                                        <button type="button"
                                                wire:click="$set('newPackageItem.gruppe', @js($b['name'] ?? ''))"
                                                @click="showBausteine = false"
                                                class="flex items-center gap-2 w-full px-2.5 py-1.5 rounded hover:bg-slate-50 text-left text-[0.65rem] font-medium text-slate-700 border-0 bg-transparent cursor-pointer">
                                            <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0"
                                                  style="background: {{ $b['bg'] ?? '#f8fafc' }}; border: 1px solid {{ $b['text'] ?? '#64748b' }};"></span>
                                            <span>{{ $b['name'] ?? '' }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <input wire:model="newPackageItem.quantity" type="number" placeholder="Anz"
                               class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <input wire:model="newPackageItem.gebinde" type="text" placeholder="Gebinde"
                               class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <input wire:model="newPackageItem.vk" type="number" step="0.01" placeholder="VK"
                               class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div class="flex justify-end">
                        <x-ui-button wire:click="addPackageItem" variant="primary" size="sm">
                            Hinzufuegen
                        </x-ui-button>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-[var(--ui-border)]">
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="closePackageItemsModal">Schliessen</x-ui-button>
                </div>
            </div>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
