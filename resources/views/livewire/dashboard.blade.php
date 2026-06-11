<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Events Dashboard" icon="heroicon-o-calendar-days" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events'],
            ['label' => 'Dashboard'],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" :href="route('events.manage')" wire:navigate>
                <span class="flex items-center gap-2">
                    @svg('heroicon-o-list-bullet', 'w-4 h-4')
                    Alle Veranstaltungen
                </span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Stat-Kacheln --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-ui-dashboard-tile
                    title="Events"
                    :count="$totalEvents"
                    subtitle="Gesamt"
                    icon="calendar-days"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Laufend"
                    :count="$running"
                    subtitle="heute aktiv"
                    icon="play"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Bevorstehend"
                    :count="$upcoming"
                    subtitle="in der Zukunft"
                    icon="clock"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Vergangen"
                    :count="$past"
                    subtitle="abgeschlossen"
                    icon="archive-box"
                    variant="secondary"
                    size="lg"
                />
            </div>

            {{-- Wiedervorlage-Cockpit: Optionsfristen / Follow-ups / Angebots-Ablauf --}}
            @php
                $resubToday = now()->toDateString();
                $resubTotal = $resubmission['options']->count() + $resubmission['followUps']->count() + $resubmission['quotes']->count();
            @endphp
            @if($resubTotal > 0)
                <div>
                    <h2 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3 flex items-center gap-2">
                        @svg('heroicon-o-bell-alert', 'w-4 h-4 text-amber-500')
                        Wiedervorlage
                        <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">{{ $resubTotal }}</span>
                    </h2>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                        {{-- Spalte 1: Ablaufende Raum-Optionen --}}
                        <div class="bg-white border border-[var(--ui-border)] rounded-xl p-4">
                            <h3 class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5">Ablaufende Raum-Optionen</h3>
                            @forelse($resubmission['options'] as $b)
                                @php $expired = $b->option_until->toDateString() < $resubToday; @endphp
                                <a href="{{ route('events.show', ['slug' => $b->event->slug]) }}?tab=buchungen" wire:navigate
                                   class="flex items-center gap-2 py-1.5 border-b border-[var(--ui-border)]/40 last:border-0 no-underline hover:bg-slate-50 rounded px-1 -mx-1">
                                    <span class="text-[0.62rem] font-mono font-bold {{ $expired ? 'text-red-600' : 'text-amber-600' }} w-14 flex-shrink-0">
                                        {{ $b->option_until->format('d.m.') }}
                                    </span>
                                    <span class="text-[0.62rem] font-mono font-bold text-[var(--ui-secondary)] flex-shrink-0">{{ $b->location?->kuerzel ?: $b->raum ?: '—' }}</span>
                                    <span class="text-[0.62rem] text-[var(--ui-muted)] truncate flex-1">{{ $b->event->name ?: $b->event->event_number }}</span>
                                    <span class="text-[0.55rem] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $expired ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $expired ? 'abgelaufen' : $b->optionsrang }}
                                    </span>
                                </a>
                            @empty
                                <p class="text-[0.62rem] text-[var(--ui-muted)] m-0">Keine Optionsfristen in den nächsten 7 Tagen.</p>
                            @endforelse
                        </div>

                        {{-- Spalte 2: Faellige Follow-ups --}}
                        <div class="bg-white border border-[var(--ui-border)] rounded-xl p-4">
                            <h3 class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5">Fällige Follow-ups</h3>
                            @forelse($resubmission['followUps'] as $e)
                                @php $expired = $e->follow_up_date->toDateString() < $resubToday; @endphp
                                <a href="{{ route('events.show', ['slug' => $e->slug]) }}" wire:navigate
                                   class="flex items-center gap-2 py-1.5 border-b border-[var(--ui-border)]/40 last:border-0 no-underline hover:bg-slate-50 rounded px-1 -mx-1">
                                    <span class="text-[0.62rem] font-mono font-bold {{ $expired ? 'text-red-600' : 'text-amber-600' }} w-14 flex-shrink-0">
                                        {{ $e->follow_up_date->format('d.m.') }}
                                    </span>
                                    <span class="text-[0.62rem] text-[var(--ui-secondary)] font-semibold truncate flex-1"
                                          title="{{ $e->follow_up_note }}">{{ $e->name ?: $e->event_number }}</span>
                                    @if($e->follow_up_note)
                                        <span class="text-[0.58rem] text-[var(--ui-muted)] truncate max-w-[40%]">{{ $e->follow_up_note }}</span>
                                    @endif
                                </a>
                            @empty
                                <p class="text-[0.62rem] text-[var(--ui-muted)] m-0">Keine fälligen Follow-ups.</p>
                            @endforelse
                        </div>

                        {{-- Spalte 3: Ablaufende Angebote --}}
                        <div class="bg-white border border-[var(--ui-border)] rounded-xl p-4">
                            <h3 class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2.5">Ablaufende Angebote</h3>
                            @forelse($resubmission['quotes'] as $q)
                                @php $expired = $q->valid_until->toDateString() < $resubToday; @endphp
                                <a href="{{ route('events.show', ['slug' => $q->event->slug]) }}?tab=angebote" wire:navigate
                                   class="flex items-center gap-2 py-1.5 border-b border-[var(--ui-border)]/40 last:border-0 no-underline hover:bg-slate-50 rounded px-1 -mx-1">
                                    <span class="text-[0.62rem] font-mono font-bold {{ $expired ? 'text-red-600' : 'text-amber-600' }} w-14 flex-shrink-0">
                                        {{ $q->valid_until->format('d.m.') }}
                                    </span>
                                    <span class="text-[0.62rem] text-[var(--ui-secondary)] font-semibold truncate flex-1">{{ $q->event->name ?: $q->event->event_number }}</span>
                                    <span class="text-[0.55rem] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $expired ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $expired ? 'abgelaufen' : 'versendet' }}
                                    </span>
                                </a>
                            @empty
                                <p class="text-[0.62rem] text-[var(--ui-muted)] m-0">Keine ablaufenden Angebote.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            {{-- Anstehende Veranstaltungen --}}
            @if($upcomingEvents->isNotEmpty())
                @php
                    $statusBorderMap = [
                        'Vertrag'       => 'border-l-green-500',
                        'Definitiv'     => 'border-l-green-400',
                        'Option'        => 'border-l-yellow-400',
                        'Abgeschlossen' => 'border-l-slate-300',
                        'Storno'        => 'border-l-red-500',
                        'Warteliste'    => 'border-l-orange-400',
                        'Tendenz'       => 'border-l-purple-400',
                    ];
                    $statusDotMap = [
                        'Vertrag'       => 'bg-green-500',
                        'Definitiv'     => 'bg-green-400',
                        'Option'        => 'bg-yellow-400',
                        'Abgeschlossen' => 'bg-slate-400',
                        'Storno'        => 'bg-red-500',
                        'Warteliste'    => 'bg-orange-400',
                        'Tendenz'       => 'bg-purple-400',
                    ];
                    $monthShort = ['', 'Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
                    $today = now()->toDateString();
                @endphp

                <div>
                    <h2 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Anstehende Veranstaltungen</h2>
                    <div class="space-y-2">
                        @foreach($upcomingEvents as $event)
                            @php
                                $sBorder = $statusBorderMap[$event->status] ?? 'border-l-slate-300';
                                $sDot    = $statusDotMap[$event->status] ?? 'bg-slate-400';
                                $dateDay   = $event->start_date?->format('d');
                                $dateMonth = $event->start_date ? $monthShort[(int) $event->start_date->format('m')] : '';
                                $dateYear  = $event->start_date?->format('Y');
                                $endLabel  = null;
                                if ($event->end_date && $event->start_date && $event->end_date != $event->start_date) {
                                    $endDay = $event->end_date->format('d');
                                    $endMonthShort = $monthShort[(int) $event->end_date->format('m')] ?? '';
                                    $endYear = $event->end_date->format('Y');
                                    if ($endYear !== $dateYear) {
                                        $endLabel = $endDay . '. ' . $endMonthShort . ' ' . $endYear;
                                    } elseif ($endMonthShort !== $dateMonth) {
                                        $endLabel = $endDay . '. ' . $endMonthShort;
                                    } else {
                                        $endLabel = $endDay . '. ' . $dateMonth;
                                    }
                                }
                                $isRunning = $event->start_date && $event->start_date->toDateString() <= $today
                                    && (!$event->end_date || $event->end_date->toDateString() >= $today);

                                $paxValues = $event->days->flatMap(fn ($d) => [
                                    $d->pers_von !== null && $d->pers_von !== '' ? (int) $d->pers_von : null,
                                    $d->pers_bis !== null && $d->pers_bis !== '' ? (int) $d->pers_bis : null,
                                ])->filter(fn ($v) => $v !== null && $v > 0)->values();
                                $paxMin = $paxValues->min();
                                $paxMax = $paxValues->max();
                                $paxLabel = null;
                                if ($paxMin !== null && $paxMax !== null) {
                                    $paxLabel = $paxMin === $paxMax
                                        ? number_format($paxMin, 0, ',', '.') . ' Pers.'
                                        : number_format($paxMin, 0, ',', '.') . '–' . number_format($paxMax, 0, ',', '.') . ' Pers.';
                                }
                                $customerLabel = $customerLabels[$event->id] ?? null;
                            @endphp

                            <div class="bg-white border border-[var(--ui-border)] border-l-4 {{ $sBorder }} rounded-lg overflow-hidden hover:shadow-sm transition">
                                <div class="flex items-stretch">
                                    {{-- Datum --}}
                                    <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                       class="flex flex-col items-center justify-center px-5 py-3 bg-slate-50/50 min-w-[96px] text-center hover:bg-slate-50 no-underline">
                                        @if($dateDay)
                                            <span class="text-[1.5rem] font-bold text-[var(--ui-secondary)] leading-none">{{ $dateDay }}</span>
                                            <span class="text-[0.6rem] font-semibold text-[var(--ui-muted)] mt-0.5 uppercase tracking-wider">{{ $dateMonth }} {{ $dateYear }}</span>
                                            @if($endLabel)
                                                <span class="text-[0.6rem] text-[var(--ui-muted)] mt-0.5 whitespace-nowrap">&rarr; {{ $endLabel }}</span>
                                            @endif
                                        @else
                                            <span class="text-[0.65rem] text-[var(--ui-muted)] italic">kein Datum</span>
                                        @endif
                                    </a>

                                    {{-- Titel + Meta --}}
                                    <div class="flex-1 min-w-0 px-4 py-3 flex flex-col justify-center gap-0.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                               class="text-[0.6rem] font-mono font-bold text-[var(--ui-muted)] hover:text-[var(--ui-primary)] no-underline">
                                                {{ $event->event_number }}
                                            </a>
                                            @if($event->event_type)
                                                <span class="text-[0.55rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $event->event_type }}</span>
                                            @endif
                                            @if($isRunning)
                                                <span class="text-[0.55rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-green-100 text-green-700">Laufend</span>
                                            @endif
                                        </div>
                                        <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                           class="text-sm font-bold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] no-underline truncate flex items-center gap-1.5">
                                            @if($event->is_highlight)
                                                <span class="text-amber-500 flex-shrink-0" title="Highlight">
                                                    @svg('heroicon-s-star', 'w-3.5 h-3.5')
                                                </span>
                                            @endif
                                            <span class="truncate">{{ $event->name ?: 'Unbenannte Veranstaltung' }}</span>
                                        </a>
                                        <div class="flex items-center gap-3 text-[0.65rem] text-[var(--ui-muted)] flex-wrap">
                                            @if($customerLabel)
                                                <span class="flex items-center gap-1">
                                                    @svg('heroicon-o-user', 'w-3 h-3')
                                                    {{ $customerLabel }}
                                                </span>
                                            @endif
                                            @if($event->location)
                                                <span class="flex items-center gap-1">
                                                    @svg('heroicon-o-map-pin', 'w-3 h-3')
                                                    {{ $event->location }}
                                                </span>
                                            @endif
                                            @if($paxLabel)
                                                <span class="flex items-center gap-1">
                                                    @svg('heroicon-o-users', 'w-3 h-3')
                                                    {{ $paxLabel }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Status --}}
                                    <div class="hidden sm:flex items-center px-4">
                                        <span class="inline-flex items-center gap-1.5 text-[0.65rem] font-semibold text-[var(--ui-secondary)]">
                                            <span class="w-2 h-2 rounded-full {{ $sDot }}"></span>
                                            {{ $event->status ?: 'Option' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($upcomingEvents->count() >= 20)
                        <div class="mt-4 text-center">
                            <x-ui-button variant="secondary-outline" size="sm" :href="route('events.manage')" wire:navigate>
                                Alle Veranstaltungen anzeigen
                            </x-ui-button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('events.manage')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-list-bullet', 'w-4 h-4')
                                Veranstaltungen
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Schnellstatistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Events gesamt</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalEvents }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Bevorstehend</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $upcoming }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Dashboard geladen</div>
                        <div class="text-[var(--ui-muted)]">vor 1 Minute</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
