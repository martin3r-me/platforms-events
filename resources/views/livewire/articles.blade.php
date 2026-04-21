<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Artikel-Stammdaten" icon="heroicon-o-squares-2x2" />
    </x-slot>

    @php
        $tabs = [
            'articles' => ['label' => 'Artikel',  'icon' => 'heroicon-o-cube'],
            'groups'   => ['label' => 'Gruppen',  'icon' => 'heroicon-o-folder'],
            'packages' => ['label' => 'Pakete',   'icon' => 'heroicon-o-archive-box'],
        ];
    @endphp

    <x-ui-page-container>
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events', 'route' => 'events.dashboard'],
            ['label' => 'Artikel'],
        ]">
            @if($activeTab === 'articles')
                <x-ui-button variant="primary" size="sm" wire:click="openArticleCreate">
                    <span class="flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4') Neuer Artikel
                    </span>
                </x-ui-button>
            @elseif($activeTab === 'groups')
                <x-ui-button variant="primary" size="sm" wire:click="openGroupCreate">
                    <span class="flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4') Neue Gruppe
                    </span>
                </x-ui-button>
            @else
                <x-ui-button variant="primary" size="sm" wire:click="openPackageCreate">
                    <span class="flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4') Neues Paket
                    </span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>

        {{-- Tab-Navigation --}}
        <div class="mt-4 border-b border-[var(--ui-border)]">
            <nav class="flex gap-1">
                @foreach($tabs as $key => $meta)
                    <button wire:click="$set('activeTab', '{{ $key }}')" type="button"
                            class="flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors
                                   {{ $activeTab === $key
                                      ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]'
                                      : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]' }}">
                        @svg($meta['icon'], 'w-4 h-4')
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ========= Tab: Articles ========= --}}
        @if($activeTab === 'articles')
            <div class="pt-6 space-y-4">
                <x-ui-panel>
                    <div class="p-4 flex gap-3 flex-wrap items-center border-b border-[var(--ui-border)]">
                        <div class="flex-1 min-w-[200px] relative">
                            <input wire:model.live.debounce.400ms="search" type="text"
                                   placeholder="Suche nach Name, Artikelnummer, Ext.-Code …"
                                   class="w-full border border-[var(--ui-border)] rounded-md pl-9 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                                @svg('heroicon-o-magnifying-glass', 'w-4 h-4')
                            </span>
                        </div>
                        <select wire:model.live="groupFilter"
                                class="border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <option value="">Alle Gruppen</option>
                            @foreach($groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($articles->isEmpty())
                        <div class="p-12 text-center">
                            @svg('heroicon-o-cube', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                            <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Artikel</p>
                            <p class="text-xs text-[var(--ui-muted)]">Lege den ersten Artikel an.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Art.-Nr.</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Gruppe</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Gebinde</th>
                                        <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">EK</th>
                                        <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">VK</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">MwSt</th>
                                        <th class="px-3 py-2 text-center text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Aktiv</th>
                                        <th class="px-3 py-2 w-24"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($articles as $a)
                                        <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60">
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">{{ $a->article_number }}</td>
                                            <td class="px-3 py-2 text-xs font-semibold text-[var(--ui-secondary)]">{{ $a->name }}</td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $a->group?->name ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $a->gebinde ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs font-mono text-right text-[var(--ui-muted)]">{{ number_format((float) $a->ek, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-xs font-mono text-right text-[var(--ui-muted)]">{{ number_format((float) $a->vk, 2, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $a->mwst }}</td>
                                            <td class="px-3 py-2 text-center">
                                                @if($a->is_active)
                                                    <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Ja</span>
                                                @else
                                                    <span class="text-[0.62rem] text-[var(--ui-muted)]">Nein</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-end gap-1">
                                                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openArticleEdit('{{ $a->uuid }}')">
                                                        @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                                    </x-ui-button>
                                                    <x-ui-button variant="danger-outline" size="sm"
                                                                 wire:click="deleteArticle('{{ $a->uuid }}')"
                                                                 wire:confirm="Artikel „{{ $a->name }}“ löschen?">
                                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                                    </x-ui-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($articles->hasPages())
                            <div class="p-4 border-t border-[var(--ui-border)]">{{ $articles->links() }}</div>
                        @endif
                    @endif
                </x-ui-panel>
            </div>
        @endif

        {{-- ========= Tab: Groups ========= --}}
        @if($activeTab === 'groups')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Artikelgruppen" subtitle="Hierarchisch – Untergruppen via parent_id">
                    @if($groups->isEmpty())
                        <div class="p-12 text-center">
                            @svg('heroicon-o-folder', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                            <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Gruppen</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Parent</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Erlös 7%</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Erlös 19%</th>
                                        <th class="px-3 py-2 text-center text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Aktiv</th>
                                        <th class="px-3 py-2 w-24"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($groups as $g)
                                        <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60">
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $g->color }}"></span>
                                                    <span class="text-xs font-semibold text-[var(--ui-secondary)]">{{ $g->name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">
                                                {{ optional($groups->firstWhere('id', $g->parent_id))->name ?: '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">{{ $g->erloeskonto_7 }}</td>
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">{{ $g->erloeskonto_19 }}</td>
                                            <td class="px-3 py-2 text-center">
                                                @if($g->is_active)
                                                    <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Ja</span>
                                                @else
                                                    <span class="text-[0.62rem] text-[var(--ui-muted)]">Nein</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-end gap-1">
                                                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openGroupEdit('{{ $g->uuid }}')">
                                                        @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                                    </x-ui-button>
                                                    <x-ui-button variant="danger-outline" size="sm"
                                                                 wire:click="deleteGroup('{{ $g->uuid }}')"
                                                                 wire:confirm="Gruppe löschen?">
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
        @endif

        {{-- ========= Tab: Packages ========= --}}
        @if($activeTab === 'packages')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Artikel-Pakete" subtitle="Vorkonfigurierte Artikel-Zusammenstellungen">
                    @if($packages->isEmpty())
                        <div class="p-12 text-center">
                            @svg('heroicon-o-archive-box', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                            <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Pakete</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Gruppe</th>
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
                                                    <span class="text-xs font-semibold text-[var(--ui-secondary)]">{{ $p->name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $p->group?->name ?: '—' }}</td>
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
                                                                 wire:confirm="Paket löschen?">
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
        @endif

        {{-- ========= Modal: Article ========= --}}
        <x-ui-modal wire:model="showArticleModal" size="lg" :hideFooter="true">
            <x-slot name="header">{{ $editingArticleUuid ? 'Artikel bearbeiten' : 'Neuer Artikel' }}</x-slot>
            <form wire:submit.prevent="saveArticle" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-[140px_1fr_140px] gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Art.-Nr. *</label>
                        <input wire:model="articleNumber" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('articleNumber') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name *</label>
                        <input wire:model="articleName" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('articleName') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Ext.-Code</label>
                        <input wire:model="articleExternalCode" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Gruppe</label>
                        <select wire:model="articleGroupId" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <option value="">—</option>
                            @foreach($groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Gebinde</label>
                        <input wire:model="articleGebinde" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">EK</label>
                        <input wire:model="articleEk" type="number" step="0.01" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">VK</label>
                        <input wire:model="articleVk" type="number" step="0.01" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">MwSt</label>
                        <select wire:model="articleMwst" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <option value="0%">0%</option>
                            <option value="7%">7%</option>
                            <option value="19%">19%</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Erlöskonto</label>
                        <input wire:model="articleErloeskonto" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Beschreibung</label>
                    <textarea wire:model="articleDescription" rows="2" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Angebots-Text</label>
                        <textarea wire:model="articleOfferText" rows="2" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Rechnungs-Text</label>
                        <textarea wire:model="articleInvoiceText" rows="2" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Lagerort</label>
                        <input wire:model="articleLagerort" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Min. Bestand</label>
                        <input wire:model="articleMinBestand" type="number" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Akt. Bestand</label>
                        <input wire:model="articleCurrentBestand" type="number" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input wire:model="articleIsActive" type="checkbox" class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                            <span class="text-xs text-[var(--ui-secondary)]">Aktiv</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeArticleModal">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

        {{-- ========= Modal: Group ========= --}}
        <x-ui-modal wire:model="showGroupModal" size="md" :hideFooter="true">
            <x-slot name="header">{{ $editingGroupUuid ? 'Gruppe bearbeiten' : 'Neue Gruppe' }}</x-slot>
            <form wire:submit.prevent="saveGroup" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name *</label>
                    <input wire:model="groupName" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    @error('groupName') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Obergruppe</label>
                        <select wire:model="groupParentId" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <option value="">— (Toplevel)</option>
                            @foreach($groups as $g)
                                @if($g->uuid !== $editingGroupUuid)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Farbe</label>
                        <input wire:model="groupColor" type="color" class="w-full h-[34px] border border-[var(--ui-border)] rounded-md cursor-pointer">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Erlös 7%</label>
                        <input wire:model="groupErloeskonto7" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Erlös 19%</label>
                        <input wire:model="groupErloeskonto19" type="text" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Sortierung</label>
                        <input wire:model="groupSortOrder" type="number" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input wire:model="groupIsActive" type="checkbox" class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                    <span class="text-xs text-[var(--ui-secondary)]">Aktiv</span>
                </label>
                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeGroupModal">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

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
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Gruppe</label>
                        <select wire:model="packageArticleGroupId" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <option value="">—</option>
                            @foreach($groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Farbe</label>
                        <input wire:model="packageColor" type="color" class="w-full h-[34px] border border-[var(--ui-border)] rounded-md cursor-pointer">
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input wire:model="packageIsActive" type="checkbox" class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                    <span class="text-xs text-[var(--ui-secondary)]">Aktiv</span>
                </label>
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
                    <p class="text-xs text-[var(--ui-muted)] text-center py-4">Noch keine Items. Fügen Sie den ersten Eintrag unten hinzu.</p>
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
                                        <td class="px-2 py-1.5 text-xs text-[var(--ui-secondary)]">{{ $item->article?->name ?: $item->name }}</td>
                                        <td class="px-2 py-1.5 text-xs text-[var(--ui-muted)]">{{ $item->gruppe }}</td>
                                        <td class="px-2 py-1.5 text-xs font-mono text-right">{{ $item->quantity }}</td>
                                        <td class="px-2 py-1.5 text-xs text-[var(--ui-muted)]">{{ $item->gebinde }}</td>
                                        <td class="px-2 py-1.5 text-xs font-mono text-right text-[var(--ui-muted)]">{{ number_format((float) $item->vk, 2, ',', '.') }}</td>
                                        <td class="px-2 py-1.5">
                                            <button wire:click="deletePackageItem({{ $item->id }})" wire:confirm="Eintrag löschen?" class="text-red-500 hover:text-red-700">
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
                        <div class="col-span-2">
                            <input wire:model="newPackageItem.name" type="text" placeholder="Name"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <input wire:model="newPackageItem.gruppe" type="text" placeholder="Gruppe"
                               class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <input wire:model="newPackageItem.quantity" type="number" placeholder="Anz"
                               class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <input wire:model="newPackageItem.gebinde" type="text" placeholder="Gebinde"
                               class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <input wire:model="newPackageItem.vk" type="number" step="0.01" placeholder="VK"
                               class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div class="flex justify-end">
                        <x-ui-button wire:click="addPackageItem" variant="primary" size="sm">
                            Hinzufügen
                        </x-ui-button>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-[var(--ui-border)]">
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="closePackageItemsModal">Schließen</x-ui-button>
                </div>
            </div>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
