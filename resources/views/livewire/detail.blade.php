<x-ui-page>
    @include('events::partials.sortable-init')
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
        $statusDotMap = [
            'Vertrag'       => 'bg-green-500',
            'Definitiv'     => 'bg-green-400',
            'Option'        => 'bg-yellow-400',
            'Abgeschlossen' => 'bg-slate-400',
            'Storno'        => 'bg-red-500',
            'Warteliste'    => 'bg-orange-400',
            'Tendenz'       => 'bg-purple-400',
        ];
        $statusDotClass = $statusDotMap[$currentStatus] ?? 'bg-slate-400';

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
        <x-ui-page-sidebar title="Event-Modul" width="w-fit min-w-[150px] max-w-[260px]" :defaultOpen="true">
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
                        <span class="flex-1 whitespace-nowrap">{{ $t['label'] }}</span>
                        @if(!empty($t['badge']) && $t['badge'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full {{ $badgeClass($t['badge'], $t['badgeColor'] ?? 'slate') }}">{{ $t['badge'] }}</span>
                        @endif
                    </button>
                @endforeach

                {{-- ===== Angebote mit Drilldown ===== --}}
                @php
                    $quotesRoot = $activeTab === 'angebote' && !$pendingQuoteView && !$pendingQuoteDayId && !$pendingQuoteItemId;
                    $quotesAnyInside = $activeTab === 'angebote';
                @endphp
                <div x-data="{ open: @js($quotesAnyInside) }">
                    <button type="button" @click="open = !open" x-on:dblclick="$wire.resetQuoteView()"
                            wire:click="resetQuoteView"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-md transition text-left
                                   {{ $quotesRoot ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : ($quotesAnyInside ? 'text-[var(--ui-primary)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]') }}">
                        @svg('heroicon-o-document-duplicate', 'w-4 h-4 flex-shrink-0')
                        <span class="flex-1 whitespace-nowrap">Angebote</span>
                        @if($counts['angebote_items'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]">{{ $counts['angebote_items'] }}</span>
                        @endif
                        <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 transition-transform text-[var(--ui-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    @php
                        $isQuotesTab = $activeTab === 'angebote';
                        $qArticlesActive = $isQuotesTab && $pendingQuoteView === 'articles';
                        // Root-Overview nur wenn Angebote-Tab aktiv UND weder Articles/Day/Item
                        $qOverviewActive = $isQuotesTab && !$pendingQuoteView && !$pendingQuoteDayId && !$pendingQuoteItemId;

                        // Day-Id des aktiven Items ermitteln (damit Datum mit-highlighted wird)
                        $qActiveItemDayId = null;
                        if ($pendingQuoteItemId) {
                            foreach ($quoteTree as $dn) {
                                foreach ($dn['types'] as $tp) {
                                    if ($tp['id'] === $pendingQuoteItemId) { $qActiveItemDayId = $dn['day_id']; break 2; }
                                }
                            }
                        }
                    @endphp
                    <div x-show="open" x-cloak class="ml-3 border-l border-[var(--ui-border)]/40 pl-2 space-y-0.5 mt-0.5">
                        <button type="button" wire:click="openQuoteArticles"
                                class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-[0.72rem] transition
                                       {{ $qArticlesActive ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                            @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5 flex-shrink-0')
                            <span class="flex-1 text-left">Alle Positionen</span>
                            @if($counts['angebote_positionen'] > 0)
                                <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full {{ $qArticlesActive ? 'bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]' : 'bg-blue-100 text-blue-700' }}">{{ $counts['angebote_positionen'] }}</span>
                            @endif
                        </button>

                        @foreach($quoteTree as $dayNode)
                            @php
                                $dayActive = $isQuotesTab && ($pendingQuoteDayId === $dayNode['day_id'] || $qActiveItemDayId === $dayNode['day_id']);
                                $dayIsDirect = $isQuotesTab && $pendingQuoteDayId === $dayNode['day_id'];
                            @endphp
                            <div x-data="{ sub: @js($dayActive) }">
                                <button type="button" @click="sub = !sub"
                                        wire:click="openQuoteDay({{ $dayNode['day_id'] }})"
                                        class="w-full flex items-center gap-1.5 px-2 py-1 rounded-md text-[0.72rem] transition
                                               {{ $dayIsDirect ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : ($dayActive ? 'text-[var(--ui-secondary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]') }}">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $dayNode['color'] }}"></span>
                                    <span class="flex-1 text-left whitespace-nowrap font-mono text-[0.68rem]">{{ $dayNode['datum'] ?? $dayNode['label'] }}</span>
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
                                            @php $typeActive = $isQuotesTab && $pendingQuoteItemId === $type['id']; @endphp
                                            <button type="button" wire:click="openQuoteItem({{ $type['id'] }})"
                                                    class="w-full flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[0.66rem] transition
                                                           {{ $typeActive ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] hover:text-[var(--ui-primary)]' }}">
                                                <span class="w-1 h-1 rounded-full flex-shrink-0 {{ $typeActive ? 'bg-[var(--ui-primary)]' : 'bg-[var(--ui-muted)]' }}"></span>
                                                <span class="flex-1 text-left whitespace-nowrap">{{ $type['typ'] }}</span>
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
                @php
                    $ordersRoot = $activeTab === 'bestellungen' && !$pendingOrderView && !$pendingOrderDayId && !$pendingOrderItemId;
                    $ordersAnyInside = $activeTab === 'bestellungen';
                    $oArticlesActive = $ordersAnyInside && $pendingOrderView === 'articles';

                    $oActiveItemDayId = null;
                    if ($pendingOrderItemId) {
                        foreach ($orderTree as $dn) {
                            foreach ($dn['types'] as $tp) {
                                if ($tp['id'] === $pendingOrderItemId) { $oActiveItemDayId = $dn['day_id']; break 2; }
                            }
                        }
                    }
                @endphp
                <div x-data="{ open: @js($ordersAnyInside) }">
                    <button type="button" @click="open = !open"
                            wire:click="resetOrderView"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-md transition text-left
                                   {{ $ordersRoot ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : ($ordersAnyInside ? 'text-[var(--ui-primary)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]') }}">
                        @svg('heroicon-o-shopping-cart', 'w-4 h-4 flex-shrink-0')
                        <span class="flex-1 whitespace-nowrap">Bestellungen</span>
                        @if($counts['bestellungen_items'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]">{{ $counts['bestellungen_items'] }}</span>
                        @endif
                        <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 transition-transform text-[var(--ui-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    <div x-show="open" x-cloak class="ml-3 border-l border-[var(--ui-border)]/40 pl-2 space-y-0.5 mt-0.5">
                        <button type="button" wire:click="openOrderArticles"
                                class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-[0.72rem] transition
                                       {{ $oArticlesActive ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                            @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5 flex-shrink-0')
                            <span class="flex-1 text-left">Alle Positionen</span>
                            @if($counts['bestellungen_positionen'] > 0)
                                <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full {{ $oArticlesActive ? 'bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]' : 'bg-blue-100 text-blue-700' }}">{{ $counts['bestellungen_positionen'] }}</span>
                            @endif
                        </button>

                        @foreach($orderTree as $dayNode)
                            @php
                                $dayActive = $ordersAnyInside && ($pendingOrderDayId === $dayNode['day_id'] || $oActiveItemDayId === $dayNode['day_id']);
                                $dayIsDirect = $ordersAnyInside && $pendingOrderDayId === $dayNode['day_id'];
                            @endphp
                            <div x-data="{ sub: @js($dayActive) }">
                                <button type="button" @click="sub = !sub"
                                        wire:click="openOrderDay({{ $dayNode['day_id'] }})"
                                        class="w-full flex items-center gap-1.5 px-2 py-1 rounded-md text-[0.72rem] transition
                                               {{ $dayIsDirect ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : ($dayActive ? 'text-[var(--ui-secondary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]') }}">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $dayNode['color'] }}"></span>
                                    <span class="flex-1 text-left whitespace-nowrap font-mono text-[0.68rem]">{{ $dayNode['datum'] ?? $dayNode['label'] }}</span>
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
                                            @php $typeActive = $ordersAnyInside && $pendingOrderItemId === $type['id']; @endphp
                                            <button type="button" wire:click="openOrderItem({{ $type['id'] }})"
                                                    class="w-full flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[0.66rem] transition
                                                           {{ $typeActive ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold' : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] hover:text-[var(--ui-primary)]' }}">
                                                <span class="w-1 h-1 rounded-full flex-shrink-0 {{ $typeActive ? 'bg-[var(--ui-primary)]' : 'bg-[var(--ui-muted)]' }}"></span>
                                                <span class="flex-1 text-left whitespace-nowrap">{{ $type['typ'] }}</span>
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
                        <span class="flex-1 whitespace-nowrap">{{ $t['label'] }}</span>
                        @if(!empty($t['badge']) && $t['badge'] > 0)
                            <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full {{ $badgeClass($t['badge'], $t['badgeColor'] ?? 'slate') }}">{{ $t['badge'] }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container background="bg-slate-100">
        <div class="events-detail-page">
        {{-- Abstand zur Navbar --}}
        <div aria-hidden="true" style="height: 0.375rem;"></div>

        {{-- Header --}}
        <div class="mb-4 bg-white border border-[var(--ui-border)] rounded-lg px-4 py-3.5"
             style="margin-top: 0;">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="min-w-0 flex-1 flex items-center gap-3">
                    <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $statusDotClass ?? 'bg-slate-400' }}"
                          title="Status: {{ $currentStatus }}"></span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-base font-bold text-[var(--ui-secondary)] truncate m-0 leading-tight">{{ $event->name ?: $event->event_number }}</h1>
                        </div>
                        <div class="mt-0.5 flex items-center gap-2.5 text-[0.65rem] text-[var(--ui-muted)] flex-wrap leading-tight">
                            @if($event->start_date)
                                <span class="flex items-center gap-1 font-mono">
                                    @svg('heroicon-o-calendar', 'w-3 h-3')
                                    {{ $event->start_date->format('d.m.Y') }}@if($event->end_date && $event->end_date != $event->start_date) – {{ $event->end_date->format('d.m.Y') }}@endif
                                </span>
                            @endif
                            @if($event->customer)
                                <span class="flex items-center gap-1">
                                    @svg('heroicon-o-user', 'w-3 h-3')
                                    {{ $event->customer }}
                                </span>
                            @endif
                            @if($event->responsible)
                                <span class="flex items-center gap-1">
                                    @svg('heroicon-o-user-circle', 'w-3 h-3')
                                    {{ $event->responsible }}
                                </span>
                            @endif
                            @if($event->status_changed_at)
                                <span class="flex items-center gap-1 text-slate-400">
                                    @svg('heroicon-o-arrow-path', 'w-3 h-3')
                                    {{ $event->status_changed_at->diffForHumans() }} – {{ $event->status_changed_at->format('d.m.Y') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[0.72rem] font-bold font-mono text-[var(--ui-primary)]">
                        {{ $event->event_number }}
                    </span>
                    <div class="flex items-center gap-2">
                        <span class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Status</span>
                        <select wire:change="setStatus($event.target.value); $event.target.blur()"
                                class="border border-[var(--ui-border)] rounded-md px-2.5 py-1 text-[0.72rem] font-semibold bg-white cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 {{ $statusClass }}">
                            @foreach($statusOptions as $s)
                                <option value="{{ $s }}" @if($currentStatus === $s) selected @endif>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= Tab: Basis (4-Spalten-Layout analog Alt) ================= --}}
        @if($activeTab === 'basis')
            @include('events::partials.basis-tab')
        @endif

        {{-- ================= Tab: Details ================= --}}
        @if($activeTab === 'details')
            <div class="pt-1 space-y-4">

                <x-ui-panel>
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-[var(--ui-border)]">
                        <span class="w-1 h-4 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <div class="min-w-0">
                            <h3 class="text-[0.82rem] font-bold text-[var(--ui-secondary)] m-0 leading-tight">Interne Infos</h3>
                            <div class="text-[0.65rem] text-[var(--ui-muted)] mt-0.5">Nur intern sichtbar, nicht auf Kunden-PDFs</div>
                        </div>
                    </div>
                    <div class="px-4 pb-4">
                        @include('events::partials.note-stream', ['type' => 'intern', 'notes' => $notesByType->get('intern', collect())])
                    </div>
                </x-ui-panel>

                    <x-ui-panel>
                        <div class="flex items-center gap-2 px-4 py-3 border-b border-[var(--ui-border)]">
                            <span class="w-1 h-4 rounded-full bg-blue-500 flex-shrink-0"></span>
                            <div class="min-w-0">
                                <h3 class="text-[0.82rem] font-bold text-[var(--ui-secondary)] m-0 leading-tight">Projektverantwortung</h3>
                                <div class="text-[0.65rem] text-[var(--ui-muted)] mt-0.5">Unterschriften-Namen + digitale Freigabe</div>
                            </div>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Unterschrift links (Projektverantwortlicher)</label>
                                    @include('events::partials.user-picker', [
                                        'field'       => 'event.sign_left',
                                        'users'       => $teamUsers,
                                        'current'     => $event->sign_left,
                                        'placeholder' => '— Teammitglied wählen —',
                                    ])
                                </div>
                                <div>
                                    <label class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-1">Unterschrift rechts (Vorgesetzter)</label>
                                    @include('events::partials.user-picker', [
                                        'field'       => 'event.sign_right',
                                        'users'       => $teamUsers,
                                        'current'     => $event->sign_right,
                                        'placeholder' => '— Teammitglied wählen —',
                                    ])
                                </div>
                            </div>

                            @php $currentUserName = auth()->user()?->name; @endphp
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-[var(--ui-border)]">
                                @foreach(['left' => 'Links (Projektverantwortlicher)', 'right' => 'Rechts (Vorgesetzter)'] as $role => $roleLabel)
                                    @php
                                        $sig = $signatures->get($role);
                                        $assignedName = $role === 'left' ? $event->sign_left : $event->sign_right;
                                        $mayBeSigned = $assignedName && $assignedName === $currentUserName;
                                    @endphp
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
                                                @if(!$assignedName)
                                                    <p class="text-[0.62rem] text-[var(--ui-muted)] text-center">Zuerst Verantwortliche/n oben auswählen</p>
                                                    <button type="button" disabled
                                                            class="px-3 py-1.5 text-xs font-semibold bg-slate-200 text-slate-400 rounded-md flex items-center gap-1.5 cursor-not-allowed">
                                                        @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5') Jetzt unterschreiben
                                                    </button>
                                                @elseif($mayBeSigned)
                                                    <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch nicht unterzeichnet</p>
                                                    <button type="button"
                                                            onclick="openSignaturePad('{{ $event->slug }}', '{{ $role }}')"
                                                            class="px-3 py-1.5 text-xs font-semibold bg-[var(--ui-primary)] text-white rounded-md hover:opacity-90 flex items-center gap-1.5">
                                                        @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5') Jetzt unterschreiben
                                                    </button>
                                                @else
                                                    <p class="text-[0.62rem] text-[var(--ui-muted)] text-center">
                                                        Wartet auf <strong>{{ $assignedName }}</strong>
                                                    </p>
                                                    <button type="button" disabled
                                                            title="Nur {{ $assignedName }} darf hier unterschreiben. Du bist als {{ $currentUserName ?: 'unbekannt' }} angemeldet."
                                                            class="px-3 py-1.5 text-xs font-semibold bg-slate-200 text-slate-400 rounded-md flex items-center gap-1.5 cursor-not-allowed">
                                                        @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5') Nicht berechtigt
                                                    </button>
                                                @endif
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

                        // Farblogik fuer Status-Badges: bevorzugt explizite Farbe pro Option aus MrFieldConfig,
                        // Fallback: erste Option = rot, letzte = gruen, Mitte = gelb.
                        $badgeFor = function ($options, $value, $colors = []) {
                            if (!$value) return 'bg-slate-200 text-slate-700';
                            $c = $colors[$value] ?? null;
                            $map = [
                                'red'    => 'bg-red-100 text-red-700',
                                'yellow' => 'bg-yellow-100 text-yellow-800',
                                'green'  => 'bg-green-100 text-green-700',
                                'gray'   => 'bg-slate-200 text-slate-700',
                            ];
                            if ($c && isset($map[$c])) return $map[$c];

                            $count = count($options);
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
                                                    <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full {{ $badgeFor($f['options'], $value, $f['colors'] ?? []) }} flex-shrink-0 truncate max-w-[100px]" title="{{ $value }}">{{ $value }}</span>
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
            <div class="pt-1">
                <livewire:events.detail.activities :event-id="$event->id" :key="'activities-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Angebote ================= --}}
        @if($activeTab === 'angebote')
            <div class="pt-1">
                <livewire:events.detail.quotes
                    :event-id="$event->id"
                    :initial-item-id="$pendingQuoteItemId"
                    :initial-day-id="$pendingQuoteDayId"
                    :initial-view="$pendingQuoteView"
                    :key="'quotes-'.$event->id.'-'.($pendingQuoteItemId ?? '0').'-'.($pendingQuoteDayId ?? '0').'-'.($pendingQuoteView ?? '_')" />
            </div>
        @endif

        {{-- ================= Tab: Bestellungen ================= --}}
        @if($activeTab === 'bestellungen')
            <div class="pt-1">
                <livewire:events.detail.orders
                    :event-id="$event->id"
                    :initial-item-id="$pendingOrderItemId"
                    :initial-day-id="$pendingOrderDayId"
                    :initial-view="$pendingOrderView"
                    :key="'orders-'.$event->id.'-'.($pendingOrderItemId ?? '0').'-'.($pendingOrderDayId ?? '0').'-'.($pendingOrderView ?? '_')" />
            </div>
        @endif

        {{-- ================= Tab: Verträge ================= --}}
        @if($activeTab === 'vertraege')
            <div class="pt-1">
                <livewire:events.detail.contracts :event-id="$event->id" :key="'contracts-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Rechnungen ================= --}}
        @if($activeTab === 'rechnungen')
            <div class="pt-1">
                <livewire:events.detail.invoices :event-id="$event->id" :key="'invoices-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Packliste ================= --}}
        @if($activeTab === 'packliste')
            <div class="pt-1">
                <livewire:events.detail.pick-lists :event-id="$event->id" :key="'picklists-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Kommunikation ================= --}}
        @if($activeTab === 'kommunikation')
            <div class="pt-1">
                <livewire:events.detail.communication :event-id="$event->id" :key="'comm-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Kalkulation ================= --}}
        @if($activeTab === 'kalkulation')
            <div class="pt-1">
                <livewire:events.detail.calculation :event-id="$event->id" :key="'calc-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Projekt-Function ================= --}}
        @if($activeTab === 'projekt')
            <div class="pt-1">
                <livewire:events.detail.projekt-function :event-id="$event->id" :key="'pf-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Schlussbericht ================= --}}
        @if($activeTab === 'schluss')
            <div class="pt-1">
                <livewire:events.detail.final-report :event-id="$event->id" :key="'fr-'.$event->id" />
            </div>
        @endif

        {{-- ================= Tab: Feedback ================= --}}
        @if($activeTab === 'feedback')
            <div class="pt-1">
                <livewire:events.detail.feedback :event-id="$event->id" :key="'feedback-'.$event->id" />
            </div>
        @endif

        {{-- Tage-Tab entfernt: Termine werden im Basis-Tab inline verwaltet. --}}
        {{-- Die openDayCreate/openDayEdit/deleteDay-Actions bleiben unveraendert verfuegbar
             ueber die Basis-Spalte 1 und das weiter unten definierte Day-Modal. --}}

        {{-- ================= Tab: Räume (Buchungen) – Inline-Edit ================= --}}
        @if($activeTab === 'buchungen')
            <div class="pt-1 space-y-4">
                <x-ui-panel>
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-[var(--ui-border)]">
                        <span class="w-1 h-4 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-[0.82rem] font-bold text-[var(--ui-secondary)] m-0 leading-tight">Räume</h3>
                            <div class="text-[0.65rem] text-[var(--ui-muted)] mt-0.5">{{ $bookings->count() }} Buchung(en)</div>
                        </div>
                        @if($days->isNotEmpty())
                            <x-ui-button variant="secondary" size="sm" wire:click="openBulkBooking">
                                @svg('heroicon-o-calendar-days', 'w-3.5 h-3.5 inline') Alle Termine übernehmen
                            </x-ui-button>
                        @endif
                    </div>
                    <x-events::bulk-actionbar
                        :count="count($selectedBookingUuids)"
                        deleteAction="deleteSelectedBookings"
                        clearAction="clearBookingSelection"
                        label="Buchung"
                        labelPlural="Buchungen" />


                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs" x-data="{ lastIdx: null }">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <th class="px-0 py-2 w-[8px]"></th>
                                    <th class="px-3 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[115px]">Datum</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[70px]">Beginn</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[70px]">Ende</th>
                                    <th class="px-2 py-2 text-center text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[60px]">Pers.</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Raum</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[140px]">Bestuhlung</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[120px]">Optionsrang</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Absprache</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody data-sortable-action="reorderBookings">
                                @if($bookings->isEmpty())
                                    <tr>
                                        <td colspan="10" class="px-3 py-8 text-center text-[var(--ui-muted)] text-xs">
                                            Noch keine Räume – unten hinzufügen.
                                        </td>
                                    </tr>
                                @endif
                                @foreach($bookings as $b)
                                    @php $isSelected = in_array($b->uuid, $selectedBookingUuids, true); @endphp
                                    <tr data-sortable-uuid="{{ $b->uuid }}"
                                        class="border-b border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)]/40 group {{ $isSelected ? 'bg-blue-50/50' : '' }}">
                                        <x-events::select-handle
                                            :uuid="$b->uuid"
                                            :index="$loop->index"
                                            :isSelected="$isSelected"
                                            toggle="toggleBookingSelection"
                                            range="toggleBookingRange"
                                            toggleAll="toggleAllBookings" />
                                        <td class="px-3 py-1.5">
                                            @php
                                                $currentBookingDate = $inlineBookings[$b->uuid]['datum'] ?? null;
                                                $knownDates = $days->pluck('datum')->map(fn($d) => $d?->format('Y-m-d'))->filter()->all();
                                            @endphp
                                            <select wire:model.blur="inlineBookings.{{ $b->uuid }}.datum"
                                                    class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-[0.68rem] font-mono bg-transparent focus:bg-white {{ $days->isEmpty() ? 'opacity-60' : '' }}"
                                                    @disabled($days->isEmpty())>
                                                <option value="">— Tag wählen —</option>
                                                @foreach($days as $d)
                                                    @php $val = $d->datum?->format('Y-m-d'); @endphp
                                                    @if($val)
                                                        <option value="{{ $val }}">{{ $d->datum->format('d.m.Y') }}@if($d->day_of_week) ({{ $d->day_of_week }})@endif</option>
                                                    @endif
                                                @endforeach
                                                @if($currentBookingDate && !in_array($currentBookingDate, $knownDates, true))
                                                    <option value="{{ $currentBookingDate }}">{{ \Carbon\Carbon::parse($currentBookingDate)->format('d.m.Y') }} (kein Event-Tag)</option>
                                                @endif
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @include('events::partials.time-input', ['model' => 'inlineBookings.'.$b->uuid.'.beginn', 'placeholder' => '—', 'class' => 'w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs font-mono bg-transparent focus:bg-white'])
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @include('events::partials.time-input', ['model' => 'inlineBookings.'.$b->uuid.'.ende', 'placeholder' => '—', 'class' => 'w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs font-mono bg-transparent focus:bg-white'])
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <input wire:model.blur="inlineBookings.{{ $b->uuid }}.pers" type="text" placeholder="—"
                                                   class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs font-mono bg-transparent focus:bg-white text-center">
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @include('events::partials.location-picker', [
                                                'model'       => 'inlineBookings.' . $b->uuid . '.location_id',
                                                'locations'   => $locations,
                                                'current'     => $inlineBookings[$b->uuid]['location_id'] ?? '',
                                                'placeholder' => '— frei —',
                                                'compact'     => true,
                                            ])
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @if(!empty($settings['bestuhlung']))
                                                <select wire:model.blur="inlineBookings.{{ $b->uuid }}.bestuhlung"
                                                        class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs bg-transparent focus:bg-white">
                                                    <option value="">—</option>
                                                    @foreach($settings['bestuhlung'] as $bOpt)
                                                        <option value="{{ $bOpt }}">{{ $bOpt }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input wire:model.blur="inlineBookings.{{ $b->uuid }}.bestuhlung" type="text" placeholder="—"
                                                       class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs bg-transparent focus:bg-white">
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <select wire:model.blur="inlineBookings.{{ $b->uuid }}.optionsrang"
                                                    class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs bg-transparent focus:bg-white font-semibold">
                                                @foreach($bookingRangs as $r)
                                                    <option value="{{ $r }}">{{ $r }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <input wire:model.blur="inlineBookings.{{ $b->uuid }}.absprache" type="text" placeholder="Absprache…"
                                                   class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs bg-transparent focus:bg-white italic">
                                        </td>
                                        <td class="px-2 py-1.5 text-right">
                                            <button wire:click="deleteBooking('{{ $b->uuid }}')" wire:confirm="Buchung löschen?"
                                                    class="opacity-0 group-hover:opacity-100 text-red-500 hover:bg-red-50 p-1 rounded">
                                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Inline Quick-Add --}}
                    <div class="p-3 bg-[var(--ui-muted-5)]/50 border-t border-[var(--ui-border)]">
                        <div class="grid grid-cols-12 gap-2 items-start">
                            <div class="col-span-2">
                                <select wire:model.live="newBookingInline.datum"
                                        :disabled="@js($newBookingInline['taeglich'] ?? false) || {{ $days->isEmpty() ? 'true' : 'false' }}"
                                        class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 disabled:opacity-50">
                                    <option value="">{{ $days->isEmpty() ? '— erst Termine anlegen —' : '— Tag wählen —' }}</option>
                                    @foreach($days as $d)
                                        @php $val = $d->datum?->format('Y-m-d'); @endphp
                                        @if($val)
                                            <option value="{{ $val }}">{{ $d->datum->format('d.m.Y') }}@if($d->day_of_week) ({{ $d->day_of_week }})@endif</option>
                                        @endif
                                    @endforeach
                                </select>
                                <label class="flex items-center gap-1.5 mt-1 cursor-pointer select-none">
                                    <input wire:model.live="newBookingInline.taeglich" type="checkbox" class="w-3 h-3 accent-[var(--ui-primary)]">
                                    <span class="text-[0.6rem] text-[var(--ui-muted)]">täglich</span>
                                </label>
                            </div>
                            <div class="col-span-1" wire:key="bk-beginn-{{ $newBookingInline['datum'] ?: 'none' }}">
                                @include('events::partials.time-input', ['model' => 'newBookingInline.beginn', 'modifier' => 'defer', 'placeholder' => 'Beginn', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
                            </div>
                            <div class="col-span-1" wire:key="bk-ende-{{ $newBookingInline['datum'] ?: 'none' }}">
                                @include('events::partials.time-input', ['model' => 'newBookingInline.ende', 'modifier' => 'defer', 'placeholder' => 'Ende', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
                            </div>
                            <div class="col-span-1" wire:key="bk-pers-{{ $newBookingInline['datum'] ?: 'none' }}">
                                <input wire:model.defer="newBookingInline.pers" type="text" placeholder="Pers."
                                       class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 text-center">
                            </div>
                            <div class="col-span-2">
                                @include('events::partials.location-picker', [
                                    'model'       => 'newBookingInline.location_id',
                                    'locations'   => $locations,
                                    'current'     => $newBookingInline['location_id'] ?? '',
                                    'placeholder' => 'Raum',
                                ])
                            </div>
                            <div class="col-span-2">
                                @if(!empty($settings['bestuhlung']))
                                    <select wire:model.defer="newBookingInline.bestuhlung"
                                            class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                        <option value="">Bestuhlung</option>
                                        @foreach($settings['bestuhlung'] as $bOpt)
                                            <option value="{{ $bOpt }}">{{ $bOpt }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input wire:model.defer="newBookingInline.bestuhlung" type="text" placeholder="Bestuhlung"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                @endif
                            </div>
                            <div class="col-span-1">
                                <select wire:model.defer="newBookingInline.optionsrang"
                                        class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    @foreach($bookingRangs as $r)
                                        <option value="{{ $r }}">{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1">
                                <input wire:model.defer="newBookingInline.absprache" type="text" placeholder="Absprache"
                                       wire:keydown.enter="addInlineBooking"
                                       class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            </div>
                            <div class="col-span-1">
                                <x-ui-button variant="primary" size="sm" wire:click="addInlineBooking" class="w-full">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Add
                                </x-ui-button>
                            </div>
                        </div>
                        <p class="text-[0.6rem] text-[var(--ui-muted)] mt-2">Enter im Absprache-Feld oder Button zum Hinzufügen. „täglich" belegt automatisch alle Event-Tage.</p>
                    </div>
                </x-ui-panel>
            </div>
        @endif

        {{-- ================= Tab: Ablauf – Inline-Edit ================= --}}
        @if($activeTab === 'ablauf')
            <div class="pt-1 space-y-4">
                <x-ui-panel>
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-[var(--ui-border)]">
                        <span class="w-1 h-4 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <div class="min-w-0">
                            <h3 class="text-[0.82rem] font-bold text-[var(--ui-secondary)] m-0 leading-tight">Ablaufplan</h3>
                            <div class="text-[0.65rem] text-[var(--ui-muted)] mt-0.5">{{ $schedule->count() }} Eintrag/Einträge</div>
                        </div>
                    </div>

                    <x-events::bulk-actionbar
                        :count="count($selectedScheduleUuids)"
                        deleteAction="deleteSelectedSchedule"
                        clearAction="clearScheduleSelection"
                        label="Ablauf-Eintrag"
                        labelPlural="Ablauf-Eintraege" />

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs" x-data="{ lastIdx: null }">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <th class="px-0 py-2 w-[8px]"></th>
                                    <th class="px-3 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[115px]">Datum</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[70px]">Von</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[70px]">Bis</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Beschreibung</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-[120px]">Raum</th>
                                    <th class="px-2 py-2 text-left text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Bemerkung</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody data-sortable-action="reorderSchedule">
                                @if($schedule->isEmpty())
                                    <tr>
                                        <td colspan="8" class="px-3 py-8 text-center text-[var(--ui-muted)] text-xs">
                                            Noch kein Ablaufplan – unten hinzufügen.
                                        </td>
                                    </tr>
                                @endif
                                @foreach($schedule as $item)
                                    @php $isSelected = in_array($item->uuid, $selectedScheduleUuids, true); @endphp
                                    <tr data-sortable-uuid="{{ $item->uuid }}"
                                        class="border-b border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)]/40 group {{ $isSelected ? 'bg-blue-50/50' : '' }}">
                                        <x-events::select-handle
                                            :uuid="$item->uuid"
                                            :index="$loop->index"
                                            :isSelected="$isSelected"
                                            toggle="toggleScheduleSelection"
                                            range="toggleScheduleRange"
                                            toggleAll="toggleAllSchedule" />
                                        <td class="px-3 py-1.5">
                                            @php
                                                $currentScheduleDate = $inlineSchedule[$item->uuid]['datum'] ?? null;
                                                $knownScheduleDates = $days->pluck('datum')->map(fn($d) => $d?->format('Y-m-d'))->filter()->all();
                                            @endphp
                                            <select wire:model.blur="inlineSchedule.{{ $item->uuid }}.datum"
                                                    class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-[0.68rem] font-mono bg-transparent focus:bg-white {{ $days->isEmpty() ? 'opacity-60' : '' }}"
                                                    @disabled($days->isEmpty())>
                                                <option value="">— Tag wählen —</option>
                                                @foreach($days as $d)
                                                    @php $val = $d->datum?->format('Y-m-d'); @endphp
                                                    @if($val)
                                                        <option value="{{ $val }}">{{ $d->datum->format('d.m.Y') }}@if($d->day_of_week) ({{ $d->day_of_week }})@endif</option>
                                                    @endif
                                                @endforeach
                                                @if($currentScheduleDate && !in_array($currentScheduleDate, $knownScheduleDates, true))
                                                    <option value="{{ $currentScheduleDate }}">{{ \Carbon\Carbon::parse($currentScheduleDate)->format('d.m.Y') }} (kein Event-Tag)</option>
                                                @endif
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @include('events::partials.time-input', ['model' => 'inlineSchedule.'.$item->uuid.'.von', 'placeholder' => '—', 'class' => 'w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs font-mono bg-transparent focus:bg-white'])
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @include('events::partials.time-input', ['model' => 'inlineSchedule.'.$item->uuid.'.bis', 'placeholder' => '—', 'class' => 'w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs font-mono bg-transparent focus:bg-white'])
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <input wire:model.blur="inlineSchedule.{{ $item->uuid }}.beschreibung" type="text" placeholder="Beschreibung…" list="schedule-desc-options"
                                                   class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs font-semibold text-[var(--ui-secondary)] bg-transparent focus:bg-white">
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @php
                                                $currentRaum = $inlineSchedule[$item->uuid]['raum'] ?? '';
                                                $knownValues = array_column($eventRooms, 'value');
                                                $roomsForPicker = $eventRooms;
                                                if ($currentRaum && !in_array($currentRaum, $knownValues, true)) {
                                                    $roomsForPicker[] = ['value' => $currentRaum, 'short' => $currentRaum, 'label' => $currentRaum . ' (nicht mehr gebucht)'];
                                                }
                                            @endphp
                                            @include('events::partials.room-picker', [
                                                'model'       => 'inlineSchedule.'.$item->uuid.'.raum',
                                                'rooms'       => $roomsForPicker,
                                                'current'     => $currentRaum,
                                                'disabled'    => empty($eventRooms),
                                                'placeholder' => '—',
                                                'compact'     => true,
                                            ])
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <input wire:model.blur="inlineSchedule.{{ $item->uuid }}.bemerkung" type="text" placeholder="Bemerkung…"
                                                   class="w-full border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs italic text-[var(--ui-muted)] bg-transparent focus:bg-white">
                                        </td>
                                        <td class="px-2 py-1.5 text-right">
                                            <button wire:click="deleteSchedule('{{ $item->uuid }}')" wire:confirm="Eintrag löschen?"
                                                    class="opacity-0 group-hover:opacity-100 text-red-500 hover:bg-red-50 p-1 rounded">
                                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Inline Quick-Add --}}
                    <div class="p-3 bg-[var(--ui-muted-5)]/50 border-t border-[var(--ui-border)]">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <div class="col-span-2">
                                <select wire:model.defer="newScheduleInline.datum"
                                        class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 {{ $days->isEmpty() ? 'opacity-60' : '' }}"
                                        @disabled($days->isEmpty())>
                                    <option value="">{{ $days->isEmpty() ? '— erst Termine anlegen —' : '— Tag wählen —' }}</option>
                                    @foreach($days as $d)
                                        @php $val = $d->datum?->format('Y-m-d'); @endphp
                                        @if($val)
                                            <option value="{{ $val }}">{{ $d->datum->format('d.m.Y') }}@if($d->day_of_week) ({{ $d->day_of_week }})@endif</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-1">
                                @include('events::partials.time-input', ['model' => 'newScheduleInline.von', 'modifier' => 'defer', 'placeholder' => 'Von', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
                            </div>
                            <div class="col-span-1">
                                @include('events::partials.time-input', ['model' => 'newScheduleInline.bis', 'modifier' => 'defer', 'placeholder' => 'Bis', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
                            </div>
                            <div class="col-span-4">
                                <input wire:model.defer="newScheduleInline.beschreibung" type="text" placeholder="Beschreibung…"
                                       list="schedule-desc-options"
                                       wire:keydown.enter="addInlineSchedule"
                                       class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                @if(!empty($settings['schedule_descriptions']))
                                    <datalist id="schedule-desc-options">
                                        @foreach($settings['schedule_descriptions'] as $sd)
                                            <option value="{{ $sd }}">
                                        @endforeach
                                    </datalist>
                                @endif
                            </div>
                            <div class="col-span-2">
                                @include('events::partials.room-picker', [
                                    'model'       => 'newScheduleInline.raum',
                                    'rooms'       => $eventRooms,
                                    'current'     => $newScheduleInline['raum'] ?? '',
                                    'disabled'    => empty($eventRooms),
                                    'placeholder' => empty($eventRooms) ? '— erst Räume anlegen —' : 'Raum wählen …',
                                    'compact'     => false,
                                ])
                            </div>
                            <div class="col-span-1">
                                <input wire:model.defer="newScheduleInline.bemerkung" type="text" placeholder="Bemerk."
                                       class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            </div>
                            <div class="col-span-1">
                                <x-ui-button variant="primary" size="sm" wire:click="addInlineSchedule" class="w-full">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Add
                                </x-ui-button>
                            </div>
                        </div>
                        <p class="text-[0.6rem] text-[var(--ui-muted)] mt-2">Enter im Beschreibungsfeld oder Button zum Hinzufügen.</p>
                    </div>
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
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                    @php $dayTypeOptions = $settings['day_types'] ?? ['Veranstaltungstag']; @endphp
                    <select wire:model="dayForm.day_type"
                            class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @foreach($dayTypeOptions as $dt)
                            <option value="{{ $dt }}">{{ $dt }}</option>
                        @endforeach
                        @if($dayForm['day_type'] ?? null && !in_array($dayForm['day_type'], $dayTypeOptions, true))
                            <option value="{{ $dayForm['day_type'] }}">{{ $dayForm['day_type'] }}</option>
                        @endif
                    </select>
                    @error('dayForm.day_type') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Von</label>
                        @include('events::partials.time-input', ['model' => 'dayForm.von', 'modifier' => 'defer', 'placeholder' => '10:00', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bis</label>
                        @include('events::partials.time-input', ['model' => 'dayForm.bis', 'modifier' => 'defer', 'placeholder' => '18:00', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
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
                        @include('events::partials.time-input', ['model' => 'bookingForm.beginn', 'modifier' => 'defer', 'placeholder' => '10:00', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bis</label>
                        @include('events::partials.time-input', ['model' => 'bookingForm.ende', 'modifier' => 'defer', 'placeholder' => '18:00', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
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

        {{-- ================= Modal: Bulk-Booking (alle Termine uebernehmen) ================= --}}
        <x-ui-modal wire:model="showBulkBookingModal" size="md" :hideFooter="true">
            <x-slot name="header">Alle Termine übernehmen</x-slot>

            <form wire:submit.prevent="submitBulkBooking" class="space-y-4">
                <p class="text-[0.7rem] text-[var(--ui-muted)] -mt-1">
                    Es wird pro Termin eine Buchung angelegt. Beginn, Ende und Personenzahl stammen aus den jeweiligen Tagen.
                </p>

                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Raum</label>
                    @include('events::partials.location-picker', [
                        'model'       => 'bulkBookingForm.location_id',
                        'locations'   => $locations,
                        'current'     => $bulkBookingForm['location_id'] ?? '',
                        'placeholder' => 'Raum wählen …',
                    ])
                    <input wire:model="bulkBookingForm.raum" type="text" placeholder="…oder Raum-Kürzel als Freitext"
                           class="mt-2 w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    @error('bulkBookingForm.raum') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bestuhlung</label>
                        @if(!empty($settings['bestuhlung']))
                            <select wire:model="bulkBookingForm.bestuhlung"
                                    class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                <option value="">—</option>
                                @foreach($settings['bestuhlung'] as $b)
                                    <option value="{{ $b }}">{{ $b }}</option>
                                @endforeach
                            </select>
                        @else
                            <input wire:model="bulkBookingForm.bestuhlung" type="text" placeholder="Reihen / Bankett / …"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @endif
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Optionsrang</label>
                        <select wire:model="bulkBookingForm.optionsrang"
                                class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @foreach($bookingRangs as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Absprache</label>
                    <input wire:model="bulkBookingForm.absprache" type="text"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeBulkBooking">Abbrechen</x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">Übernehmen</x-ui-button>
                </div>
            </form>
        </x-ui-modal>

        {{-- ================= Modal: Schedule ================= --}}
        <x-ui-modal wire:model="showScheduleModal" size="md" :hideFooter="true">
            <x-slot name="header">{{ $editingScheduleUuid ? 'Ablauf-Eintrag bearbeiten' : 'Neuer Ablauf-Eintrag' }}</x-slot>

            <form wire:submit.prevent="saveSchedule" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Beschreibung *</label>
                    <input wire:model="scheduleForm.beschreibung" type="text" placeholder="Begrüßung / Empfang / ..." list="schedule-desc-options"
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
                        @include('events::partials.time-input', ['model' => 'scheduleForm.von', 'modifier' => 'defer', 'placeholder' => '10:00', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bis</label>
                        @include('events::partials.time-input', ['model' => 'scheduleForm.bis', 'modifier' => 'defer', 'placeholder' => '11:00', 'class' => 'w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30'])
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
        </div>
    </x-ui-page-container>
</x-ui-page>
