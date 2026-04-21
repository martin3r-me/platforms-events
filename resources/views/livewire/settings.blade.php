<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Event-Einstellungen" icon="heroicon-o-cog-6-tooth" />
    </x-slot>

    @php
        $tabs = [
            'cost_centers'  => ['label' => 'Kostenstellen',  'icon' => 'heroicon-o-currency-euro'],
            'cost_carriers' => ['label' => 'Kostenträger',   'icon' => 'heroicon-o-inbox-stack'],
            'quote_status'  => ['label' => 'Angebots-Status','icon' => 'heroicon-o-document-duplicate'],
            'order_status'  => ['label' => 'Bestell-Status', 'icon' => 'heroicon-o-shopping-cart'],
            'event_types'   => ['label' => 'Anlass-Typen',   'icon' => 'heroicon-o-calendar-days'],
            'bestuhlung'    => ['label' => 'Bestuhlung',     'icon' => 'heroicon-o-table-cells'],
            'bausteine'     => ['label' => 'Text-Bausteine',    'icon' => 'heroicon-o-puzzle-piece'],
            'mr_fields'     => ['label' => 'Management Report', 'icon' => 'heroicon-o-chart-bar-square'],
            'templates'     => ['label' => 'Dokumentvorlagen',  'icon' => 'heroicon-o-document-text'],
        ];
    @endphp

    <x-ui-page-container>
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events', 'route' => 'events.dashboard'],
            ['label' => 'Einstellungen'],
        ]" />

        {{-- Spacer zur Breadcrumb-Leiste --}}
        <div aria-hidden="true" style="height:0.625rem;"></div>

        <div class="border-b border-[var(--ui-border)] overflow-x-auto">
            <nav class="flex gap-1">
                @foreach($tabs as $key => $meta)
                    <button wire:click="$set('activeTab', '{{ $key }}')" type="button"
                            class="flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap
                                   {{ $activeTab === $key
                                      ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]'
                                      : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]' }}">
                        @svg($meta['icon'], 'w-4 h-4')
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Konfiguration der einfachen Listen-Sektionen --}}
        @php
            $simpleLists = [
                'cost_centers'  => ['title' => 'Kostenstellen',   'subtitle' => 'Werden im Event-Basis-Tab und in Rechnungen als Auswahl angezeigt.', 'list' => $costCenters,       'new' => 'newCostCenter',    'add' => 'addCostCenter',    'remove' => 'removeCostCenter',    'placeholder' => 'z.B. 4200-Hochzeiten'],
                'cost_carriers' => ['title' => 'Kostenträger',    'subtitle' => 'Optional: zusätzliche Auswahl im Event-Basis-Tab.',                  'list' => $costCarriers,      'new' => 'newCostCarrier',   'add' => 'addCostCarrier',   'remove' => 'removeCostCarrier',   'placeholder' => 'z.B. Ref-2026'],
                'quote_status'  => ['title' => 'Angebots-Status', 'subtitle' => 'Wird im Quote-Detail als Dropdown angezeigt.',                      'list' => $quoteStatuses,     'new' => 'newQuoteStatus',   'add' => 'addQuoteStatus',   'remove' => 'removeQuoteStatus',   'placeholder' => 'z.B. Versandt'],
                'order_status'  => ['title' => 'Bestell-Status',  'subtitle' => 'Wird im Bestell-Detail als Dropdown angezeigt.',                   'list' => $orderStatuses,     'new' => 'newOrderStatus',   'add' => 'addOrderStatus',   'remove' => 'removeOrderStatus',   'placeholder' => 'z.B. In Arbeit'],
                'event_types'   => ['title' => 'Anlass-Typen',    'subtitle' => 'Wird im Event-Basis-Tab im Anlass-Feld als Auswahl angezeigt.',     'list' => $eventTypes,        'new' => 'newEventType',     'add' => 'addEventType',     'remove' => 'removeEventType',     'placeholder' => 'z.B. Teamevent'],
                'bestuhlung'    => ['title' => 'Bestuhlungs-Arten','subtitle' => 'Wird im Raumbuchungs-Modal als Dropdown angezeigt.',                'list' => $bestuhlungOptions, 'new' => 'newBestuhlung',    'add' => 'addBestuhlung',    'remove' => 'removeBestuhlung',    'placeholder' => 'z.B. Bankett'],
            ];
        @endphp

        @foreach($simpleLists as $key => $cfg)
            @if($activeTab === $key)
                <div class="pt-6 space-y-4">
                    <x-ui-panel title="{{ $cfg['title'] }}" subtitle="{{ $cfg['subtitle'] }}">
                        <div class="p-4 border-b border-[var(--ui-border)] flex items-center gap-2">
                            <input wire:model="{{ $cfg['new'] }}" wire:keydown.enter.prevent="{{ $cfg['add'] }}" type="text"
                                   placeholder="{{ $cfg['placeholder'] }}"
                                   class="flex-1 border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <x-ui-button variant="primary" size="sm" wire:click="{{ $cfg['add'] }}">
                                @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Hinzufügen
                            </x-ui-button>
                        </div>
                        @if(empty($cfg['list']))
                            <div class="p-8 text-xs text-[var(--ui-muted)] text-center">
                                Noch keine Einträge.
                            </div>
                        @else
                            <ul class="divide-y divide-[var(--ui-border)]/40">
                                @foreach($cfg['list'] as $i => $item)
                                    <li class="p-3 flex items-center justify-between">
                                        <span class="text-xs text-[var(--ui-secondary)]">{{ $item }}</span>
                                        <button wire:click="{{ $cfg['remove'] }}({{ $i }})" wire:confirm="Eintrag entfernen?"
                                                class="text-[var(--ui-muted)] hover:text-red-600 p-1">
                                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ui-panel>
                </div>
            @endif
        @endforeach

        @if($activeTab === 'bausteine')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Text-Bausteine" subtitle="Wiederverwendbare Positions-Bausteine mit Farbkennung (z.B. Headline, Trenntext). Einsatz im Quote/Order-Editor spaeter.">
                    <div class="p-4 border-b border-[var(--ui-border)] grid grid-cols-[1fr_120px_120px_auto] gap-2 items-end">
                        <div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Name</label>
                            <input wire:model="newBaustein.name" type="text" placeholder="z.B. Headline"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Hintergrund</label>
                            <input wire:model="newBaustein.bg" type="color" class="w-full h-[34px] border border-[var(--ui-border)] rounded-md cursor-pointer">
                        </div>
                        <div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Text</label>
                            <input wire:model="newBaustein.text" type="color" class="w-full h-[34px] border border-[var(--ui-border)] rounded-md cursor-pointer">
                        </div>
                        <x-ui-button variant="primary" size="sm" wire:click="addBaustein">
                            @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Hinzufügen
                        </x-ui-button>
                    </div>
                    @if(empty($bausteine))
                        <div class="p-8 text-xs text-[var(--ui-muted)] text-center">Noch keine Bausteine.</div>
                    @else
                        <ul class="divide-y divide-[var(--ui-border)]/40">
                            @foreach($bausteine as $i => $b)
                                <li class="p-3 flex items-center justify-between gap-3">
                                    <span class="text-[0.7rem] font-bold px-3 py-1 rounded-md" style="background: {{ $b['bg'] }}; color: {{ $b['text'] }}">{{ $b['name'] }}</span>
                                    <div class="flex items-center gap-2 text-[0.62rem] font-mono text-[var(--ui-muted)]">
                                        <span>{{ $b['bg'] }}</span>
                                        <span>{{ $b['text'] }}</span>
                                        <button wire:click="removeBaustein({{ $i }})" wire:confirm="Baustein entfernen?"
                                                class="text-[var(--ui-muted)] hover:text-red-600 p-1">
                                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui-panel>
            </div>
        @endif

        {{-- ===== Management-Report-Felder ===== --}}
        @if($activeTab === 'mr_fields')
            <div class="pt-4 space-y-4 max-w-[960px]">
                <x-ui-panel>
                    <div class="p-4 flex items-center justify-between border-b border-[var(--ui-border)] flex-wrap gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-[var(--ui-secondary)]">Management Report — Felder</h3>
                            <p class="text-[0.62rem] text-[var(--ui-muted)]">Felder, die im Event-Details-Tab als Status-Cockpit erscheinen.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="resetMrDefaults"
                                         wire:confirm="Alle Felder löschen und Standardwerte neu laden?">
                                Defaults wiederherstellen
                            </x-ui-button>
                            <x-ui-button variant="primary" size="sm" wire:click="openMrModal">
                                @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Neues Feld
                            </x-ui-button>
                        </div>
                    </div>

                    <div class="p-4 space-y-4">
                        @if($mrFields->isEmpty())
                            <p class="text-xs text-[var(--ui-muted)] text-center py-6">
                                Noch keine Felder konfiguriert. Lege eines mit „Neues Feld" an oder nutze „Defaults wiederherstellen".
                            </p>
                        @else
                            @php $groups = $mrFields->groupBy('group_label'); @endphp
                            @foreach($groups as $groupLabel => $fields)
                                <div>
                                    <p class="text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2">{{ $groupLabel }}</p>
                                    <div class="space-y-1">
                                        @foreach($fields as $f)
                                            @php $colorMap = ['red' => 'bg-red-100 text-red-700 border-red-200', 'yellow' => 'bg-yellow-100 text-yellow-700 border-yellow-200', 'green' => 'bg-green-100 text-green-700 border-green-200', 'gray' => 'bg-slate-100 text-slate-600 border-slate-200']; @endphp
                                            <div class="flex items-center gap-2 px-3 py-2 bg-white border border-[var(--ui-border)] rounded-md {{ $f->is_active ? '' : 'opacity-60' }}">
                                                <div class="flex flex-col gap-0.5 flex-shrink-0">
                                                    <button wire:click="moveMrUp({{ $f->id }})" class="w-4 h-4 text-[0.55rem] text-slate-400 hover:text-slate-700 border border-slate-200 rounded" title="Hoch">▲</button>
                                                    <button wire:click="moveMrDown({{ $f->id }})" class="w-4 h-4 text-[0.55rem] text-slate-400 hover:text-slate-700 border border-slate-200 rounded" title="Runter">▼</button>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-[0.68rem] font-semibold text-[var(--ui-secondary)] m-0">{{ $f->label }}</p>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        @foreach($f->options ?? [] as $opt)
                                                            @php $lbl = is_array($opt) ? ($opt['label'] ?? '') : $opt; $clr = is_array($opt) ? ($opt['color'] ?? 'gray') : 'gray'; @endphp
                                                            <span class="text-[0.52rem] font-semibold px-1.5 py-0.5 rounded-full border {{ $colorMap[$clr] ?? $colorMap['gray'] }}">{{ $lbl }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <button wire:click="toggleMrActive({{ $f->id }})"
                                                        class="flex-shrink-0 w-8 h-4 rounded-full relative transition {{ $f->is_active ? 'bg-green-500' : 'bg-slate-300' }}">
                                                    <span class="absolute top-0.5 w-3 h-3 rounded-full bg-white transition-all {{ $f->is_active ? 'left-4' : 'left-0.5' }}"></span>
                                                </button>
                                                <button wire:click="openMrModal({{ $f->id }})"
                                                        class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded hover:bg-slate-50 text-slate-600" title="Bearbeiten">
                                                    @svg('heroicon-o-pencil', 'w-3 h-3')
                                                </button>
                                                <button wire:click="deleteMrField({{ $f->id }})" wire:confirm="Feld löschen?"
                                                        class="w-7 h-7 flex items-center justify-center border border-red-200 bg-red-50 rounded hover:bg-red-100 text-red-500" title="Löschen">
                                                    @svg('heroicon-o-trash', 'w-3 h-3')
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </x-ui-panel>
            </div>

            <x-ui-modal wire:model="mrModal" size="md" :hideFooter="true">
                <x-slot name="header">{{ $mrEditingId ? 'MR-Feld bearbeiten' : 'Neues MR-Feld' }}</x-slot>
                <form wire:submit.prevent="saveMrField" class="space-y-3">
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Gruppe</label>
                        <input wire:model="mrForm.group_label" type="text" placeholder="z.B. Logistik & Personal"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Feldname</label>
                        <input wire:model="mrForm.label" type="text" placeholder="z.B. Küchenpersonal"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Optionen (eine pro Zeile)</label>
                        <textarea wire:model="mrForm.options" rows="6"
                                  class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono"></textarea>
                        <p class="text-[0.56rem] text-[var(--ui-muted)] mt-1">Farben werden automatisch vergeben: erste Option rot, letzte grün, „nicht benötigt"/„keine Rechnung" grau, dazwischen gelb.</p>
                    </div>
                    <label class="flex items-center gap-2 text-[0.68rem]">
                        <input type="checkbox" wire:model="mrForm.is_active">
                        <span>Aktiv (erscheint im Event-Cockpit)</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--ui-border)]">
                        <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('mrModal', false)">Abbrechen</x-ui-button>
                        <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                    </div>
                </form>
            </x-ui-modal>
        @endif

        {{-- ===== Dokumentvorlagen ===== --}}
        @if($activeTab === 'templates')
            <div class="pt-4 space-y-4 max-w-[960px]">
                <x-ui-panel>
                    <div class="p-4 flex items-center justify-between border-b border-[var(--ui-border)] flex-wrap gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-[var(--ui-secondary)]">Dokumentvorlagen</h3>
                            <p class="text-[0.62rem] text-[var(--ui-muted)]">Vorlagen für Verträge, Optionsbestätigungen etc. Werden beim Erstellen eines neuen Dokuments als Startpunkt geladen.</p>
                        </div>
                        <x-ui-button variant="primary" size="sm" wire:click="openTplModal">
                            @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Neue Vorlage
                        </x-ui-button>
                    </div>

                    <div class="p-4 space-y-2">
                        @if($templates->isEmpty())
                            <p class="text-xs text-[var(--ui-muted)] text-center py-6">Noch keine Vorlagen angelegt.</p>
                        @else
                            @foreach($templates as $tpl)
                                <div class="flex items-center gap-2 px-3 py-2 bg-white border border-[var(--ui-border)] rounded-md {{ $tpl->is_active ? '' : 'opacity-60' }}">
                                    <span class="w-2 h-6 rounded-sm flex-shrink-0" style="background: {{ $tpl->color ?: '#7c3aed' }};"></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[0.72rem] font-semibold text-[var(--ui-secondary)] m-0">{{ $tpl->label }}</p>
                                        <p class="text-[0.58rem] text-[var(--ui-muted)] font-mono">{{ $tpl->slug }}</p>
                                        @if($tpl->description)
                                            <p class="text-[0.62rem] text-slate-500 mt-1">{{ \Illuminate\Support\Str::limit($tpl->description, 120) }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="toggleTemplateActive({{ $tpl->id }})"
                                            class="flex-shrink-0 w-8 h-4 rounded-full relative transition {{ $tpl->is_active ? 'bg-green-500' : 'bg-slate-300' }}">
                                        <span class="absolute top-0.5 w-3 h-3 rounded-full bg-white transition-all {{ $tpl->is_active ? 'left-4' : 'left-0.5' }}"></span>
                                    </button>
                                    <button wire:click="openTplModal({{ $tpl->id }})"
                                            class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded hover:bg-slate-50 text-slate-600" title="Bearbeiten">
                                        @svg('heroicon-o-pencil', 'w-3 h-3')
                                    </button>
                                    <button wire:click="deleteTemplate({{ $tpl->id }})" wire:confirm="Vorlage löschen?"
                                            class="w-7 h-7 flex items-center justify-center border border-red-200 bg-red-50 rounded hover:bg-red-100 text-red-500" title="Löschen">
                                        @svg('heroicon-o-trash', 'w-3 h-3')
                                    </button>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </x-ui-panel>
            </div>

            <x-ui-modal wire:model="tplModal" size="xl" :hideFooter="true">
                <x-slot name="header">{{ $tplEditingId ? 'Vorlage bearbeiten' : 'Neue Dokumentvorlage' }}</x-slot>
                <form wire:submit.prevent="saveTemplate" class="space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Titel</label>
                            <input wire:model="tplForm.label" type="text" placeholder="z.B. Nutzungsvertrag Standard"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Slug (optional)</label>
                            <input wire:model="tplForm.slug" type="text" placeholder="nutzungsvertrag"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Beschreibung</label>
                            <input wire:model="tplForm.description" type="text" placeholder="Kurzbeschreibung"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                        </div>
                        <div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Farbe</label>
                            <input wire:model="tplForm.color" type="color"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-1 py-0.5 h-[34px] cursor-pointer">
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Inhalt (Markdown)</label>
                            <div class="flex items-center gap-1.5">
                                <label class="flex items-center gap-1 px-2 py-0.5 border border-slate-200 rounded bg-white hover:bg-slate-50 text-[0.58rem] font-semibold text-slate-600 cursor-pointer">
                                    @svg('heroicon-o-photo', 'w-2.5 h-2.5')
                                    Bild einfügen
                                    <input type="file" wire:model="contractImage" accept="image/*" class="hidden">
                                </label>
                                <button type="button" wire:click="convertTplToMarkdown"
                                        class="flex items-center gap-1 px-2 py-0.5 border border-slate-200 rounded bg-white hover:bg-slate-50 text-[0.58rem] font-semibold text-slate-600 cursor-pointer">
                                    @svg('heroicon-o-arrow-path', 'w-2.5 h-2.5')
                                    HTML → Markdown
                                </button>
                            </div>
                        </div>
                        <div wire:loading wire:target="contractImage" class="text-[0.6rem] text-slate-500 mb-1">Bild wird hochgeladen …</div>
                        @if(session('contractImageError'))
                            <div class="text-[0.6rem] text-red-500 mb-1">{{ session('contractImageError') }}</div>
                        @endif
                        <textarea wire:model="tplForm.html_content" rows="14"
                                  class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono"
                                  placeholder="# Nutzungsvertrag&#10;&#10;Platzhalter wie {CUSTOMER_COMPANY}, {EVENT_NUMBER} etc. werden beim PDF-Export ersetzt.&#10;&#10;Auch HTML moeglich - beim Speichern automatisch nach Markdown konvertiert."></textarea>
                        <p class="text-[0.56rem] text-[var(--ui-muted)] mt-1">Markdown empfohlen. Platzhalter in geschweiften Klammern (siehe unten) werden beim Rendern mit Event-Daten gefüllt.</p>

                        {{-- Platzhalter-Liste --}}
                        <div class="mt-2" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                    class="flex items-center gap-1 text-[0.6rem] font-semibold text-purple-600 hover:text-purple-700 border-0 bg-transparent p-0 cursor-pointer">
                                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-90' : ''"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                Verfügbare Platzhalter
                            </button>
                            <div x-show="open" x-cloak class="grid grid-cols-2 gap-1 mt-2 p-2 bg-slate-50 rounded border border-slate-100">
                                @foreach(\Platform\Events\Services\ContractRenderer::availablePlaceholders() as $key => $desc)
                                    <div class="flex items-center gap-1.5 text-[0.58rem]">
                                        <code class="px-1 py-0.5 bg-white border border-slate-200 rounded text-purple-600 font-mono">{{ $key }}</code>
                                        <span class="text-slate-500 truncate">{{ $desc }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-[0.68rem]">
                        <input type="checkbox" wire:model="tplForm.is_active">
                        <span>Aktiv (erscheint in der Vorlagenauswahl)</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-3 border-t border-[var(--ui-border)]">
                        <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('tplModal', false)">Abbrechen</x-ui-button>
                        <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                    </div>
                </form>
            </x-ui-modal>
        @endif
    </x-ui-page-container>
</x-ui-page>
