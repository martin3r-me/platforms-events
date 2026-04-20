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
            'bausteine'     => ['label' => 'Text-Bausteine', 'icon' => 'heroicon-o-puzzle-piece'],
        ];
    @endphp

    <x-ui-page-container>
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events', 'route' => 'events.dashboard'],
            ['label' => 'Einstellungen'],
        ]" />

        <div class="mt-4 border-b border-[var(--ui-border)] overflow-x-auto">
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

        {{-- Macros via @php + reuse --}}
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
    </x-ui-page-container>
</x-ui-page>
