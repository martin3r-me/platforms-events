<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $event->name }}" icon="heroicon-o-calendar-days" />
    </x-slot>

    @php
        $statusBadge = [
            'Vertrag'       => 'bg-green-100 text-green-700',
            'Definitiv'     => 'bg-green-50 text-green-700',
            'Option'        => 'bg-yellow-50 text-yellow-800',
            'Abgeschlossen' => 'bg-slate-100 text-slate-600',
            'Storno'        => 'bg-red-50 text-red-700',
            'Warteliste'    => 'bg-orange-50 text-orange-700',
            'Tendenz'       => 'bg-purple-50 text-purple-700',
        ];
        $currentStatus = $event->status ?: 'Option';
        $statusClass   = $statusBadge[$currentStatus] ?? 'bg-slate-100 text-slate-600';

        $tabs = [
            'basis'        => ['label' => 'Basis',             'icon' => 'heroicon-o-identification'],
            'details'      => ['label' => 'Details',           'icon' => 'heroicon-o-document-text'],
            'buchungen'    => ['label' => 'Räume',             'icon' => 'heroicon-o-building-office-2'],
            'ablauf'       => ['label' => 'Ablauf',            'icon' => 'heroicon-o-clock'],
            'aktivitaeten' => ['label' => 'Aktivitäten',       'icon' => 'heroicon-o-bolt'],
            'kalkulation'  => ['label' => 'Kalkulation',       'icon' => 'heroicon-o-calculator'],
            'projekt'      => ['label' => 'Projekt Function',  'icon' => 'heroicon-o-document-check'],
            'vertraege'    => ['label' => 'Verträge',          'icon' => 'heroicon-o-document'],
            'packliste'    => ['label' => 'Packliste',         'icon' => 'heroicon-o-cube'],
            'kommunikation'=> ['label' => 'Kommunikation',     'icon' => 'heroicon-o-envelope'],
            'angebote'     => ['label' => 'Angebote',          'icon' => 'heroicon-o-document-duplicate'],
            'bestellungen' => ['label' => 'Bestellungen',      'icon' => 'heroicon-o-shopping-cart'],
            'rechnungen'   => ['label' => 'Rechnungen',        'icon' => 'heroicon-o-receipt-percent'],
            'schluss'      => ['label' => 'Schlussbericht',    'icon' => 'heroicon-o-presentation-chart-line'],
            'feedback'     => ['label' => 'Feedback',          'icon' => 'heroicon-o-chat-bubble-left-right'],
        ];
    @endphp

    {{-- Event-Detail-Sidebar (Tab-Navigation + Drilldown) --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Event-Modul" width="w-72" :defaultOpen="true">
            {{-- Event-Header --}}
            <div class="p-4 border-b border-[var(--ui-border)] flex items-start gap-2">
                @svg('heroicon-o-calendar-days', 'w-5 h-5 text-[var(--ui-primary)] flex-shrink-0 mt-0.5')
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-[var(--ui-secondary)] truncate" title="{{ $event->name }}">{{ $event->name }}</p>
                    <p class="text-[0.68rem] text-[var(--ui-muted)] font-mono">{{ $event->event_number }}</p>
                </div>
            </div>

            @php
                $badgeClass = fn ($n, $color = 'slate') => $n > 0
                    ? match ($color) {
                        'primary' => 'bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]',
                        'purple'  => 'bg-purple-100 text-purple-700',
                        'blue'    => 'bg-blue-100 text-blue-700',
                        'green'   => 'bg-green-100 text-green-700',
                        default   => 'bg-slate-200 text-slate-700',
                    }
                    : 'hidden';

                $primaryTabs = [
                    ['key' => 'basis',        'label' => 'Basis',            'icon' => 'heroicon-o-identification'],
                    ['key' => 'details',      'label' => 'Details',          'icon' => 'heroicon-o-document-text'],
                    ['key' => 'buchungen',    'label' => 'Räume',            'icon' => 'heroicon-o-building-office-2', 'badge' => $counts['buchungen']],
                    ['key' => 'ablauf',       'label' => 'Ablauf',           'icon' => 'heroicon-o-clock',             'badge' => $counts['ablauf']],
                    ['key' => 'aktivitaeten', 'label' => 'Aktivitäten',      'icon' => 'heroicon-o-bolt',              'badge' => $counts['aktivitaeten']],
                    ['key' => 'kalkulation',  'label' => 'Kalkulation',      'icon' => 'heroicon-o-calculator'],
                    ['key' => 'projekt',      'label' => 'Projekt Function', 'icon' => 'heroicon-o-document-check'],
                    ['key' => 'vertraege',    'label' => 'Verträge',         'icon' => 'heroicon-o-document',          'badge' => $counts['vertraege'],    'badgeColor' => 'purple'],
                    ['key' => 'packliste',    'label' => 'Packliste',        'icon' => 'heroicon-o-cube',              'badge' => $counts['packliste']],
                    ['key' => 'kommunikation','label' => 'Kommunikation',    'icon' => 'heroicon-o-envelope',          'badge' => $counts['kommunikation'], 'badgeColor' => 'blue'],
                ];

                $tailTabs = [
                    ['key' => 'rechnungen', 'label' => 'Rechnungen',    'icon' => 'heroicon-o-receipt-percent',          'badge' => $counts['rechnungen']],
                    ['key' => 'schluss',    'label' => 'Schlussbericht','icon' => 'heroicon-o-presentation-chart-line'],
                    ['key' => 'feedback',   'label' => 'Feedback',      'icon' => 'heroicon-o-chat-bubble-left-right',   'badge' => $counts['feedback']],
                ];
            @endphp

            <nav class="p-2 space-y-0.5 text-xs">
                @foreach($primaryTabs as $t)
                    <button wire:click="$set('activeTab', '{{ $t['key'] }}')" type="button"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-md transition text-left
                                   {{ $activeTab === $t['key']
                                      ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold'
                                      : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @svg($t['icon'], 'w-4 h-4 flex-shrink-0')
                        <span class="flex-1 truncate">{{ $t['label'] }}</span>
                        @if(!empty($t['badge']) && $t['badge'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full {{ $badgeClass($t['badge'], $t['badgeColor'] ?? 'slate') }}">{{ $t['badge'] }}</span>
                        @endif
                    </button>
                @endforeach

                {{-- ===== Angebote mit Drilldown ===== --}}
                <div x-data="{ open: @js($activeTab === 'angebote') }">
                    <button type="button" @click="open = !open" x-on:dblclick="$wire.set('activeTab', 'angebote')"
                            wire:click="$set('activeTab', 'angebote')"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-md transition text-left
                                   {{ $activeTab === 'angebote' ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @svg('heroicon-o-document-duplicate', 'w-4 h-4 flex-shrink-0')
                        <span class="flex-1 truncate">Angebote</span>
                        @if($counts['angebote_items'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]">{{ $counts['angebote_items'] }}</span>
                        @endif
                        <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 transition-transform text-[var(--ui-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    <div x-show="open" x-cloak class="ml-3 border-l border-[var(--ui-border)]/40 pl-2 space-y-0.5 mt-0.5">
                        <button type="button" wire:click="$set('activeTab', 'angebote')"
                                class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-[0.72rem] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                            @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5 flex-shrink-0')
                            <span class="flex-1 text-left">Alle Artikel</span>
                            @if($counts['angebote_positionen'] > 0)
                                <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ $counts['angebote_positionen'] }}</span>
                            @endif
                        </button>

                        @foreach($quoteTree as $dayNode)
                            <div x-data="{ sub: false }">
                                <button type="button" @click="sub = !sub"
                                        wire:click="$set('activeTab', 'angebote')"
                                        class="w-full flex items-center gap-1.5 px-2 py-1 rounded-md text-[0.72rem] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $dayNode['color'] }}"></span>
                                    <span class="flex-1 text-left truncate font-mono text-[0.68rem]">{{ $dayNode['datum'] ?? $dayNode['label'] }}</span>
                                    @if($dayNode['positions'] > 0)
                                        <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full bg-slate-200 text-slate-700">{{ $dayNode['positions'] }}</span>
                                    @endif
                                    @if(count($dayNode['types']) > 0)
                                        <svg :class="sub ? 'rotate-90' : ''" class="w-2.5 h-2.5 transition-transform text-[var(--ui-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    @endif
                                </button>
                                @if(count($dayNode['types']) > 0)
                                    <div x-show="sub" x-cloak class="ml-4 space-y-0.5 mt-0.5">
                                        @foreach($dayNode['types'] as $type)
                                            <button type="button" wire:click="$set('activeTab', 'angebote')"
                                                    class="w-full flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[0.66rem] text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)]">
                                                <span class="w-1 h-1 rounded-full bg-[var(--ui-muted)] flex-shrink-0"></span>
                                                <span class="flex-1 text-left truncate">{{ $type['typ'] }}</span>
                                                @if($type['positions'] > 0)
                                                    <span class="text-[0.58rem] text-[var(--ui-muted)]">{{ $type['positions'] }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ===== Bestellungen mit Drilldown ===== --}}
                <div x-data="{ open: @js($activeTab === 'bestellungen') }">
                    <button type="button" @click="open = !open"
                            wire:click="$set('activeTab', 'bestellungen')"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-md transition text-left
                                   {{ $activeTab === 'bestellungen' ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @svg('heroicon-o-shopping-cart', 'w-4 h-4 flex-shrink-0')
                        <span class="flex-1 truncate">Bestellungen</span>
                        @if($counts['bestellungen_items'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]">{{ $counts['bestellungen_items'] }}</span>
                        @endif
                        <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 transition-transform text-[var(--ui-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    <div x-show="open" x-cloak class="ml-3 border-l border-[var(--ui-border)]/40 pl-2 space-y-0.5 mt-0.5">
                        <button type="button" wire:click="$set('activeTab', 'bestellungen')"
                                class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-[0.72rem] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                            @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5 flex-shrink-0')
                            <span class="flex-1 text-left">Alle Positionen</span>
                            @if($counts['bestellungen_positionen'] > 0)
                                <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ $counts['bestellungen_positionen'] }}</span>
                            @endif
                        </button>

                        @foreach($orderTree as $dayNode)
                            <div x-data="{ sub: false }">
                                <button type="button" @click="sub = !sub"
                                        wire:click="$set('activeTab', 'bestellungen')"
                                        class="w-full flex items-center gap-1.5 px-2 py-1 rounded-md text-[0.72rem] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $dayNode['color'] }}"></span>
                                    <span class="flex-1 text-left truncate font-mono text-[0.68rem]">{{ $dayNode['datum'] ?? $dayNode['label'] }}</span>
                                    @if($dayNode['positions'] > 0)
                                        <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full bg-slate-200 text-slate-700">{{ $dayNode['positions'] }}</span>
                                    @endif
                                    @if(count($dayNode['types']) > 0)
                                        <svg :class="sub ? 'rotate-90' : ''" class="w-2.5 h-2.5 transition-transform text-[var(--ui-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    @endif
                                </button>
                                @if(count($dayNode['types']) > 0)
                                    <div x-show="sub" x-cloak class="ml-4 space-y-0.5 mt-0.5">
                                        @foreach($dayNode['types'] as $type)
                                            <button type="button" wire:click="$set('activeTab', 'bestellungen')"
                                                    class="w-full flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[0.66rem] text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)]">
                                                <span class="w-1 h-1 rounded-full bg-[var(--ui-muted)] flex-shrink-0"></span>
                                                <span class="flex-1 text-left truncate">{{ $type['typ'] }}</span>
                                                @if($type['positions'] > 0)
                                                    <span class="text-[0.58rem] text-[var(--ui-muted)]">{{ $type['positions'] }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @foreach($tailTabs as $t)
                    <button wire:click="$set('activeTab', '{{ $t['key'] }}')" type="button"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-md transition text-left
                                   {{ $activeTab === $t['key']
                                      ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold'
                                      : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @svg($t['icon'], 'w-4 h-4 flex-shrink-0')
                        <span class="flex-1 truncate">{{ $t['label'] }}</span>
                        @if(!empty($t['badge']) && $t['badge'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full {{ $badgeClass($t['badge'], $t['badgeColor'] ?? 'slate') }}">{{ $t['badge'] }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events', 'route' => 'events.dashboard'],
            ['label' => 'Veranstaltungen', 'route' => 'events.manage'],
            ['label' => $event->event_number],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" wire:click="duplicate"
                         wire:confirm="Veranstaltung „{{ $event->name }}“ als Kopie anlegen?">
                <span class="flex items-center gap-2">
                    @svg('heroicon-o-document-duplicate', 'w-4 h-4')
                    Duplizieren
                </span>
            </x-ui-button>
        </x-ui-page-actionbar>

        {{-- Header --}}
        <div class="mt-4 bg-white border border-[var(--ui-border)] rounded-lg p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 text-[0.68rem] font-mono text-[var(--ui-muted)] mb-1">
                        <span class="font-bold text-[var(--ui-primary)]">{{ $event->event_number }}</span>
                        @if($event->status_changed_at)
                            <span>· Status geändert {{ $event->status_changed_at->diffForHumans() }}</span>
                        @endif
                    </div>
                    <h1 class="text-xl font-bold text-[var(--ui-secondary)] truncate">{{ $event->name }}</h1>
                    <div class="mt-1 flex items-center gap-3 text-xs text-[var(--ui-muted)] flex-wrap">
                        @if($event->customer)
                            <span class="flex items-center gap-1">
                                @svg('heroicon-o-user', 'w-3.5 h-3.5')
                                {{ $event->customer }}
                            </span>
                        @endif
                        @if($event->start_date)
                            <span class="flex items-center gap-1 font-mono">
                                @svg('heroicon-o-calendar', 'w-3.5 h-3.5')
                                {{ $event->start_date->format('d.m.Y') }}
                                @if($event->end_date && $event->end_date != $event->start_date)
                                    – {{ $event->end_date->format('d.m.Y') }}
                                @endif
                            </span>
                        @endif
                        @if($event->responsible)
                            <span class="flex items-center gap-1">
                                @svg('heroicon-o-user-circle', 'w-3.5 h-3.5')
                                {{ $event->responsible }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full {{ $statusClass }}">
                        {{ $currentStatus }}
                    </span>
                    <select wire:change="setStatus($event.target.value); $event.target.blur()"
                            class="border border-[var(--ui-border)] rounded-md px-2 py-1 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <option value="">Status ändern …</option>
                        @foreach($statusOptions as $s)
                            <option value="{{ $s }}" @if($currentStatus === $s) selected @endif>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ================= Tab: Basis (3-Spalten-Layout analog Alt) ================= --}}
        @if($activeTab === 'basis')
            <div class="pt-4 grid grid-cols-1 lg:grid-cols-12 gap-4">

                {{-- ========== Spalte 1: Termine ========== --}}
                <div class="lg:col-span-4 space-y-4">
                    <x-ui-panel title="Termine" subtitle="{{ $days->count() }} Tag(e)">
                        <div class="p-2 flex justify-end border-b border-[var(--ui-border)]">
                            <x-ui-button variant="secondary-outline" size="sm" wire:click="openDayCreate">
                                @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Tag
                            </x-ui-button>
                        </div>
                        @if($days->isEmpty())
                            <div class="p-8 text-center border border-dashed border-[var(--ui-border)]/40 rounded-md m-3">
                                @svg('heroicon-o-calendar', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-xs text-[var(--ui-muted)]">Keine Termine</p>
                            </div>
                        @else
                            <div class="divide-y divide-[var(--ui-border)]/30">
                                @foreach($days as $day)
                                    <div class="p-2 flex items-center gap-2 hover:bg-[var(--ui-muted-5)]/40 group cursor-pointer"
                                         wire:click="openDayEdit('{{ $day->uuid }}')">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $day->color }}"></span>
                                        <span class="text-[0.58rem] font-bold text-[var(--ui-muted)] w-5 text-center flex-shrink-0">{{ $day->day_of_week ?? '' }}</span>
                                        <span class="text-xs font-mono text-[var(--ui-secondary)] flex-1 truncate">{{ $day->datum?->format('d.m.Y') ?: '—' }}</span>
                                        <span class="text-[0.62rem] font-mono text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded">
                                            {{ $day->von ?: '—' }}–{{ $day->bis ?: '—' }}
                                        </span>
                                        @if($day->pers_von || $day->pers_bis)
                                            <span class="text-[0.62rem] font-mono text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded flex items-center gap-1">
                                                @svg('heroicon-o-users', 'w-2.5 h-2.5')
                                                {{ $day->pers_von ?: '?' }}–{{ $day->pers_bis ?: '?' }}
                                            </span>
                                        @endif
                                        <span class="text-[0.58rem] font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded-full">{{ $day->day_status }}</span>
                                        <button wire:click.stop="deleteDay('{{ $day->uuid }}')" wire:confirm="Tag löschen?"
                                                class="opacity-0 group-hover:opacity-100 text-red-500 hover:bg-red-50 p-1 rounded flex-shrink-0">
                                            @svg('heroicon-o-trash', 'w-3 h-3')
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-ui-panel>
                </div>

                {{-- ========== Spalte 2: Stammdaten-Cards ========== --}}
                <div class="lg:col-span-4 space-y-4">

                    <x-ui-panel title="Basis" subtitle="Name, Kunde, Zeitraum, Status">
                        <div class="p-4 space-y-3">
                            <div>
                                <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Name *</label>
                                <input wire:model.blur="event.name" type="text"
                                       class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                @error('event.name') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Kunde</label>
                                    <input wire:model.blur="event.customer" type="text"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Status</label>
                                    <select wire:model.blur="event.status"
                                            class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                        @foreach($statusOptions as $s)
                                            <option value="{{ $s }}">{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Start</label>
                                    <input wire:model.blur="event.start_date" type="date"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Ende</label>
                                    <input wire:model.blur="event.end_date" type="date"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                            </div>
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Veranstalter">
                        <div class="p-4 space-y-2">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Unternehmen</label>
                            <input wire:model.blur="event.organizer_for_whom" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Ansprechpartner</label>
                                    <input wire:model.blur="event.organizer_contact" type="text"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Vor Ort</label>
                                    <input wire:model.blur="event.organizer_contact_onsite" type="text"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                            </div>
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Besteller">
                        <div class="p-4 space-y-2">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Bestellt über</label>
                            <div class="flex gap-1 bg-[var(--ui-muted-5)] rounded-md p-1 w-fit">
                                @foreach([
                                    'mail' => ['icon' => 'heroicon-o-envelope',    'label' => 'E-Mail'],
                                    'phone' => ['icon' => 'heroicon-o-phone',      'label' => 'Telefon'],
                                    'meeting' => ['icon' => 'heroicon-o-user-group','label' => 'Termin'],
                                    'referral' => ['icon' => 'heroicon-o-link',    'label' => 'Empfehlung'],
                                    'other' => ['icon' => 'heroicon-o-ellipsis-horizontal', 'label' => 'Sonstiges'],
                                ] as $via => $meta)
                                    <button type="button" wire:click="$set('event.orderer_via', '{{ $via }}')"
                                            title="{{ $meta['label'] }}"
                                            class="p-1.5 rounded transition
                                                   {{ ($event->orderer_via ?? 'mail') === $via
                                                      ? 'bg-white shadow-sm text-[var(--ui-primary)]'
                                                      : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}">
                                        @svg($meta['icon'], 'w-3.5 h-3.5')
                                    </button>
                                @endforeach
                            </div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Unternehmen</label>
                            <input wire:model.blur="event.orderer_company" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Ansprechpartner</label>
                            <input wire:model.blur="event.orderer_contact" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Rechnung">
                        <div class="p-4 space-y-2">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Rechnung an</label>
                            <input wire:model.blur="event.invoice_to" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Ansprechpartner</label>
                                    <input wire:model.blur="event.invoice_contact" type="text"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Rechnungsdatum</label>
                                    <select wire:model.blur="event.invoice_date_type"
                                            class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                        <option value="">— wählen —</option>
                                        @foreach($days as $d)
                                            <option value="{{ $d->datum?->format('Y-m-d') }}">{{ $d->datum?->format('d.m.Y') }}{{ $d->day_of_week ? ' ('.$d->day_of_week.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Zuständigkeit">
                        <div class="p-4 space-y-2">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Verantwortlich</label>
                            <input wire:model.blur="event.responsible" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Kostenstelle</label>
                                    @if(!empty($settings['cost_centers']))
                                        <select wire:model.blur="event.cost_center"
                                                class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                            <option value="">—</option>
                                            @foreach($settings['cost_centers'] as $cc)
                                                <option value="{{ $cc }}">{{ $cc }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input wire:model.blur="event.cost_center" type="text"
                                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    @endif
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Kostenträger</label>
                                    <input wire:model.blur="event.cost_carrier" type="text" placeholder="{{ $event->event_number }}"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                            </div>
                        </div>
                    </x-ui-panel>
                </div>

                {{-- ========== Spalte 3: Anlass / Eingang / Lieferung / Weiterleitung / Notizen ========== --}}
                <div class="lg:col-span-4 space-y-4">

                    <x-ui-panel title="Anlass">
                        <div class="p-4 space-y-2">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Anlassgruppe</label>
                            <input wire:model.blur="event.group" type="text" placeholder="z.B. Messe"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Anlass</label>
                            @if(!empty($settings['event_types']))
                                <select wire:model.blur="event.event_type"
                                        class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <option value="">—</option>
                                    @foreach($settings['event_types'] as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input wire:model.blur="event.event_type" type="text"
                                       class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @endif
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Ort (freitext)</label>
                            <input wire:model.blur="event.location" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Eingang">
                        <div class="p-4 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Datum</label>
                                    <input wire:model.blur="event.inquiry_date" type="date"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Uhrzeit</label>
                                    <input wire:model.blur="event.inquiry_time" type="text" placeholder="10:00"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                            </div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Potenzialanalyse</label>
                            @php $pct = (int) preg_replace('/[^0-9]/', '', (string) ($event->potential ?? '')); @endphp
                            <div x-data="{
                                    pct: {{ $pct }},
                                    bg()   { return this.pct >= 70 ? '#dcfce7' : this.pct >= 50 ? '#fef3c7' : this.pct >= 30 ? '#ffedd5' : this.pct >= 10 ? '#fee2e2' : '#f1f5f9'; },
                                    fill() { return this.pct >= 90 ? '#16a34a' : this.pct >= 70 ? '#22c55e' : this.pct >= 50 ? '#f59e0b' : this.pct >= 30 ? '#f97316' : this.pct >= 10 ? '#ef4444' : '#cbd5e1'; },
                                    txt()  { return this.pct >= 90 ? '#15803d' : this.pct >= 70 ? '#16a34a' : this.pct >= 50 ? '#a16207' : this.pct >= 30 ? '#c2410c' : this.pct >= 10 ? '#dc2626' : '#94a3b8'; }
                                 }">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="flex-1 h-2 rounded-full overflow-hidden" :style="'background:' + bg()">
                                        <div class="h-full rounded-full transition-all duration-300" :style="'width:' + pct + '%; background:' + fill()"></div>
                                    </div>
                                    <span class="text-xs font-bold font-mono min-w-[2.5rem] text-right" :style="'color:' + txt()" x-text="pct ? pct + '%' : '—'"></span>
                                </div>
                                <select wire:model.blur="event.potential"
                                        @change="pct = parseInt($event.target.value) || 0"
                                        class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <option value="">— bitte wählen —</option>
                                    <option value="10% (unwahrscheinlich)">10% (unwahrscheinlich)</option>
                                    <option value="30% (unverbindliche Anfrage)">30% (unverbindliche Anfrage)</option>
                                    <option value="50% (Tendenz offen)">50% (Tendenz offen)</option>
                                    <option value="70% (deutliche Tendenz zur Buchung)">70% (deutliche Tendenz zur Buchung)</option>
                                    <option value="90% (ziemlich definitiv)">90% (ziemlich definitiv)</option>
                                </select>
                            </div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mt-2">Notiz zur Anfrage</label>
                            <input wire:model.blur="event.inquiry_note" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Lieferung & Wiedervorlage">
                        <div class="p-4 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Wiedervorlage am</label>
                                    <input wire:model.blur="event.follow_up_date" type="date"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Lieferant</label>
                                    <input wire:model.blur="event.delivery_supplier" type="text"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                            </div>
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Lieferkontakt</label>
                            <input wire:model.blur="event.delivery_contact" type="text"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block">Notiz</label>
                            <textarea wire:model.blur="event.follow_up_note" rows="2"
                                      class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Weiterleitung">
                        <div class="p-4 grid grid-cols-3 gap-2">
                            <div class="flex items-center">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input wire:model.blur="event.forwarded" type="checkbox" class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                                    <span class="text-xs text-[var(--ui-secondary)]">aktiv</span>
                                </label>
                            </div>
                            <div>
                                <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Datum</label>
                                <input wire:model.blur="event.forwarding_date" type="date"
                                       class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            </div>
                            <div>
                                <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Uhrzeit</label>
                                <input wire:model.blur="event.forwarding_time" type="text" placeholder="14:30"
                                       class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            </div>
                        </div>
                    </x-ui-panel>

                    <x-ui-panel title="Liefertext" subtitle="Sichtbar für Auslieferung">
                        @include('events::partials.note-stream', ['type' => 'liefertext', 'notes' => $notesByType->get('liefertext', collect())])
                    </x-ui-panel>

                    <x-ui-panel title="Erste Absprache">
                        @include('events::partials.note-stream', ['type' => 'absprache', 'notes' => $notesByType->get('absprache', collect())])
                    </x-ui-panel>
                </div>
            </div>
        @endif

        {{-- ================= Tab: Details ================= --}}
        @if($activeTab === 'details')
            <div class="pt-4 space-y-4">

                <x-ui-panel title="Interne Infos" subtitle="Nur intern sichtbar, nicht auf Kunden-PDFs">
                    @include('events::partials.note-stream', ['type' => 'intern', 'notes' => $notesByType->get('intern', collect())])
                </x-ui-panel>

                    <x-ui-panel title="Projektverantwortung" subtitle="Unterschriften-Namen + digitale Freigabe">
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Unterschrift links (Projektverantwortlicher)</label>
                                    <input wire:model="event.sign_left" type="text" placeholder="Max Mustermann"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Unterschrift rechts (Vorgesetzter)</label>
                                    <input wire:model="event.sign_right" type="text" placeholder="Erika Mustermann"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-[var(--ui-border)]">
                                @foreach(['left' => 'Links (Projektverantwortlicher)', 'right' => 'Rechts (Vorgesetzter)'] as $role => $roleLabel)
                                    @php $sig = $signatures->get($role); @endphp
                                    <div class="border border-[var(--ui-border)] rounded-md p-3 bg-[var(--ui-muted-5)]/40">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">{{ $roleLabel }}</span>
                                            @if($sig)
                                                <span class="text-[0.62rem] text-green-600 font-semibold flex items-center gap-1">
                                                    @svg('heroicon-o-check-badge', 'w-3.5 h-3.5') unterzeichnet
                                                </span>
                                            @else
                                                <span class="text-[0.62rem] text-[var(--ui-muted)]">offen</span>
                                            @endif
                                        </div>
                                        @if($sig)
                                            <img src="{{ $sig->signature_image }}" alt="Unterschrift" class="max-h-20 w-full object-contain border border-[var(--ui-border)] rounded-md bg-white">
                                            <p class="text-[0.58rem] text-[var(--ui-muted)] font-mono mt-2">
                                                {{ $sig->user?->name ?? '—' }} · {{ $sig->signed_at?->format('d.m.Y H:i') }}
                                            </p>
                                            <p class="text-[0.56rem] text-[var(--ui-muted)] font-mono truncate" title="Dokumenten-Hash (SHA-256): {{ $sig->document_hash }}">
                                                Hash: {{ substr($sig->document_hash, 0, 24) }}…
                                            </p>
                                            <button type="button" onclick="resetSignature('{{ $event->slug }}', '{{ $role }}')"
                                                    class="mt-2 text-[0.62rem] text-red-600 hover:underline">
                                                Unterschrift zurücksetzen
                                            </button>
                                        @else
                                            <div class="flex flex-col items-center justify-center gap-2 py-4 border border-dashed border-[var(--ui-border)] rounded-md bg-white">
                                                <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch nicht unterzeichnet</p>
                                                <button type="button"
                                                        onclick="openSignaturePad('{{ $event->slug }}', '{{ $role }}')"
                                                        class="px-3 py-1.5 text-xs font-semibold bg-[var(--ui-primary)] text-white rounded-md hover:opacity-90 flex items-center gap-1.5">
                                                    @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5') Jetzt unterschreiben
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-ui-panel>

                    {{-- Management Report – Status-Cockpit --}}
                    @php
                        $mrTotal = 0; $mrDone = 0;
                        foreach ($mrFields as $group => $fields) {
                            foreach ($fields as $f) {
                                $mrTotal++;
                                $val = $event->mr_data[$f['key']] ?? null;
                                if ($val && !in_array(strtolower($val), ['', 'fehlende eingabe', 'noch nicht erstellt', 'unbekannt (pl)', 'keine rechnung', 'n/a', 'nicht benötigt'], true)) {
                                    $mrDone++;
                                }
                            }
                        }
                        $mrProgress = $mrTotal > 0 ? round(($mrDone / $mrTotal) * 100) : 0;

                        // Farblogik fuer Status-Badges (first option = rot, letzte = gruen)
                        $badgeFor = function ($options, $value) {
                            $count = count($options);
                            if (!$value) return 'bg-slate-200 text-slate-700';
                            $idx = array_search($value, $options, true);
                            if ($idx === false) return 'bg-slate-200 text-slate-700';
                            if ($idx === 0) return 'bg-red-100 text-red-700';
                            if ($idx === $count - 1) return 'bg-green-100 text-green-700';
                            return 'bg-yellow-100 text-yellow-800';
                        };
                    @endphp

                    <x-ui-panel>
                        <div class="p-4 flex items-center justify-between flex-wrap gap-3 border-b border-[var(--ui-border)]">
                            <div class="flex items-center gap-2">
                                <span class="w-1 h-4 rounded-full" style="background: #f59e0b"></span>
                                <h3 class="text-sm font-bold text-[var(--ui-secondary)]">Management Report</h3>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2 text-[0.6rem] text-[var(--ui-muted)]">
                                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>offen</span>
                                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>läuft</span>
                                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>erledigt</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 bg-[var(--ui-muted-5)] rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full transition-all" style="width: {{ $mrProgress }}%"></div>
                                    </div>
                                    <span class="text-[0.62rem] font-bold text-[var(--ui-muted)] font-mono">{{ $mrDone }}/{{ $mrTotal }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 space-y-4">
                            @foreach($mrFields as $group => $fields)
                                <div>
                                    <p class="text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2">{{ $group }}</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($fields as $f)
                                            @php $value = $event->mr_data[$f['key']] ?? $f['options'][0]; @endphp
                                            <div x-data="{ open: false }" class="relative">
                                                <button type="button" @click="open = !open"
                                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 bg-white border border-[var(--ui-border)] rounded-md hover:border-[var(--ui-primary)]/40 text-left">
                                                    <span class="text-[0.62rem] font-semibold text-[var(--ui-secondary)] leading-tight flex-1 truncate">{{ $f['label'] }}</span>
                                                    <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full {{ $badgeFor($f['options'], $value) }} flex-shrink-0 truncate max-w-[100px]" title="{{ $value }}">{{ $value }}</span>
                                                </button>
                                                <div x-show="open" x-cloak @click.outside="open = false"
                                                     class="absolute top-full left-0 right-0 mt-1 z-50 bg-white border border-[var(--ui-border)] rounded-md shadow-lg p-1">
                                                    @foreach($f['options'] as $opt)
                                                        <button type="button"
                                                                wire:click="setMrField('{{ $f['key'] }}', @js($opt))"
                                                                @click="open = false"
                                                                class="w-full flex items-center gap-2 px-2 py-1.5 text-[0.68rem] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] rounded text-left">
                                                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $badgeFor($f['options'], $opt) }}"></span>
                                                            <span class="flex-1 truncate">{{ $opt }}</span>
                                                            @if($opt === $value)
                                                                @svg('heroicon-o-check', 'w-3 h-3 text-[var(--ui-primary)] flex-shrink-0')
                                                            @endif
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui-panel>
            </div>
        @endif

        {{-- ================= Tab: Aktivitäten ================= --}}
        @if($activeTab === 'aktivitaeten')
            <div class="pt-6">
                <livewire:events.detail.activities :event-id="$event->id" :key="'activities-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Angebote ================= --}}
        @if($activeTab === 'angebote')
            <div class="pt-6">
                <livewire:events.detail.quotes :event-id="$event->id" :key="'quotes-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Bestellungen ================= --}}
        @if($activeTab === 'bestellungen')
            <div class="pt-6">
                <livewire:events.detail.orders :event-id="$event->id" :key="'orders-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Verträge ================= --}}
        @if($activeTab === 'vertraege')
            <div class="pt-6">
                <livewire:events.detail.contracts :event-id="$event->id" :key="'contracts-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Rechnungen ================= --}}
        @if($activeTab === 'rechnungen')
            <div class="pt-6">
                <livewire:events.detail.invoices :event-id="$event->id" :key="'invoices-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Packliste ================= --}}
        @if($activeTab === 'packliste')
            <div class="pt-6">
                <livewire:events.detail.pick-lists :event-id="$event->id" :key="'picklists-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Kommunikation ================= --}}
        @if($activeTab === 'kommunikation')
            <div class="pt-6">
                <livewire:events.detail.communication :event-id="$event->id" :key="'comm-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Kalkulation ================= --}}
        @if($activeTab === 'kalkulation')
            <div class="pt-6">
                <livewire:events.detail.calculation :event-id="$event->id" :key="'calc-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Projekt-Function ================= --}}
        @if($activeTab === 'projekt')
            <div class="pt-6">
                <livewire:events.detail.projekt-function :event-id="$event->id" :key="'pf-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Schlussbericht ================= --}}
        @if($activeTab === 'schluss')
            <div class="pt-6">
                <livewire:events.detail.final-report :event-id="$event->id" :key="'fr-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Feedback ================= --}}
        @if($activeTab === 'feedback')
            <div class="pt-6">
                <livewire:events.detail.feedback :event-id="$event->id" :key="'feedback-'.$event->id" />
            </div>
        @endif

        {{-- Tage-Tab entfernt: Termine werden im Basis-Tab inline verwaltet. --}}
        {{-- Die openDayCreate/openDayEdit/deleteDay-Actions bleiben unveraendert verfuegbar
             ueber die Basis-Spalte 1 und das weiter unten definierte Day-Modal. --}}

        {{-- ================= Tab: Buchungen ================= --}}
        @if($activeTab === 'buchungen')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Raum-Buchungen" subtitle="Welche Räume/Locations werden genutzt">
                    <div class="p-4 flex justify-between items-center border-b border-[var(--ui-border)]">
                        <p class="text-xs text-[var(--ui-muted)]">{{ $bookings->count() }} Buchung(en)</p>
                        <x-ui-button variant="primary" size="sm" wire:click="openBookingCreate">
                            <span class="flex items-center gap-1.5">
                                @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                Buchung
                            </span>
                        </x-ui-button>
                    </div>

                    @if($bookings->isEmpty())
                        <div class="p-12 text-center">
                            @svg('heroicon-o-building-office-2', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                            <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Noch keine Buchungen</p>
                            <p class="text-xs text-[var(--ui-muted)]">Füge eine Raum-Buchung hinzu.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Raum</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Datum</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Von–Bis</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Pers.</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Bestuhlung</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Optionsrang</th>
                                        <th class="px-3 py-2 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bookings as $b)
                                        <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60">
                                            <td class="px-3 py-2">
                                                @if($b->location)
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="text-xs font-mono font-bold text-[var(--ui-secondary)]">{{ $b->location->kuerzel }}</span>
                                                        <span class="text-[0.62rem] text-[var(--ui-muted)]">{{ $b->location->name }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-xs font-mono text-[var(--ui-muted)]">{{ $b->raum ?: '—' }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">{{ $b->datum ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">
                                                {{ $b->beginn ?: '—' }}{{ $b->ende ? ' – ' . $b->ende : '' }}
                                            </td>
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">{{ $b->pers ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $b->bestuhlung ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $b->optionsrang }}</td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-end gap-1">
                                                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openBookingEdit('{{ $b->uuid }}')">
                                                        @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                                    </x-ui-button>
                                                    <x-ui-button variant="danger-outline" size="sm"
                                                                 wire:click="deleteBooking('{{ $b->uuid }}')"
                                                                 wire:confirm="Buchung löschen?">
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

        {{-- ================= Tab: Ablauf ================= --}}
        @if($activeTab === 'ablauf')
            <div class="pt-6 space-y-4">
                <x-ui-panel title="Ablaufplan" subtitle="Zeitleiste der Veranstaltung">
                    <div class="p-4 flex justify-between items-center border-b border-[var(--ui-border)]">
                        <p class="text-xs text-[var(--ui-muted)]">{{ $schedule->count() }} Eintrag/Einträge</p>
                        <x-ui-button variant="primary" size="sm" wire:click="openScheduleCreate">
                            <span class="flex items-center gap-1.5">
                                @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                Eintrag
                            </span>
                        </x-ui-button>
                    </div>

                    @if($schedule->isEmpty())
                        <div class="p-12 text-center">
                            @svg('heroicon-o-clock', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                            <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Ablaufplan ist leer</p>
                            <p class="text-xs text-[var(--ui-muted)]">Füge den ersten Programmpunkt hinzu.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Datum</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Von–Bis</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Beschreibung</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Raum</th>
                                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Bemerkung</th>
                                        <th class="px-3 py-2 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schedule as $item)
                                        <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60">
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">{{ $item->datum ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">
                                                {{ $item->von ?: '—' }}{{ $item->bis ? ' – ' . $item->bis : '' }}
                                            </td>
                                            <td class="px-3 py-2 text-xs font-semibold text-[var(--ui-secondary)]">{{ $item->beschreibung }}</td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $item->raum ?: '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $item->bemerkung ?: '—' }}</td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center justify-end gap-1">
                                                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openScheduleEdit('{{ $item->uuid }}')">
                                                        @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                                    </x-ui-button>
                                                    <x-ui-button variant="danger-outline" size="sm"
                                                                 wire:click="deleteSchedule('{{ $item->uuid }}')"
                                                                 wire:confirm="Eintrag löschen?">
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

        {{-- Notizen-Tab entfernt: Notizen werden inline in Basis (liefertext/absprache)
             und in Details (intern) als Streams verwaltet. --}}
        {{-- MR-Tab entfernt: Management-Report liegt jetzt im Details-Tab (Status-Cockpit). --}}

        {{-- ================= Modal: Day ================= --}}
        <x-ui-modal wire:model="showDayModal" size="md" :hideFooter="true">
            <x-slot name="header">{{ $editingDayUuid ? 'Tag bearbeiten' : 'Neuer Tag' }}</x-slot>

            <form wire:submit.prevent="saveDay" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Label *</label>
                        <input wire:model="dayForm.label" type="text" placeholder="Tag 1 / Aufbau"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('dayForm.label') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Datum *</label>
                        <input wire:model="dayForm.datum" type="date"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('dayForm.datum') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Von</label>
                        <input wire:model="dayForm.von" type="text" placeholder="10:00"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bis</label>
                        <input wire:model="dayForm.bis" type="text" placeholder="18:00"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Pers. von</label>
                        <input wire:model="dayForm.pers_von" type="text" placeholder="50"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Pers. bis</label>
                        <input wire:model="dayForm.pers_bis" type="text" placeholder="80"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Status</label>
                        <select wire:model="dayForm.day_status"
                                class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @foreach($statusOptions as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Farbe</label>
                        <input wire:model="dayForm.color" type="color"
                               class="w-full h-[34px] border border-[var(--ui-border)] rounded-md cursor-pointer">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeDayModal">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

        {{-- ================= Modal: Booking ================= --}}
        <x-ui-modal wire:model="showBookingModal" size="md" :hideFooter="true">
            <x-slot name="header">{{ $editingBookingUuid ? 'Buchung bearbeiten' : 'Neue Buchung' }}</x-slot>

            <form wire:submit.prevent="saveBooking" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Location</label>
                    <select wire:model="bookingForm.location_id"
                            class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <option value="">— Keine Location (freitext Raum) —</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->kuerzel }} — {{ $loc->name }}</option>
                        @endforeach
                    </select>
                    @error('bookingForm.location_id') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Raum (freitext, Fallback)</label>
                    <input wire:model="bookingForm.raum" type="text" placeholder="z.B. BLUE — nur nutzen wenn keine Location passt"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Datum</label>
                        <input wire:model="bookingForm.datum" type="date"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Von</label>
                        <input wire:model="bookingForm.beginn" type="text" placeholder="10:00"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bis</label>
                        <input wire:model="bookingForm.ende" type="text" placeholder="18:00"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Personen</label>
                        <input wire:model="bookingForm.pers" type="text" placeholder="80"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bestuhlung</label>
                        @if(!empty($settings['bestuhlung']))
                            <select wire:model="bookingForm.bestuhlung"
                                    class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                <option value="">—</option>
                                @foreach($settings['bestuhlung'] as $b)
                                    <option value="{{ $b }}">{{ $b }}</option>
                                @endforeach
                            </select>
                        @else
                            <input wire:model="bookingForm.bestuhlung" type="text" placeholder="Reihen / Bankett / …"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @endif
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Optionsrang</label>
                        <select wire:model="bookingForm.optionsrang"
                                class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @foreach($bookingRangs as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Absprache</label>
                        <input wire:model="bookingForm.absprache" type="text"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeBookingModal">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

        {{-- ================= Modal: Schedule ================= --}}
        <x-ui-modal wire:model="showScheduleModal" size="md" :hideFooter="true">
            <x-slot name="header">{{ $editingScheduleUuid ? 'Ablauf-Eintrag bearbeiten' : 'Neuer Ablauf-Eintrag' }}</x-slot>

            <form wire:submit.prevent="saveSchedule" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Beschreibung *</label>
                    <input wire:model="scheduleForm.beschreibung" type="text" placeholder="Begrüßung / Empfang / ..."
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    @error('scheduleForm.beschreibung') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Datum</label>
                        <input wire:model="scheduleForm.datum" type="date"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Von</label>
                        <input wire:model="scheduleForm.von" type="text" placeholder="10:00"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bis</label>
                        <input wire:model="scheduleForm.bis" type="text" placeholder="11:00"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Raum</label>
                        <input wire:model="scheduleForm.raum" type="text"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bemerkung</label>
                        <input wire:model="scheduleForm.bemerkung" type="text"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input wire:model="scheduleForm.linked" type="checkbox"
                               class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                        <span class="text-xs text-[var(--ui-secondary)]">Mit Auftrag verknüpft</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeScheduleModal">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

        {{-- ================= Modal: Note ================= --}}
        <x-ui-modal wire:model="showNoteModal" size="md" :hideFooter="true">
            <x-slot name="header">{{ $editingNoteUuid ? 'Notiz bearbeiten' : 'Neue Notiz' }}</x-slot>

            <form wire:submit.prevent="saveNote" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ *</label>
                    <select wire:model="noteForm.type"
                            class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @foreach($noteTypes as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('noteForm.type') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Text *</label>
                    <textarea wire:model="noteForm.text" rows="6"
                              class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                    @error('noteForm.text') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Autor</label>
                    <input wire:model="noteForm.user_name" type="text"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>
                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeNoteModal">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

        {{-- ================= Signatur-Pad (nativ, ohne Livewire) ================= --}}
        <div id="signature-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:white; border-radius:10px; padding:20px; width:95%; max-width:560px; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h3 style="font-size:1rem; font-weight:700; color:#1e293b; margin:0;" id="signature-title">Unterschrift</h3>
                    <button type="button" onclick="closeSignaturePad()" style="font-size:1.3rem; line-height:1; color:#64748b; background:none; border:none; cursor:pointer;">×</button>
                </div>
                <p style="font-size:0.75rem; color:#64748b; margin:0 0 10px 0;">Zeichnen Sie mit Maus oder Touch in das Feld. Der SHA-256 Hash des Events wird automatisch erfasst.</p>
                <canvas id="signature-canvas" width="520" height="180" style="border:1px solid #cbd5e1; border-radius:6px; background:#f8fafc; touch-action:none; width:100%; height:180px; cursor:crosshair;"></canvas>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; gap:8px;">
                    <button type="button" onclick="clearSignature()" style="padding:8px 14px; border:1px solid #cbd5e1; background:white; color:#475569; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                        Löschen
                    </button>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeSignaturePad()" style="padding:8px 14px; border:1px solid #cbd5e1; background:white; color:#475569; border-radius:6px; font-size:0.75rem; cursor:pointer;">
                            Abbrechen
                        </button>
                        <button type="button" onclick="submitSignature()" style="padding:8px 18px; background:#16a34a; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                            Unterschrift speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @once
        <script>
            (function () {
                const state = { eventSlug: null, role: null, drawing: false, lastX: 0, lastY: 0, canvas: null, ctx: null };

                function getCsrfToken() {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    return meta ? meta.getAttribute('content') : '';
                }

                function initCanvas() {
                    const canvas = document.getElementById('signature-canvas');
                    if (!canvas) return null;
                    const ctx = canvas.getContext('2d');
                    ctx.lineWidth = 2.2;
                    ctx.lineCap = 'round';
                    ctx.strokeStyle = '#1e293b';
                    return { canvas, ctx };
                }

                function pointerPos(e) {
                    const rect = state.canvas.getBoundingClientRect();
                    const scaleX = state.canvas.width / rect.width;
                    const scaleY = state.canvas.height / rect.height;
                    const x = (e.clientX ?? (e.touches && e.touches[0].clientX) ?? 0) - rect.left;
                    const y = (e.clientY ?? (e.touches && e.touches[0].clientY) ?? 0) - rect.top;
                    return { x: x * scaleX, y: y * scaleY };
                }

                function startDraw(e) {
                    e.preventDefault();
                    state.drawing = true;
                    const p = pointerPos(e);
                    state.lastX = p.x; state.lastY = p.y;
                }

                function draw(e) {
                    if (!state.drawing) return;
                    e.preventDefault();
                    const p = pointerPos(e);
                    state.ctx.beginPath();
                    state.ctx.moveTo(state.lastX, state.lastY);
                    state.ctx.lineTo(p.x, p.y);
                    state.ctx.stroke();
                    state.lastX = p.x; state.lastY = p.y;
                }

                function stopDraw() { state.drawing = false; }

                window.openSignaturePad = function (eventSlug, role) {
                    state.eventSlug = eventSlug;
                    state.role = role;
                    const modal = document.getElementById('signature-modal');
                    modal.style.display = 'flex';
                    document.getElementById('signature-title').textContent = role === 'left'
                        ? 'Unterschrift Auftraggeber (links)'
                        : 'Unterschrift Auftragnehmer (rechts)';

                    setTimeout(() => {
                        const init = initCanvas();
                        if (!init) return;
                        state.canvas = init.canvas;
                        state.ctx = init.ctx;
                        clearSignature();

                        state.canvas.addEventListener('mousedown', startDraw);
                        state.canvas.addEventListener('mousemove', draw);
                        state.canvas.addEventListener('mouseup', stopDraw);
                        state.canvas.addEventListener('mouseleave', stopDraw);
                        state.canvas.addEventListener('touchstart', startDraw, { passive: false });
                        state.canvas.addEventListener('touchmove', draw, { passive: false });
                        state.canvas.addEventListener('touchend', stopDraw);
                    }, 30);
                };

                window.closeSignaturePad = function () {
                    document.getElementById('signature-modal').style.display = 'none';
                };

                window.clearSignature = function () {
                    if (!state.ctx) return;
                    state.ctx.clearRect(0, 0, state.canvas.width, state.canvas.height);
                };

                window.submitSignature = function () {
                    if (!state.canvas || !state.eventSlug || !state.role) return;
                    const dataUrl = state.canvas.toDataURL('image/png');

                    fetch(`/events/va/${state.eventSlug}/sign`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        },
                        body: JSON.stringify({ role: state.role, signature: dataUrl }),
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) {
                            closeSignaturePad();
                            window.location.reload();
                        } else {
                            alert('Fehler beim Speichern der Unterschrift.');
                        }
                    })
                    .catch(() => alert('Netzwerkfehler beim Speichern.'));
                };

                window.resetSignature = function (eventSlug, role) {
                    if (!confirm('Unterschrift wirklich zurücksetzen?')) return;

                    fetch(`/events/va/${eventSlug}/sign/${role}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        },
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) window.location.reload();
                    });
                };
            })();
        </script>
        @endonce
    </x-ui-page-container>
</x-ui-page>
