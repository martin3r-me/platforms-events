<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Event-Einstellungen" icon="heroicon-o-cog-6-tooth" />
    </x-slot>

    @php
        $tabs = [
            'cost_centers'  => ['label' => 'Kostenstellen',  'icon' => 'heroicon-o-currency-euro'],
            'cost_carriers' => ['label' => 'Kostenträger',   'icon' => 'heroicon-o-inbox-stack'],
            'quote_status'  => ['label' => 'Angebots-Status','icon' => 'heroicon-o-document-duplicate'],
            'quote_options' => ['label' => 'Angebots-Optionen','icon' => 'heroicon-o-adjustments-horizontal'],
            'order_status'  => ['label' => 'Bestell-Status', 'icon' => 'heroicon-o-shopping-cart'],
            'event_types'   => ['label' => 'Anlass-Typen',   'icon' => 'heroicon-o-calendar-days'],
            'bestuhlung'    => ['label' => 'Bestuhlung',     'icon' => 'heroicon-o-table-cells'],
            'schedule_desc' => ['label' => 'Ablaufplan-Beschreibungen', 'icon' => 'heroicon-o-list-bullet'],
            'day_types'     => ['label' => 'Tages-Typen',    'icon' => 'heroicon-o-calendar'],
            'beverage_modes'=> ['label' => 'Getränke-Modi',  'icon' => 'heroicon-o-beaker'],
            'order_number'  => ['label' => 'Ordernummer',    'icon' => 'heroicon-o-hashtag'],
            'flat_rates'    => ['label' => 'Pauschalen',     'icon' => 'heroicon-o-calculator'],
            'bausteine'     => ['label' => 'Text-Bausteine',    'icon' => 'heroicon-o-puzzle-piece'],
            'mr_fields'     => ['label' => 'Management Report', 'icon' => 'heroicon-o-chart-bar-square'],
            'templates'     => ['label' => 'Dokumentvorlagen',  'icon' => 'heroicon-o-document-text'],
        ];
    @endphp

    {{-- Einstellungs-Navigation als zweite Sidebar (vertikal) --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-fit min-w-[200px] max-w-[280px]" :defaultOpen="true" side="left">
            <nav class="p-2 space-y-0.5 text-xs">
                @foreach($tabs as $key => $meta)
                    <button wire:click="$set('activeTab', '{{ $key }}')" type="button"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-md transition text-left
                                   {{ $activeTab === $key
                                      ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold'
                                      : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @svg($meta['icon'], 'w-4 h-4 flex-shrink-0')
                        <span class="flex-1 whitespace-nowrap">{{ $meta['label'] }}</span>
                    </button>
                @endforeach
            </nav>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events', 'route' => 'events.dashboard'],
            ['label' => 'Einstellungen'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        {{-- Konfiguration der einfachen Listen-Sektionen --}}
        @php
            $simpleLists = [
                'cost_centers'  => ['title' => 'Kostenstellen',   'subtitle' => 'Werden im Event-Basis-Tab und in Rechnungen als Auswahl angezeigt.', 'list' => $costCenters,       'prop' => 'costCenters',          'new' => 'newCostCenter',    'add' => 'addCostCenter',    'remove' => 'removeCostCenter',    'placeholder' => 'z.B. 4200-Hochzeiten'],
                'cost_carriers' => ['title' => 'Kostenträger',    'subtitle' => 'Optional: zusätzliche Auswahl im Event-Basis-Tab.',                  'list' => $costCarriers,      'prop' => 'costCarriers',         'new' => 'newCostCarrier',   'add' => 'addCostCarrier',   'remove' => 'removeCostCarrier',   'placeholder' => 'z.B. Ref-2026'],
                'quote_status'  => ['title' => 'Angebots-Status', 'subtitle' => 'Wird im Quote-Detail als Dropdown angezeigt.',                      'list' => $quoteStatuses,     'prop' => 'quoteStatuses',        'new' => 'newQuoteStatus',   'add' => 'addQuoteStatus',   'remove' => 'removeQuoteStatus',   'placeholder' => 'z.B. Versandt'],
                'order_status'  => ['title' => 'Bestell-Status',  'subtitle' => 'Wird im Bestell-Detail als Dropdown angezeigt.',                   'list' => $orderStatuses,     'prop' => 'orderStatuses',        'new' => 'newOrderStatus',   'add' => 'addOrderStatus',   'remove' => 'removeOrderStatus',   'placeholder' => 'z.B. In Arbeit'],
                'event_types'   => ['title' => 'Anlass-Typen',    'subtitle' => 'Wird im Event-Basis-Tab im Anlass-Feld als Auswahl angezeigt.',     'list' => $eventTypes,        'prop' => 'eventTypes',           'new' => 'newEventType',     'add' => 'addEventType',     'remove' => 'removeEventType',     'placeholder' => 'z.B. Teamevent'],
                'bestuhlung'    => ['title' => 'Bestuhlungs-Arten','subtitle' => 'Wird im Raumbuchungs-Modal als Dropdown angezeigt.',                'list' => $bestuhlungOptions, 'prop' => 'bestuhlungOptions',    'new' => 'newBestuhlung',    'add' => 'addBestuhlung',    'remove' => 'removeBestuhlung',    'placeholder' => 'z.B. Bankett'],
                'schedule_desc' => ['title' => 'Ablaufplan-Beschreibungen','subtitle' => 'Vorschlaege im Beschreibung-Feld des Ablaufplans. Freitext bleibt weiterhin moeglich.', 'list' => $scheduleDescriptions, 'prop' => 'scheduleDescriptions', 'new' => 'newScheduleDescription', 'add' => 'addScheduleDescription', 'remove' => 'removeScheduleDescription', 'placeholder' => 'z.B. Empfang'],
                'day_types'     => ['title' => 'Tages-Typen',     'subtitle' => 'Auswahl im Tag-Modal (Veranstaltungstag, Aufbautag, Abbautag, Rüsttag, …).', 'list' => $dayTypes, 'prop' => 'dayTypes', 'new' => 'newDayType', 'add' => 'addDayType', 'remove' => 'removeDayType', 'placeholder' => 'z.B. Probentag'],
                'beverage_modes'=> ['title' => 'Getränke-Modi',   'subtitle' => 'Auswahl am Vorgang/an Positionen für Getränke. „Auf Anfrage" (oder Varianten davon) blendet im Angebot den Preis aus.', 'list' => $beverageModes, 'prop' => 'beverageModes', 'new' => 'newBeverageMode', 'add' => 'addBeverageMode', 'remove' => 'removeBeverageMode', 'placeholder' => 'z.B. Open Bar'],
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
                                @php $lastIdx = count($cfg['list']) - 1; @endphp
                                @foreach($cfg['list'] as $i => $item)
                                    <li class="p-3 flex items-center justify-between gap-2">
                                        <span class="text-xs text-[var(--ui-secondary)] flex-1">{{ $item }}</span>
                                        <div class="flex items-center gap-0.5">
                                            <button wire:click="moveSimpleItem('{{ $cfg['prop'] }}', {{ $i }}, -1)"
                                                    @disabled($i === 0)
                                                    title="Nach oben"
                                                    class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] p-1 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-[var(--ui-muted)]">
                                                @svg('heroicon-o-chevron-up', 'w-3.5 h-3.5')
                                            </button>
                                            <button wire:click="moveSimpleItem('{{ $cfg['prop'] }}', {{ $i }}, 1)"
                                                    @disabled($i === $lastIdx)
                                                    title="Nach unten"
                                                    class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] p-1 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-[var(--ui-muted)]">
                                                @svg('heroicon-o-chevron-down', 'w-3.5 h-3.5')
                                            </button>
                                            <button wire:click="{{ $cfg['remove'] }}({{ $i }})" wire:confirm="Eintrag entfernen?"
                                                    title="Entfernen"
                                                    class="text-[var(--ui-muted)] hover:text-red-600 p-1 ml-1">
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
        @endforeach

        @if($activeTab === 'quote_options')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Angebots-Optionen" subtitle="Team-weite Standardwerte fuer neue und bestehende Angebote. Projektleiter koennen am einzelnen Angebot davon abweichen.">
                    <div class="p-4 space-y-4">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="attachFloorPlansDefault"
                                   class="mt-0.5 h-4 w-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]/30">
                            <span class="flex-1">
                                <span class="block text-xs font-semibold text-[var(--ui-secondary)]">Raumgrundrisse standardmaessig ans Angebot anhaengen</span>
                                <span class="block mt-0.5 text-[0.7rem] text-[var(--ui-muted)]">
                                    Wenn aktiviert, werden bei neuen Angeboten die Grundrisse der gebuchten Raeume automatisch in das Angebots-PDF und in den oeffentlichen Einsichtslink aufgenommen. Der Projektleiter kann das pro Angebot ein- oder ausschalten.
                                </span>
                            </span>
                        </label>
                    </div>
                </x-ui-panel>
            </div>
        @endif

        @if($activeTab === 'order_number')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Ordernummer-Schema" subtitle="Wird im Event-Basis-Tab in der Kachel „Zuständigkeit“ angezeigt und kann per Klick in die Zwischenablage kopiert werden.">
                    <div class="p-4 space-y-3">
                        <div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Schema</label>
                            <input wire:model="orderNumberSchema" wire:keydown.enter.prevent="saveOrderNumberSchema" type="text"
                                   placeholder="{{ \Platform\Events\Services\OrderNumberBuilder::DEFAULT_SCHEMA }}"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui-button variant="primary" size="sm" wire:click="saveOrderNumberSchema">
                                @svg('heroicon-o-check', 'w-3.5 h-3.5 inline') Speichern
                            </x-ui-button>
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="resetOrderNumberSchema">
                                Zurück auf Default
                            </x-ui-button>
                        </div>
                        <div class="pt-2 border-t border-[var(--ui-border)]">
                            <div class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Verfügbare Platzhalter</div>
                            <ul class="space-y-1">
                                @foreach(\Platform\Events\Services\OrderNumberBuilder::PLACEHOLDERS as $ph => $desc)
                                    <li class="flex items-start gap-2 text-[0.7rem]">
                                        <code class="font-mono font-semibold text-[var(--ui-primary)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded whitespace-nowrap">{{ $ph }}</code>
                                        <span class="text-[var(--ui-muted)]">{{ $desc }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-3 text-[0.65rem] text-[var(--ui-muted)]">
                                Beispiel: <code class="font-mono">{{ \Platform\Events\Services\OrderNumberBuilder::DEFAULT_SCHEMA }}</code>
                                → <code class="font-mono font-semibold text-[var(--ui-secondary)]">CW-1500-VA#2026-044</code>
                            </div>
                        </div>
                    </div>
                </x-ui-panel>
            </div>
        @endif

        @if($activeTab === 'flat_rates')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Pauschal-Regeln" subtitle="Regelbasierte Kalkulation für Vorgänge (z.B. Getränke, Buffet, Bar, Equipment). Eine Regel wird im Positions-Editor des Vorgangs manuell angewendet und erzeugt eine Pauschale-Position.">
                    <div class="p-4 border-b border-[var(--ui-border)] flex justify-end">
                        <x-ui-button variant="primary" size="sm" wire:click="openFlatRateModal">
                            @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Neue Regel
                        </x-ui-button>
                    </div>
                    @if($flatRateRules->isEmpty())
                        <div class="p-8 text-xs text-[var(--ui-muted)] text-center italic">
                            Noch keine Regel angelegt. Beispiel: <code class="font-mono bg-slate-100 px-1">day.pers_avg * (20 + (event.season == 'summer' ? 5 : 0))</code>
                        </div>
                    @else
                        <ul class="divide-y divide-[var(--ui-border)]/40">
                            @foreach($flatRateRules as $rule)
                                <li class="p-3 flex items-start gap-3">
                                    <button wire:click="toggleFlatRateActive({{ $rule->id }})" type="button"
                                            title="{{ $rule->is_active ? 'Aktiv – klicken zum Deaktivieren' : 'Inaktiv – klicken zum Aktivieren' }}"
                                            class="mt-0.5 w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $rule->is_active ? 'bg-green-500' : 'bg-slate-300' }}"></button>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">{{ $rule->name }}</span>
                                            @foreach(($rule->scope_typs ?? []) as $typ)
                                                <span class="text-[0.55rem] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-blue-50 text-blue-700">{{ $typ }}</span>
                                            @endforeach
                                            @if(!empty($rule->scope_event_types))
                                                <span class="text-[0.55rem] text-[var(--ui-muted)]">· Anlass: {{ implode(', ', $rule->scope_event_types) }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 text-[0.62rem] font-mono text-[var(--ui-muted)] break-all">{{ $rule->formula }}</div>
                                        <div class="mt-1 text-[0.58rem] text-[var(--ui-muted)]">
                                            Output: <span class="font-semibold text-[var(--ui-secondary)]">{{ $rule->output_name }}</span>
                                            · Gruppe <span class="font-mono">{{ $rule->output_gruppe }}</span>
                                            · MwSt {{ $rule->output_mwst }}
                                            · Priorität {{ $rule->priority }}
                                        </div>
                                        @if($rule->last_error)
                                            <div class="mt-1 text-[0.6rem] text-red-600 flex items-center gap-1">
                                                @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                                {{ $rule->last_error }}
                                                <span class="text-[var(--ui-muted)]">({{ $rule->last_error_at?->format('d.m.Y H:i') }})</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <button wire:click="openFlatRateModal({{ $rule->id }})" class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] p-1" title="Bearbeiten">
                                            @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                        </button>
                                        <button wire:click="deleteFlatRate({{ $rule->id }})" wire:confirm="Regel löschen?"
                                                class="text-[var(--ui-muted)] hover:text-red-600 p-1" title="Löschen">
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

        {{-- Modal: FlatRate-Regel ---------------------------------------------------------- --}}
        <x-ui-modal wire:model="flatRateModal" size="lg" :hideFooter="true">
            <x-slot name="header">{{ $flatRateEditingId ? 'Pauschal-Regel bearbeiten' : 'Neue Pauschal-Regel' }}</x-slot>
            <form wire:submit.prevent="saveFlatRate" class="space-y-3">
                @if(session('flatRateError'))
                    <div class="px-3 py-2 rounded-md bg-red-50 border border-red-200 text-[0.68rem] text-red-700 flex items-center gap-1.5">
                        @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5') {{ session('flatRateError') }}
                    </div>
                @endif

                <div class="grid grid-cols-[1fr_120px] gap-3">
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Name *</label>
                        <input wire:model="flatRateForm.name" type="text" placeholder="z.B. Getränke-Pauschale Hochzeit"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Priorität</label>
                        <input wire:model="flatRateForm.priority" type="number" min="0" max="9999"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono">
                    </div>
                </div>

                <div>
                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Beschreibung</label>
                    <textarea wire:model="flatRateForm.description" rows="2" placeholder="Optional"
                              class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Scope-Typen * (komma-getrennt)</label>
                        <input wire:model="flatRateForm.scope_typs" type="text" placeholder="Getränke, Bar"
                               list="flat-rate-typs"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                        <datalist id="flat-rate-typs">
                            @foreach($flatRateAllowedTypes as $t)
                                <option value="{{ $t }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Scope-Anlässe (optional)</label>
                        <input wire:model="flatRateForm.scope_event_types" type="text" placeholder="z.B. Hochzeit, Gala — leer = alle"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    </div>
                </div>

                <div>
                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Formel *</label>
                    <textarea wire:model="flatRateForm.formula" rows="3"
                              placeholder="day.pers_avg * (20 + (event.season == 'summer' ? 5 : 0))"
                              class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-[0.72rem] font-mono"></textarea>
                </div>

                <div class="grid grid-cols-[1fr_1fr_80px_120px] gap-3">
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Output-Bezeichnung</label>
                        <input wire:model="flatRateForm.output_name" type="text" placeholder="Getränke-Pauschale"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Output-Gruppe *</label>
                        <input wire:model="flatRateForm.output_gruppe" type="text" placeholder="z.B. Getränke"
                               list="flat-rate-gruppen"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                        <datalist id="flat-rate-gruppen">
                            @foreach($flatRateAllowedGruppen as $g)
                                <option value="{{ $g }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">MwSt</label>
                        <select wire:model="flatRateForm.output_mwst" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                            <option value="0%">0%</option>
                            <option value="7%">7%</option>
                            <option value="19%">19%</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Beschaffung</label>
                        <input wire:model="flatRateForm.output_procurement_type" type="text" placeholder="optional"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer select-none text-[0.7rem]">
                    <input wire:model="flatRateForm.is_active" type="checkbox" class="w-3 h-3 accent-[var(--ui-primary)] cursor-pointer">
                    <span class="font-semibold text-[var(--ui-secondary)]">Regel ist aktiv</span>
                </label>

                {{-- Variablen-Legende + Dry-Run --}}
                <div class="grid grid-cols-2 gap-3 border-t border-[var(--ui-border)] pt-3">
                    <div>
                        <div class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Verfügbare Variablen</div>
                        <div class="max-h-48 overflow-y-auto border border-[var(--ui-border)] rounded-md p-2 space-y-0.5 bg-slate-50">
                            @foreach($flatRateCatalog['variables'] as $name => $desc)
                                <div class="text-[0.6rem] flex items-start gap-1.5">
                                    <code class="font-mono text-blue-600 font-semibold whitespace-nowrap">{{ $name }}</code>
                                    <span class="text-[var(--ui-muted)]">{{ $desc }}</span>
                                </div>
                            @endforeach
                            <div class="pt-1 mt-1 border-t border-[var(--ui-border)]/50">
                                @foreach($flatRateCatalog['functions'] as $name => $desc)
                                    <div class="text-[0.6rem] flex items-start gap-1.5">
                                        <code class="font-mono text-violet-600 font-semibold whitespace-nowrap">{{ $name }}</code>
                                        <span class="text-[var(--ui-muted)]">{{ $desc }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Dry-Run gegen Vorgang</div>
                        <select wire:model="flatRateDryRunItemId"
                                class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-[0.7rem] mb-2">
                            <option value="">— Vorgang wählen —</option>
                            @foreach($flatRateDryRunItems as $it)
                                <option value="{{ $it->id }}">{{ $it->typ }} · {{ $it->eventDay?->event?->event_number }} · {{ $it->eventDay?->datum?->format('d.m.Y') }}</option>
                            @endforeach
                        </select>
                        <x-ui-button variant="secondary-outline" size="sm" type="button" wire:click="runFlatRateDryRun">
                            @svg('heroicon-o-play', 'w-3 h-3 inline') Auswerten
                        </x-ui-button>
                        @if($flatRateDryRunResult)
                            <div class="mt-2 p-2 rounded-md border {{ $flatRateDryRunResult['ok'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }}">
                                @if($flatRateDryRunResult['ok'])
                                    <div class="text-[0.7rem] font-bold text-green-700">Ergebnis: {{ number_format((float) $flatRateDryRunResult['value'], 2, ',', '.') }} €</div>
                                @else
                                    <div class="text-[0.7rem] font-semibold text-red-700">{{ $flatRateDryRunResult['error'] ?? 'Fehler' }}</div>
                                @endif
                                @if(!empty($flatRateDryRunResult['context']))
                                    <details class="mt-1">
                                        <summary class="text-[0.58rem] font-semibold text-[var(--ui-muted)] cursor-pointer">Kontext anzeigen</summary>
                                        <pre class="text-[0.55rem] font-mono whitespace-pre-wrap break-all text-[var(--ui-muted)] mt-1">{{ json_encode($flatRateDryRunResult['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                    </details>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('flatRateModal', false)">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

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
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Inhalt</label>
                            <label class="flex items-center gap-1 px-2 py-0.5 border border-slate-200 rounded bg-white hover:bg-slate-50 text-[0.58rem] font-semibold text-slate-600 cursor-pointer">
                                @svg('heroicon-o-document-arrow-up', 'w-2.5 h-2.5')
                                HTML-Datei laden
                                <input type="file" wire:model="tplHtmlFile" accept=".html,.htm,text/html" class="hidden">
                            </label>
                        </div>
                        <div wire:loading wire:target="tplHtmlFile" class="text-[0.6rem] text-slate-500 mb-1">HTML-Datei wird geladen …</div>
                        @if(session('tplHtmlFileError'))
                            <div class="text-[0.6rem] text-red-500 mb-1">{{ session('tplHtmlFileError') }}</div>
                        @endif
                        @include('events::partials.tinymce-editor', [
                            'wireProperty' => 'tplForm.html_content',
                            'initial'      => $tplForm['html_content'] ?? '',
                            'height'       => 500,
                            'uniqueId'     => 'tiny-template',
                        ])
                        <p class="text-[0.56rem] text-[var(--ui-muted)] mt-2">
                            Formatierung ueber die Toolbar. Platzhalter in geschweiften Klammern werden beim Rendern mit Event-Daten gefuellt.
                        </p>

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
