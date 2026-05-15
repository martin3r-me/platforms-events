<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Veranstaltungen" icon="heroicon-o-calendar-days" />
    </x-slot>

    @php
        $statusColor = [
            'Vertrag'       => ['bg' => 'bg-green-100',  'text' => 'text-green-700'],
            'Definitiv'     => ['bg' => 'bg-green-50',   'text' => 'text-green-700'],
            'Option'        => ['bg' => 'bg-yellow-50',  'text' => 'text-yellow-800'],
            'Abgeschlossen' => ['bg' => 'bg-slate-100',  'text' => 'text-slate-600'],
            'Storno'        => ['bg' => 'bg-red-50',     'text' => 'text-red-700'],
            'Warteliste'    => ['bg' => 'bg-orange-50',  'text' => 'text-orange-700'],
            'Tendenz'       => ['bg' => 'bg-purple-50',  'text' => 'text-purple-700'],
        ];

        $periodOptions = [
            ['value' => 'week',  'label' => 'Woche'],
            ['value' => 'month', 'label' => 'Monat'],
            ['value' => 'year',  'label' => 'Jahr'],
            ['value' => 'all',   'label' => 'Alle'],
        ];
    @endphp

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Events', 'route' => 'events.dashboard'],
            ['label' => 'Veranstaltungen'],
        ]">
            <div class="flex items-center gap-2">
                {{-- View-Toggle: Liste / Kalender --}}
                <div class="inline-flex items-center bg-slate-100 border border-slate-200 rounded-md p-0.5 gap-0.5">
                    <button type="button" wire:click="setViewMode('list')"
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded text-[0.68rem] font-semibold transition
                                   {{ $viewMode === 'list'
                                       ? 'bg-white text-[var(--ui-secondary)] shadow-sm'
                                       : 'text-slate-500 hover:text-[var(--ui-secondary)]' }}">
                        @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5')
                        Liste
                    </button>
                    <button type="button" wire:click="setViewMode('calendar')"
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded text-[0.68rem] font-semibold transition
                                   {{ $viewMode === 'calendar'
                                       ? 'bg-white text-[var(--ui-secondary)] shadow-sm'
                                       : 'text-slate-500 hover:text-[var(--ui-secondary)]' }}">
                        @svg('heroicon-o-calendar-days', 'w-3.5 h-3.5')
                        Kalender
                    </button>
                </div>

                <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                    <span class="flex items-center gap-2">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Neue Veranstaltung
                    </span>
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>

        <div class="space-y-6">
            @if(session('eventCreatedStatus'))
                <div class="px-4 py-2.5 rounded-md bg-emerald-50 border border-emerald-200 text-[0.72rem] text-emerald-800 flex items-center gap-2">
                    @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                    {{ session('eventCreatedStatus') }}
                </div>
            @endif
            @if(session('eventCreatedError'))
                <div class="px-4 py-2.5 rounded-md bg-amber-50 border border-amber-200 text-[0.72rem] text-amber-800 flex items-center gap-2">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4')
                    {{ session('eventCreatedError') }}
                </div>
            @endif

            {{-- Stats (nur in der Listen-Ansicht; im Kalender uebernehmen die Tages-Zellen die Visualisierung) --}}
            @if($viewMode === 'list')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white border border-[var(--ui-border)] rounded-lg p-4">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $stats['total'] }}</p>
                    <p class="text-[0.65rem] text-[var(--ui-muted)] uppercase tracking-wider font-semibold">Veranstaltungen</p>
                </div>
                <div class="bg-white border border-[var(--ui-border)] rounded-lg p-4">
                    <p class="text-2xl font-bold text-blue-600">{{ $stats['upcoming'] }}</p>
                    <p class="text-[0.65rem] text-[var(--ui-muted)] uppercase tracking-wider font-semibold">Bevorstehend</p>
                </div>
                <div class="bg-white border border-[var(--ui-border)] rounded-lg p-4">
                    <p class="text-2xl font-bold text-slate-400">{{ $stats['past'] }}</p>
                    <p class="text-[0.65rem] text-[var(--ui-muted)] uppercase tracking-wider font-semibold">Vergangen</p>
                </div>
            </div>
            @endif

            {{-- Filter-Panel: gilt fuer Liste UND Kalender. Period-Tabs nur in der Liste
                 (im Kalender navigiert die JS-Komponente eigenstaendig). --}}
            <x-ui-panel>
                <div class="flex flex-col gap-4">
                    @if($viewMode === 'list')
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-20 flex-shrink-0">
                            Zeitraum
                        </span>
                        <x-ui-segmented-toggle
                            model="period"
                            :current="$period"
                            :options="$periodOptions"
                            size="sm"
                            activeVariant="secondary"
                        />
                    </div>
                    @endif

                    <div class="flex items-center gap-3 flex-wrap {{ $viewMode === 'list' ? 'border-t border-[var(--ui-border)]/40 pt-4' : '' }}">
                        <span class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-20 flex-shrink-0">
                            Suche
                        </span>
                        <div class="flex-1 min-w-[200px] relative">
                            <input wire:model.live.debounce.400ms="search"
                                   type="text"
                                   placeholder="Name, Kunde oder VA-Nummer …"
                                   class="w-full border border-[var(--ui-border)] rounded-md pl-9 pr-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                                @svg('heroicon-o-magnifying-glass', 'w-4 h-4')
                            </span>
                        </div>
                        <select wire:model.live="statusFilter"
                                class="border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <option value="">Alle Status</option>
                            @foreach($statusOptions as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="responsibleFilter"
                                class="border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"
                                title="Verantwortlicher">
                            <option value="">Alle Verantwortlichen</option>
                            @foreach(($filterOptions['responsibles'] ?? []) as $r)
                                <option value="{{ $r }}">{{ $r }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="eventTypeFilter"
                                class="border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"
                                title="Event-Typ">
                            <option value="">Alle Typen</option>
                            @foreach(($filterOptions['eventTypes'] ?? []) as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="locationFilter"
                                class="border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"
                                title="Location">
                            <option value="">Alle Locations</option>
                            @foreach(($filterOptions['locations'] ?? []) as $l)
                                <option value="{{ $l }}">{{ $l }}</option>
                            @endforeach
                        </select>

                        {{-- Highlight-Filter: nur besonders markierte Veranstaltungen anzeigen --}}
                        <button type="button" wire:click="toggleHighlightsOnly"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md border text-[0.7rem] font-semibold transition
                                       {{ $highlightsOnly
                                           ? 'bg-amber-50 border-amber-300 text-amber-700 hover:bg-amber-100'
                                           : 'bg-white border-[var(--ui-border)] text-slate-500 hover:border-amber-200 hover:text-amber-600' }}"
                                title="Nur Highlights anzeigen">
                            @if($highlightsOnly)
                                @svg('heroicon-s-star', 'w-3.5 h-3.5')
                            @else
                                @svg('heroicon-o-star', 'w-3.5 h-3.5')
                            @endif
                            Highlights
                        </button>

                        @if($search !== '' || $statusFilter !== '' || $responsibleFilter !== '' || $eventTypeFilter !== '' || $locationFilter !== '' || $highlightsOnly || $dateFrom !== '' || $dateTo !== '')
                            <button type="button" wire:click="clearCalendarFilters"
                                    class="inline-flex items-center gap-1 px-2.5 py-2 rounded-md border border-slate-200 bg-white text-[0.65rem] font-semibold text-slate-500 hover:bg-slate-50"
                                    title="Filter zuruecksetzen">
                                @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                Reset
                            </button>
                        @endif
                    </div>
                </div>
            </x-ui-panel>

            @if($viewMode === 'list')

            {{-- Card-Liste --}}
            @php
                $statusDotMap = [
                    'Vertrag'       => 'bg-green-500',
                    'Definitiv'     => 'bg-green-400',
                    'Option'        => 'bg-yellow-400',
                    'Abgeschlossen' => 'bg-slate-400',
                    'Storno'        => 'bg-red-500',
                    'Warteliste'    => 'bg-orange-400',
                    'Tendenz'       => 'bg-purple-400',
                ];
                $statusBorderMap = [
                    'Vertrag'       => 'border-l-green-500',
                    'Definitiv'     => 'border-l-green-400',
                    'Option'        => 'border-l-yellow-400',
                    'Abgeschlossen' => 'border-l-slate-400',
                    'Storno'        => 'border-l-red-500',
                    'Warteliste'    => 'border-l-orange-400',
                    'Tendenz'       => 'border-l-purple-400',
                ];
                $monthShort = ['', 'Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
                $colorClass = [
                    'red'    => ['bg' => '#fee2e2', 'fg' => '#dc2626', 'dot' => '#ef4444'],
                    'yellow' => ['bg' => '#fef3c7', 'fg' => '#b45309', 'dot' => '#f59e0b'],
                    'green'  => ['bg' => '#dcfce7', 'fg' => '#15803d', 'dot' => '#22c55e'],
                    'gray'   => ['bg' => '#f1f5f9', 'fg' => '#475569', 'dot' => '#cbd5e1'],
                ];
            @endphp

            @if($events->isEmpty())
                <x-ui-panel>
                    <div class="p-12 text-center">
                        @svg('heroicon-o-calendar-days', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-4')
                        <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Veranstaltungen gefunden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">
                            @if($search || $statusFilter)
                                Für die aktuellen Filter gibt es keine Treffer.
                            @else
                                Lege die erste Veranstaltung an.
                            @endif
                        </p>
                        <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neue Veranstaltung
                            </span>
                        </x-ui-button>
                    </div>
                </x-ui-panel>
            @else
                <div class="space-y-2">
                    @foreach($events as $event)
                        @php
                            $sc           = $statusColor[$event->status] ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-600'];
                            $sBorder      = $statusBorderMap[$event->status] ?? 'border-l-slate-300';
                            $sDot         = $statusDotMap[$event->status] ?? 'bg-slate-400';
                            $mrData       = is_array($event->mr_data) ? $event->mr_data : [];

                            // MR-Progress
                            $mrTotal = $mrFields->count();
                            $mrOpen  = 0;
                            foreach ($mrFields as $f) {
                                $val = $mrData['mrf_' . $f->id] ?? null;
                                $first = $f->options[0]['label'] ?? null;
                                if (!$val || (is_string($val) && (stripos($val, 'fehlende') !== false || stripos($val, 'noch nicht') !== false || stripos($val, 'unbekannt') !== false || $val === $first))) {
                                    $mrOpen++;
                                }
                            }
                            $mrDone = $mrTotal - $mrOpen;
                            $dateDay = $event->start_date?->format('d');
                            $dateMonth = $event->start_date ? $monthShort[(int) $event->start_date->format('m')] : '';
                            $dateYear  = $event->start_date?->format('Y');
                            $endLabel = null;
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
                        @endphp

                        <div x-data="{ open: false }" class="bg-white border border-[var(--ui-border)] border-l-4 {{ $sBorder }} rounded-lg overflow-hidden hover:shadow-sm transition">
                            <div class="flex items-stretch">
                                {{-- Datum-Block --}}
                                <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                   class="flex flex-col items-center justify-center px-5 py-3 bg-slate-50/50 min-w-[96px] text-center hover:bg-slate-50 no-underline">
                                    @if($dateDay)
                                        <span class="text-[1.5rem] font-bold text-[var(--ui-secondary)] leading-none">{{ $dateDay }}</span>
                                        <span class="text-[0.6rem] font-semibold text-[var(--ui-muted)] mt-0.5 uppercase tracking-wider">{{ $dateMonth }} {{ $dateYear }}</span>
                                        @if($endLabel)
                                            <span class="text-[0.6rem] text-[var(--ui-muted)] mt-0.5 whitespace-nowrap">→ {{ $endLabel }}</span>
                                        @endif
                                    @else
                                        <span class="text-[0.65rem] text-[var(--ui-muted)] italic">kein Datum</span>
                                    @endif
                                </a>

                                {{-- Mitte: Titel + Meta --}}
                                <div class="flex-1 min-w-0 px-4 py-3 flex flex-col justify-center gap-0.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                           class="text-[0.6rem] font-mono font-bold text-[var(--ui-muted)] hover:text-[var(--ui-primary)] no-underline">
                                            {{ $event->event_number }}
                                        </a>
                                        @if($event->event_type)
                                            <span class="text-[0.55rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $event->event_type }}</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                       class="text-sm font-bold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] no-underline truncate flex items-center gap-1.5">
                                        @if($event->is_highlight)
                                            <span class="text-amber-500 flex-shrink-0" title="Highlight – besonders sehenswert">
                                                @svg('heroicon-s-star', 'w-3.5 h-3.5')
                                            </span>
                                        @endif
                                        <span class="truncate">{{ $event->name ?: 'Unbenannte Veranstaltung' }}</span>
                                    </a>
                                    @php
                                        $customerLabel = $customerLabels[$event->id] ?? null;
                                        // Personenzahl-Spanne ueber alle Tage berechnen.
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
                                    @endphp
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
                                        {{-- Bis-Datum wird im Datums-Block links angezeigt (→ Tag. Monat). --}}
                                    </div>
                                </div>

                                {{-- MR-Progress + Potential (kompakt) --}}
                                @php
                                    $potentialPct = (int) preg_replace('/[^0-9]/', '', (string) ($event->potential ?? ''));
                                    if ($potentialPct >= 90)      { $potBar = 'bg-green-600';   $potText = 'text-green-700'; }
                                    elseif ($potentialPct >= 70)  { $potBar = 'bg-green-500';   $potText = 'text-green-600'; }
                                    elseif ($potentialPct >= 50)  { $potBar = 'bg-amber-500';   $potText = 'text-amber-700'; }
                                    elseif ($potentialPct >= 30)  { $potBar = 'bg-orange-500';  $potText = 'text-orange-700';}
                                    elseif ($potentialPct >= 10)  { $potBar = 'bg-red-500';     $potText = 'text-red-600';  }
                                    else                          { $potBar = 'bg-slate-300';   $potText = 'text-slate-400'; }
                                    $revenue = $revenueByEvent[$event->id] ?? null;
                                @endphp
                                @if($mrTotal > 0 || $potentialPct > 0)
                                    <div class="hidden md:flex flex-col gap-1 px-4 py-3 flex-shrink-0 min-w-[140px]">
                                        @if($mrTotal > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="w-16 h-1 bg-slate-200 rounded-full overflow-hidden">
                                                    <div class="h-full bg-green-500 transition-all" style="width: {{ $mrTotal > 0 ? ($mrDone / $mrTotal) * 100 : 0 }}%"></div>
                                                </div>
                                                <span class="text-[0.6rem] font-mono text-[var(--ui-muted)] whitespace-nowrap">
                                                    {{ $mrDone }}/{{ $mrTotal }}
                                                    @if($mrOpen > 0)
                                                        <span class="text-red-500 font-semibold">· {{ $mrOpen }} offen</span>
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                        @if($potentialPct > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="w-16 h-1 bg-slate-200 rounded-full overflow-hidden">
                                                    <div class="h-full {{ $potBar }} transition-all" style="width: {{ $potentialPct }}%"></div>
                                                </div>
                                                <span class="text-[0.6rem] font-mono whitespace-nowrap {{ $potText }}">
                                                    {{ $potentialPct }}%
                                                    <span class="text-[var(--ui-muted)] uppercase tracking-wider ml-0.5">Potential</span>
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Umsatz --}}
                                @if($revenue !== null && $revenue > 0)
                                    <div class="hidden md:flex items-center px-3 py-3 flex-shrink-0">
                                        <span class="text-[0.78rem] font-bold font-mono text-green-700 whitespace-nowrap" title="Umsatz aus Angebots-Vorgaengen">
                                            {{ number_format($revenue, 2, ',', '.') }} €
                                        </span>
                                    </div>
                                @endif

                                {{-- Status + Datum + Expand --}}
                                <div class="flex items-center gap-3 px-4 py-3 flex-shrink-0">
                                    <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap {{ $sc['bg'] }} {{ $sc['text'] }}">
                                        {{ $event->status ?: 'Option' }}
                                    </span>
                                    @if($event->status_changed_at)
                                        <span class="hidden lg:inline text-[0.58rem] font-mono text-slate-400">{{ $event->status_changed_at->format('d.m.Y') }}</span>
                                    @endif
                                    @if($mrTotal > 0)
                                        <button type="button" @click="open = !open"
                                                class="w-7 h-7 flex items-center justify-center rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition"
                                                :class="open ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] border-[var(--ui-primary)]' : ''">
                                            <svg class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    @endif
                                    <button type="button"
                                            wire:click="delete('{{ $event->uuid }}')"
                                            wire:confirm="Veranstaltung {{ $event->name }} wirklich löschen?"
                                            class="w-7 h-7 flex items-center justify-center rounded border border-red-200 bg-red-50 hover:bg-red-100 text-red-500 transition">
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            </div>

                            {{-- MR-Kachel-Grid (expandable) --}}
                            @if($mrTotal > 0)
                                <div x-show="open" x-cloak class="border-t border-[var(--ui-border)]/60 bg-slate-50/50 px-4 py-3">
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-2">
                                        @foreach($mrFields as $f)
                                            @php
                                                $val = $mrData['mrf_' . $f->id] ?? ($f->options[0]['label'] ?? '—');
                                                $color = 'gray';
                                                foreach (($f->options ?? []) as $opt) {
                                                    if (is_array($opt) && ($opt['label'] ?? '') === $val) {
                                                        $color = $opt['color'] ?? 'gray';
                                                        break;
                                                    }
                                                }
                                                $cc = $colorClass[$color] ?? $colorClass['gray'];
                                            @endphp
                                            <div class="bg-white border rounded-md px-2.5 py-1.5" style="border-color: {{ $cc['dot'] }}40;">
                                                <p class="text-[0.55rem] text-[var(--ui-muted)] m-0 mb-0.5 truncate" title="{{ $f->label }}">{{ $f->label }}</p>
                                                <span class="text-[0.55rem] font-semibold px-1.5 py-0.5 rounded-full"
                                                      style="background: {{ $cc['bg'] }}; color: {{ $cc['fg'] }};">{{ $val }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($events->hasPages())
                    <div class="pt-4">
                        {{ $events->links() }}
                    </div>
                @endif
            @endif
            @endif {{-- /viewMode list --}}

            @if($viewMode === 'calendar')
                {{-- ===== KALENDER-VIEW ===== --}}
                @php
                    // Status-Farben fuer Calendar-Eintrage. Werte spiegeln $statusColor in
                    // CSS-Hex, damit Alpine sie direkt nutzen kann (Tailwind-Klassen sind
                    // dynamisch nicht zur Compile-Time bekannt).
                    $statusColorsJs = [
                        'Vertrag'       => ['bg' => '#dcfce7', 'color' => '#065f46', 'bar' => '#16a34a'],
                        'Definitiv'     => ['bg' => '#f0fdf4', 'color' => '#15803d', 'bar' => '#22c55e'],
                        'Option'        => ['bg' => '#fefce8', 'color' => '#854d0e', 'bar' => '#eab308'],
                        'Abgeschlossen' => ['bg' => '#f1f5f9', 'color' => '#475569', 'bar' => '#94a3b8'],
                        'Storno'        => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'bar' => '#ef4444'],
                        'Warteliste'    => ['bg' => '#fff7ed', 'color' => '#c2410c', 'bar' => '#f97316'],
                        'Tendenz'       => ['bg' => '#faf5ff', 'color' => '#7c3aed', 'bar' => '#a855f7'],
                    ];
                    $calendarViews = [
                        'month'  => 'Monat',
                        'week'   => 'Woche',
                        'day'    => 'Tag',
                        'agenda' => 'Agenda',
                    ];
                    $icalUrl = route('events.calendar.ics', [
                        'status'     => $statusFilter ?: null,
                        'resp'       => $responsibleFilter ?: null,
                        'type'       => $eventTypeFilter ?: null,
                        'loc'        => $locationFilter ?: null,
                        'highlights' => $highlightsOnly ? 1 : null,
                    ]);
                @endphp
                <div x-data="eventsCalendar(
                            {{ json_encode($calendarEvents) }},
                            {{ json_encode($statusColorsJs) }},
                            {{ json_encode($typeColorMap) }},
                            @js($colorMode),
                            @js($calendarView)
                        )"
                     class="bg-white border border-[var(--ui-border)] rounded-lg overflow-hidden">

                    @if(session('eventMoved'))
                        <div class="px-4 py-2 bg-blue-50 border-b border-blue-200 text-[0.7rem] text-blue-800 flex items-center gap-2">
                            @svg('heroicon-o-information-circle', 'w-3.5 h-3.5')
                            {{ session('eventMoved') }}
                        </div>
                    @endif

                    {{-- Header: Navigation + View-Toggle + Color-Mode + iCal --}}
                    <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--ui-border)] flex-wrap gap-3">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="navPrev()"
                                    class="w-7 h-7 border border-slate-200 rounded-md bg-white hover:bg-slate-50 flex items-center justify-center text-slate-500"
                                    title="Zurueck">
                                @svg('heroicon-o-chevron-left', 'w-3.5 h-3.5')
                            </button>
                            <span class="text-sm font-bold text-[var(--ui-secondary)] min-w-[200px] text-center"
                                  x-text="headerLabel"></span>
                            <button type="button" @click="navNext()"
                                    class="w-7 h-7 border border-slate-200 rounded-md bg-white hover:bg-slate-50 flex items-center justify-center text-slate-500"
                                    title="Vor">
                                @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5')
                            </button>
                            <button type="button" @click="goToday()"
                                    class="px-3 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-[0.65rem] font-semibold text-slate-600">
                                Heute
                            </button>
                        </div>

                        <div class="flex items-center gap-3 flex-wrap">
                            {{-- View-Toggle: Monat / Woche / Tag / Agenda --}}
                            <div class="inline-flex items-center bg-slate-100 border border-slate-200 rounded-md p-0.5 gap-0.5">
                                @foreach($calendarViews as $key => $label)
                                    <button type="button"
                                            wire:click="setCalendarView('{{ $key }}')"
                                            @click="view = @js($key)"
                                            class="px-2.5 py-1 rounded text-[0.65rem] font-semibold transition
                                                   {{ $calendarView === $key
                                                       ? 'bg-white text-[var(--ui-secondary)] shadow-sm'
                                                       : 'text-slate-500 hover:text-[var(--ui-secondary)]' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- Color-Mode-Toggle --}}
                            <div class="inline-flex items-center gap-1 text-[0.6rem] text-[var(--ui-muted)]">
                                <span class="uppercase tracking-wider font-bold">Farbe</span>
                                <div class="inline-flex items-center bg-slate-100 border border-slate-200 rounded-md p-0.5 gap-0.5">
                                    <button type="button"
                                            wire:click="setColorMode('status')"
                                            @click="colorMode = 'status'"
                                            class="px-2 py-0.5 rounded text-[0.6rem] font-semibold transition
                                                   {{ $colorMode === 'status'
                                                       ? 'bg-white text-[var(--ui-secondary)] shadow-sm'
                                                       : 'text-slate-500 hover:text-[var(--ui-secondary)]' }}">
                                        Status
                                    </button>
                                    <button type="button"
                                            wire:click="setColorMode('type')"
                                            @click="colorMode = 'type'"
                                            class="px-2 py-0.5 rounded text-[0.6rem] font-semibold transition
                                                   {{ $colorMode === 'type'
                                                       ? 'bg-white text-[var(--ui-secondary)] shadow-sm'
                                                       : 'text-slate-500 hover:text-[var(--ui-secondary)]' }}">
                                        Typ
                                    </button>
                                </div>
                            </div>

                            <span class="text-[0.6rem] text-[var(--ui-muted)]">{{ count($calendarEvents) }} Veranstaltung{{ count($calendarEvents) === 1 ? '' : 'en' }}</span>

                            <a href="{{ $icalUrl }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-[0.62rem] font-semibold text-slate-600 no-underline"
                               title="Als iCal/ICS-Datei herunterladen">
                                @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
                                iCal
                            </a>
                        </div>
                    </div>

                    {{-- =================== MONATS-ANSICHT =================== --}}
                    <div x-show="view === 'month'">
                        <div class="grid grid-cols-7 border-b border-[var(--ui-border)] bg-slate-50">
                            <template x-for="d in ['Mo','Di','Mi','Do','Fr','Sa','So']" :key="d">
                                <div class="px-2 py-1.5 text-center text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]"
                                     x-text="d"></div>
                            </template>
                        </div>
                        <div class="grid grid-cols-7">
                            <template x-for="(cell, idx) in monthCells" :key="idx">
                                <div class="min-h-[96px] px-1.5 py-1 border-b border-r border-slate-100"
                                     :class="cell.isToday ? 'bg-blue-50/40' : (cell.isCurrentMonth ? 'bg-white' : 'bg-slate-50/40')"
                                     @dragover.prevent="dragOverDay = cell.date"
                                     @dragleave="dragOverDay = null"
                                     @drop.prevent="onDropEvent($event, cell.date)"
                                     :style="dragOverDay === cell.date ? 'outline: 2px dashed #3b82f6; outline-offset: -2px;' : ''">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[0.65rem]"
                                              :class="cell.isToday
                                                  ? 'font-bold text-blue-600'
                                                  : (cell.isCurrentMonth ? 'font-medium text-slate-700' : 'text-slate-300')"
                                              x-text="cell.day"></span>
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <template x-for="ev in cell.events" :key="ev.id + '-' + cell.date">
                                            <div draggable="true"
                                                 @dragstart="onDragStart($event, ev)"
                                                 @dragend="onDragEnd()"
                                                 @click="onPillClick($event, ev)"
                                                 class="px-1.5 py-0.5 rounded text-[0.55rem] font-semibold truncate border-l-[3px] transition hover:opacity-80 cursor-grab active:cursor-grabbing select-none"
                                                 :style="eventStyle(ev)"
                                                 :title="eventTooltip(ev)">
                                                <span x-show="ev.is_highlight" class="mr-0.5">⭐</span><span x-text="ev.name"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- =================== WOCHEN-ANSICHT =================== --}}
                    <div x-show="view === 'week'">
                        <div class="grid grid-cols-7 border-b border-[var(--ui-border)] bg-slate-50">
                            <template x-for="cell in weekCells" :key="cell.date">
                                <div class="px-2 py-1.5 text-center text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">
                                    <div x-text="cell.dow"></div>
                                    <div class="text-[0.7rem] mt-0.5"
                                         :class="cell.isToday ? 'text-blue-600' : 'text-slate-700'"
                                         x-text="cell.day"></div>
                                </div>
                            </template>
                        </div>
                        <div class="grid grid-cols-7">
                            <template x-for="cell in weekCells" :key="cell.date">
                                <div class="min-h-[260px] px-1.5 py-2 border-b border-r border-slate-100"
                                     :class="cell.isToday ? 'bg-blue-50/40' : 'bg-white'"
                                     @dragover.prevent="dragOverDay = cell.date"
                                     @dragleave="dragOverDay = null"
                                     @drop.prevent="onDropEvent($event, cell.date)"
                                     :style="dragOverDay === cell.date ? 'outline: 2px dashed #3b82f6; outline-offset: -2px;' : ''">
                                    <div class="flex flex-col gap-1">
                                        <template x-for="ev in cell.events" :key="ev.id + '-' + cell.date">
                                            <div draggable="true"
                                                 @dragstart="onDragStart($event, ev)"
                                                 @dragend="onDragEnd()"
                                                 @click="onPillClick($event, ev)"
                                                 class="px-2 py-1 rounded text-[0.62rem] font-semibold border-l-[3px] transition hover:opacity-80 cursor-grab active:cursor-grabbing select-none"
                                                 :style="eventStyle(ev)"
                                                 :title="eventTooltip(ev)">
                                                <div class="flex items-center gap-1">
                                                    <span x-show="ev.is_highlight">⭐</span>
                                                    <span x-text="ev.event_number" class="text-[0.55rem] opacity-70 font-mono"></span>
                                                </div>
                                                <div x-text="ev.name" class="truncate"></div>
                                                <div x-show="ev.customer" x-text="ev.customer" class="text-[0.55rem] opacity-75 truncate"></div>
                                            </div>
                                        </template>
                                        <div x-show="cell.events.length === 0" class="text-[0.55rem] text-slate-300 text-center pt-4">—</div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- =================== TAGES-ANSICHT =================== --}}
                    <div x-show="view === 'day'" class="p-4">
                        <template x-if="dayEvents.length === 0">
                            <div class="text-center py-12 text-[var(--ui-muted)] text-sm">
                                Keine Veranstaltungen an diesem Tag.
                            </div>
                        </template>
                        <div class="flex flex-col gap-2">
                            <template x-for="ev in dayEvents" :key="ev.id">
                                <a :href="ev.url"
                                   wire:navigate
                                   class="block p-3 rounded-md border-l-[4px] no-underline transition hover:shadow-sm"
                                   :style="eventStyle(ev) + '; border-color: #e2e8f0; border-width: 1px; border-left-width: 4px;'"
                                   :title="eventTooltip(ev)">
                                    <div class="flex items-center justify-between gap-3 flex-wrap">
                                        <div class="flex items-center gap-2">
                                            <span x-show="ev.is_highlight" class="text-amber-500">⭐</span>
                                            <span class="font-mono text-[0.65rem] opacity-75" x-text="ev.event_number"></span>
                                            <span class="text-[0.85rem] font-bold" x-text="ev.name"></span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[0.65rem] opacity-80">
                                            <span x-show="ev.customer" x-text="ev.customer"></span>
                                            <span x-show="ev.location" class="px-1.5 py-0.5 rounded bg-white/40" x-text="ev.location"></span>
                                            <span class="px-1.5 py-0.5 rounded bg-white/40 font-semibold" x-text="ev.status"></span>
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div>

                    {{-- =================== AGENDA-ANSICHT =================== --}}
                    <div x-show="view === 'agenda'" class="divide-y divide-slate-100">
                        <template x-if="agendaGroups.length === 0">
                            <div class="text-center py-12 text-[var(--ui-muted)] text-sm">
                                Keine Veranstaltungen im aktuellen Filter-Bereich.
                            </div>
                        </template>
                        <template x-for="grp in agendaGroups" :key="grp.date">
                            <div class="px-4 py-3">
                                <div class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2"
                                     x-text="grp.label"></div>
                                <div class="flex flex-col gap-1">
                                    <template x-for="ev in grp.events" :key="ev.id + '-' + grp.date">
                                        <a :href="ev.url"
                                           wire:navigate
                                           class="flex items-center gap-3 px-3 py-2 rounded-md border-l-[3px] no-underline transition hover:bg-slate-50"
                                           :style="eventStyle(ev)"
                                           :title="eventTooltip(ev)">
                                            <span x-show="ev.is_highlight">⭐</span>
                                            <span class="font-mono text-[0.6rem] opacity-70" x-text="ev.event_number"></span>
                                            <span class="text-[0.72rem] font-bold flex-1 truncate" x-text="ev.name"></span>
                                            <span x-show="ev.customer" class="text-[0.6rem] opacity-75 truncate max-w-[180px]" x-text="ev.customer"></span>
                                            <span class="text-[0.55rem] font-semibold px-1.5 py-0.5 rounded bg-white/60" x-text="ev.status"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- ===== Verschieben-Modal (Alpine-only) ===== --}}
                    <div x-show="pendingMove !== null"
                         x-cloak
                         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @keydown.window.escape="cancelMove()">
                        {{-- Overlay --}}
                        <div class="absolute inset-0 backdrop-blur-md bg-black/50" @click="cancelMove()"></div>

                        {{-- Modal-Karte --}}
                        <div class="relative z-[101] w-full max-w-md bg-white rounded-xl shadow-2xl border border-slate-200/60 overflow-hidden"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                            {{-- Header --}}
                            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                                    @svg('heroicon-o-arrows-up-down', 'w-5 h-5')
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-[0.9rem] font-bold text-slate-800 m-0 truncate">Veranstaltung verschieben</h3>
                                    <p class="text-[0.65rem] text-slate-500 m-0 truncate" x-text="pendingMove?.eventName + (pendingMove?.customer ? ' · ' + pendingMove?.customer : '')"></p>
                                </div>
                                <button @click="cancelMove()" type="button"
                                        class="text-slate-400 hover:text-slate-700 p-1 -mr-1"
                                        aria-label="Schliessen">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </button>
                            </div>

                            {{-- Body: Vorher/Nachher --}}
                            <div class="px-5 py-4">
                                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <div class="text-[0.55rem] font-bold uppercase tracking-wider text-slate-400">Bisher</div>
                                        <div class="text-[0.78rem] font-semibold text-slate-700 mt-0.5" x-text="formatDateDE(pendingMove?.oldDate)"></div>
                                    </div>
                                    <div class="text-slate-300">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                    </div>
                                    <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                                        <div class="text-[0.55rem] font-bold uppercase tracking-wider text-blue-500">Neu</div>
                                        <div class="text-[0.78rem] font-semibold text-blue-800 mt-0.5" x-text="formatDateDE(pendingMove?.newDate)"></div>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-center">
                                    <span class="text-[0.62rem] font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700"
                                          x-text="(pendingMove?.offsetDays > 0 ? '+' : '') + pendingMove?.offsetDays + ' Tag' + (Math.abs(pendingMove?.offsetDays) === 1 ? '' : 'e')"></span>
                                </div>

                                <div class="mt-4 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-[0.65rem] text-amber-800 flex items-start gap-2">
                                    @svg('heroicon-o-information-circle', 'w-3.5 h-3.5 flex-shrink-0 mt-0.5')
                                    <div>
                                        Alle <span class="font-semibold">Event-Tage</span>, <span class="font-semibold">Raumbuchungen</span> und
                                        <span class="font-semibold">Ablaufplan-Einträge</span> werden um die gleiche Differenz mitgezogen.
                                        Uhrzeiten, Personenzahlen und Raumzuordnungen bleiben unverändert.
                                    </div>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
                                <button type="button" @click="cancelMove()"
                                        class="px-3 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-[0.7rem] font-semibold text-slate-600 transition">
                                    Abbrechen
                                </button>
                                <button type="button" @click="confirmMove()"
                                        class="px-3 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-[0.7rem] font-semibold text-white transition shadow-sm flex items-center gap-1.5">
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                    Verschieben
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif {{-- /viewMode calendar --}}
        </div>

        {{-- Create-Modal: strukturierte Erstkontakt-Erfassung (Lastenheft 2.3.1) --}}
        <x-ui-modal wire:model="showCreateModal" size="lg" :hideFooter="true">
            <x-slot name="header">Neue Veranstaltung — Erstkontakt</x-slot>

            <form wire:submit.prevent="create" class="space-y-5">

                {{-- ===== Sektion 1: Basis ===== --}}
                <section class="space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-4 bg-blue-600 rounded-sm"></span>
                        <h3 class="text-[0.7rem] font-bold uppercase tracking-wider text-[var(--ui-secondary)] m-0">Basis</h3>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name der Veranstaltung *</label>
                        <input wire:model="name" type="text" placeholder="z.B. Sommer-Gala 2026"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('name') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Kunde</label>
                        @php $sc = $crmSlots['customer'] ?? []; @endphp
                        @include('events::partials.crm-company-picker', [
                            'slot'          => 'customer',
                            'available'     => $crmCompanyAvailable,
                            'options'       => $sc['options']   ?? [],
                            'label'         => $sc['label']     ?? null,
                            'url'           => $sc['url']       ?? null,
                            'currentId'     => $sc['currentId'] ?? null,
                            'fallbackField' => 'customer',
                            'placeholder'   => '— CRM-Firma wählen —',
                        ])
                    </div>
                </section>

                {{-- ===== Sektion 2: Zeitraum & Gäste ===== --}}
                <section class="space-y-3 border-t border-[var(--ui-border)] pt-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-4 bg-emerald-600 rounded-sm"></span>
                        <h3 class="text-[0.7rem] font-bold uppercase tracking-wider text-[var(--ui-secondary)] m-0">Zeitraum & Gäste</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Wunschdatum (Start) *</label>
                            <input wire:model.blur="start_date" type="date"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @error('start_date') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Ende</label>
                            <input wire:model="end_date" type="date"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @error('end_date') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Personenzahl (vorbefüllt für alle Tage)</label>
                        <input wire:model="default_pax" type="text" placeholder="z.B. 120 oder 100 – 150"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <p class="mt-1 text-[0.58rem] text-[var(--ui-muted)]">Wird auf jeden Event-Tag in „Personen von/bis" übernommen — kann später pro Tag angepasst werden.</p>
                    </div>
                </section>

                {{-- ===== Sektion 3: Anlass & Zuständigkeit ===== --}}
                <section class="space-y-3 border-t border-[var(--ui-border)] pt-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-4 bg-violet-600 rounded-sm"></span>
                        <h3 class="text-[0.7rem] font-bold uppercase tracking-wider text-[var(--ui-secondary)] m-0">Anlass & Zuständigkeit</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Anlass-Typ</label>
                            <input wire:model="event_type" type="text" list="evt-create-types"
                                   placeholder="z.B. Hochzeit"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <datalist id="evt-create-types">
                                @foreach(($filterOptions['eventTypes'] ?? []) as $t)
                                    <option value="{{ $t }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Verantwortlich</label>
                            <input wire:model="responsible" type="text" list="evt-create-resps"
                                   placeholder="Projektleiter"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            <datalist id="evt-create-resps">
                                @foreach(($filterOptions['responsibles'] ?? []) as $r)
                                    <option value="{{ $r }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Status</label>
                        <select wire:model="status"
                                class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @foreach($statusOptions as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </section>

                {{-- ===== Sektion 4: Eingang & Wiedervorlage ===== --}}
                <section class="space-y-3 border-t border-[var(--ui-border)] pt-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-4 bg-amber-500 rounded-sm"></span>
                        <h3 class="text-[0.7rem] font-bold uppercase tracking-wider text-[var(--ui-secondary)] m-0">Eingang & Wiedervorlage</h3>
                    </div>
                    <div class="grid grid-cols-[1fr_120px_1fr] gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Eingangsdatum</label>
                            <input wire:model="inquiry_date" type="date"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Uhrzeit</label>
                            <input wire:model="inquiry_time" type="time"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Potenzial</label>
                            <select wire:model="potential"
                                    class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                <option value="">— offen —</option>
                                @foreach(\Platform\Events\Tools\CreateEventTool::POTENTIAL_OPTIONS as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-[160px_1fr] gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Wiedervorlage</label>
                            <input wire:model="follow_up_date" type="date"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Notiz zum Erstkontakt</label>
                            <input wire:model="follow_up_note" type="text"
                                   placeholder="z.B. Telefonat mit Frau Müller, Budget ca. 8 k€, möchte Buffet"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </div>
                </section>

                {{-- ===== Sektion 5: Erstinfo automatisch versenden ===== --}}
                <section class="space-y-3 border-t border-[var(--ui-border)] pt-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-4 bg-sky-600 rounded-sm"></span>
                        <h3 class="text-[0.7rem] font-bold uppercase tracking-wider text-[var(--ui-secondary)] m-0">Erstinfo-Versand</h3>
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="send_initial_info"
                               class="mt-0.5 h-4 w-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]/30">
                        <span class="flex-1">
                            <span class="block text-xs font-semibold text-[var(--ui-secondary)]">Erstinfo direkt nach Anlage versenden</span>
                            <span class="block mt-0.5 text-[0.62rem] text-[var(--ui-muted)]">
                                Schickt automatisch eine personalisierte Info-Mail an den Interessenten — basierend auf der in den Einstellungen hinterlegten Dokumentvorlage. Voraussetzung: Vorlage und Email-Channel sind im Settings-Tab „Angebots-Optionen" gepflegt.
                            </span>
                        </span>
                    </label>
                    @if($send_initial_info)
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Empfänger-Mail *</label>
                            <input wire:model="initial_info_to" type="email"
                                   placeholder="kunde@beispiel.de"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @error('initial_info_to') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </section>

                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeCreate">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">
                        <span wire:loading.remove wire:target="create">Veranstaltung anlegen</span>
                        <span wire:loading wire:target="create">Anlegen …</span>
                    </x-ui-button>
                </div>
            </form>
        </x-ui-modal>
    </x-ui-page-container>

    {{-- Alpine-Komponente fuer den Kalender. Globale Funktion: einmalige
         Definition bleibt nach wire:navigate erhalten. --}}
    <script>
        if (typeof window.eventsCalendar === 'undefined') {
            window.eventsCalendar = function (events, statusColors, typeColors, colorMode, view) {
                var now = new Date();
                return {
                    events: events || [],
                    statusColors: statusColors || {},
                    typeColors:   typeColors   || {},
                    colorMode:    colorMode    || 'status',
                    view:         view         || 'month',

                    // Cursor — bedeutet je nach view:
                    //   month  → aktueller Monat
                    //   week   → ein Tag innerhalb der Woche
                    //   day    → der angezeigte Tag
                    //   agenda → Startpunkt der Liste (3 Monate ab Cursor)
                    cursor:  new Date(now.getFullYear(), now.getMonth(), now.getDate()),
                    dragOverDay:  null,
                    draggedId:    null,
                    wasDragging:  false,

                    // Modal-State fuer den Verschieben-Confirm.
                    pendingMove:  null,  // null oder { eventId, eventName, oldDate, newDate, durationDays }

                    monthNames: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
                    dowShort:   ['Mo','Di','Mi','Do','Fr','Sa','So'],

                    fmtDate(dt) {
                        return dt.getFullYear() + '-'
                            + String(dt.getMonth() + 1).padStart(2, '0') + '-'
                            + String(dt.getDate()).padStart(2, '0');
                    },

                    fmtDateLong(dt) {
                        return this.dowShort[(dt.getDay() + 6) % 7] + ', '
                            + dt.getDate() + '. '
                            + this.monthNames[dt.getMonth()] + ' '
                            + dt.getFullYear();
                    },

                    eventsForDate(dt) {
                        var iso = this.fmtDate(dt);
                        return this.events.filter(function (ev) {
                            var start = ev.start_date || '';
                            var end   = ev.end_date   || start;
                            return iso >= start && iso <= end;
                        });
                    },

                    eventStyle(ev) {
                        var c = (this.colorMode === 'type')
                            ? (this.typeColors[ev.event_type] || this.statusColors[ev.status] || {})
                            : (this.statusColors[ev.status]    || {});
                        return 'border-left-color:' + (c.bar   || '#94a3b8')
                             + '; background:'      + (c.bg    || '#f1f5f9')
                             + '; color:'           + (c.color || '#475569');
                    },

                    eventTooltip(ev) {
                        var parts = [];
                        if (ev.is_highlight) parts.push('⭐');
                        parts.push(ev.event_number + ' · ' + ev.name);
                        if (ev.customer)     parts.push('— ' + ev.customer);
                        if (ev.event_type)   parts.push('· Typ: ' + ev.event_type);
                        if (ev.responsible)  parts.push('· ' + ev.responsible);
                        if (ev.location)     parts.push('· ' + ev.location);
                        return parts.join(' ');
                    },

                    // ---------- Navigation pro View ----------
                    navPrev() {
                        var c = new Date(this.cursor);
                        if (this.view === 'month')  c.setMonth(c.getMonth() - 1);
                        if (this.view === 'week')   c.setDate(c.getDate() - 7);
                        if (this.view === 'day')    c.setDate(c.getDate() - 1);
                        if (this.view === 'agenda') c.setMonth(c.getMonth() - 1);
                        this.cursor = c;
                    },
                    navNext() {
                        var c = new Date(this.cursor);
                        if (this.view === 'month')  c.setMonth(c.getMonth() + 1);
                        if (this.view === 'week')   c.setDate(c.getDate() + 7);
                        if (this.view === 'day')    c.setDate(c.getDate() + 1);
                        if (this.view === 'agenda') c.setMonth(c.getMonth() + 1);
                        this.cursor = c;
                    },
                    goToday() {
                        var t = new Date();
                        this.cursor = new Date(t.getFullYear(), t.getMonth(), t.getDate());
                    },

                    get headerLabel() {
                        var c = this.cursor;
                        if (this.view === 'month') {
                            return this.monthNames[c.getMonth()] + ' ' + c.getFullYear();
                        }
                        if (this.view === 'week') {
                            var start = this.weekStart(c);
                            var end   = new Date(start); end.setDate(end.getDate() + 6);
                            return 'KW' + this.isoWeek(start) + ' · '
                                 + start.getDate() + '.' + (start.getMonth() + 1) + '.'
                                 + ' – '
                                 + end.getDate()   + '.' + (end.getMonth() + 1)   + '.' + end.getFullYear();
                        }
                        if (this.view === 'day') {
                            return this.fmtDateLong(c);
                        }
                        return 'Agenda ab ' + this.monthNames[c.getMonth()] + ' ' + c.getFullYear();
                    },

                    weekStart(dt) {
                        var d = new Date(dt);
                        var shift = (d.getDay() + 6) % 7; // Mo=0 .. So=6
                        d.setDate(d.getDate() - shift);
                        d.setHours(0, 0, 0, 0);
                        return d;
                    },

                    isoWeek(dt) {
                        var d = new Date(Date.UTC(dt.getFullYear(), dt.getMonth(), dt.getDate()));
                        var dayNum = d.getUTCDay() || 7;
                        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
                        var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
                        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
                    },

                    // ---------- Monats-View ----------
                    get monthCells() {
                        var year  = this.cursor.getFullYear();
                        var month = this.cursor.getMonth();
                        var firstDay = new Date(year, month, 1);
                        var lastDay  = new Date(year, month + 1, 0);
                        var startDow = (firstDay.getDay() + 6) % 7;
                        var daysInMonth = lastDay.getDate();
                        var today = new Date(); today.setHours(0, 0, 0, 0);

                        var cells = [];
                        var prevLast = new Date(year, month, 0).getDate();
                        for (var i = startDow - 1; i >= 0; i--) {
                            var d = prevLast - i;
                            var dt = new Date(year, month - 1, d);
                            cells.push({ day: d, date: this.fmtDate(dt), isCurrentMonth: false, isToday: false, events: this.eventsForDate(dt) });
                        }
                        for (var d = 1; d <= daysInMonth; d++) {
                            var dt = new Date(year, month, d);
                            cells.push({ day: d, date: this.fmtDate(dt), isCurrentMonth: true, isToday: dt.getTime() === today.getTime(), events: this.eventsForDate(dt) });
                        }
                        var remaining = 42 - cells.length;
                        for (var d = 1; d <= remaining; d++) {
                            var dt = new Date(year, month + 1, d);
                            cells.push({ day: d, date: this.fmtDate(dt), isCurrentMonth: false, isToday: false, events: this.eventsForDate(dt) });
                        }
                        return cells;
                    },

                    // ---------- Wochen-View ----------
                    get weekCells() {
                        var start = this.weekStart(this.cursor);
                        var today = new Date(); today.setHours(0, 0, 0, 0);
                        var cells = [];
                        for (var i = 0; i < 7; i++) {
                            var dt = new Date(start); dt.setDate(start.getDate() + i);
                            cells.push({
                                day: dt.getDate(),
                                dow: this.dowShort[i],
                                date: this.fmtDate(dt),
                                isToday: dt.getTime() === today.getTime(),
                                events: this.eventsForDate(dt),
                            });
                        }
                        return cells;
                    },

                    // ---------- Tages-View ----------
                    get dayEvents() {
                        return this.eventsForDate(this.cursor);
                    },

                    // ---------- Agenda-View: alle Events ab Cursor, gruppiert nach Tag ----------
                    get agendaGroups() {
                        var cursor = new Date(this.cursor); cursor.setHours(0, 0, 0, 0);
                        var horizon = new Date(cursor); horizon.setMonth(horizon.getMonth() + 3);
                        // Map: date → events
                        var bucket = {};
                        for (var i = 0; i < this.events.length; i++) {
                            var ev = this.events[i];
                            if (!ev.start_date) continue;
                            var s = new Date(ev.start_date + 'T00:00:00');
                            var e = new Date((ev.end_date || ev.start_date) + 'T00:00:00');
                            // Start frühestens beim Cursor.
                            var iter = (s < cursor) ? new Date(cursor) : new Date(s);
                            while (iter <= e && iter <= horizon) {
                                var key = this.fmtDate(iter);
                                (bucket[key] = bucket[key] || []).push(ev);
                                iter.setDate(iter.getDate() + 1);
                            }
                        }
                        var keys = Object.keys(bucket).sort();
                        var out = [];
                        for (var k = 0; k < keys.length; k++) {
                            var dt = new Date(keys[k] + 'T00:00:00');
                            out.push({ date: keys[k], label: this.fmtDateLong(dt), events: bucket[keys[k]] });
                        }
                        return out;
                    },

                    // ---------- Drag & Drop ----------
                    onDragStart(e, payload) {
                        this.draggedId   = payload.id;
                        this.wasDragging = true;
                        e.dataTransfer.effectAllowed = 'move';
                        // Manche Browser starten den Drag nicht, wenn dataTransfer leer bleibt.
                        try { e.dataTransfer.setData('text/plain', String(payload.id)); } catch (_) {}
                    },

                    onDragEnd() {
                        // Click-Event feuert NACH dragend — Flag erst danach loeschen.
                        var self = this;
                        setTimeout(function () { self.wasDragging = false; }, 100);
                    },

                    onPillClick(e, payload) {
                        // Wenn gerade gezogen wurde, Click unterdruecken (kein Navigate).
                        if (this.wasDragging) {
                            e.preventDefault();
                            e.stopPropagation();
                            return;
                        }
                        // SPA-Navigation analog zu wire:navigate.
                        if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                            window.Livewire.navigate(payload.url);
                        } else {
                            window.location.href = payload.url;
                        }
                    },

                    onDropEvent(e, targetDate) {
                        this.dragOverDay = null;
                        if (!this.draggedId) return;
                        var movedEv = this.events.find(function (x) { return x.id === this.draggedId; }.bind(this));
                        if (!movedEv) { this.draggedId = null; return; }
                        if (movedEv.start_date === targetDate) { this.draggedId = null; return; }
                        // Anstatt confirm(): Modal mit allen Details oeffnen.
                        var oldStart = new Date(movedEv.start_date + 'T00:00:00');
                        var oldEnd   = new Date((movedEv.end_date || movedEv.start_date) + 'T00:00:00');
                        this.pendingMove = {
                            eventId:      movedEv.id,
                            eventName:    movedEv.name,
                            customer:     movedEv.customer || '',
                            oldDate:      movedEv.start_date,
                            newDate:      targetDate,
                            durationDays: Math.round((oldEnd - oldStart) / 86400000),
                            offsetDays:   Math.round((new Date(targetDate + 'T00:00:00') - oldStart) / 86400000),
                        };
                    },

                    confirmMove() {
                        if (!this.pendingMove) return;
                        var pm = this.pendingMove;
                        var movedEv = this.events.find(function (x) { return x.id === pm.eventId; });
                        if (!movedEv) { this.pendingMove = null; this.draggedId = null; return; }
                        // Optimistic UI: lokal verschieben.
                        var newStart = new Date(pm.newDate + 'T00:00:00');
                        var newEnd   = new Date(newStart); newEnd.setDate(newStart.getDate() + pm.durationDays);
                        movedEv.start_date = this.fmtDate(newStart);
                        movedEv.end_date   = this.fmtDate(newEnd);
                        // Server-Round-Trip.
                        this.$wire.moveEvent(pm.eventId, pm.newDate);
                        this.pendingMove = null;
                        this.draggedId   = null;
                    },

                    cancelMove() {
                        this.pendingMove = null;
                        this.draggedId   = null;
                    },

                    formatDateDE(iso) {
                        if (!iso) return '';
                        var d = new Date(iso + 'T00:00:00');
                        return this.dowShort[(d.getDay() + 6) % 7] + ', '
                             + String(d.getDate()).padStart(2, '0') + '.'
                             + String(d.getMonth() + 1).padStart(2, '0') + '.'
                             + d.getFullYear();
                    },
                };
            };
        }
    </script>
</x-ui-page>
