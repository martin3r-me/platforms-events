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

    <x-ui-page-container>
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

        {{-- Spacer zur Breadcrumb-Leiste --}}
        <div aria-hidden="true" style="height:0.625rem;"></div>

        <div class="space-y-6">

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

            {{-- Filter-Panel: in Liste komplett, im Kalender ohne Period-Tabs (Kalender hat eigene Monats-Navigation) --}}
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
                @endphp
                <div x-data="eventsCalendar({{ json_encode($calendarEvents) }}, {{ json_encode($statusColorsJs) }})"
                     class="bg-white border border-[var(--ui-border)] rounded-lg overflow-hidden">

                    {{-- Header: Monats-Navigation --}}
                    <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--ui-border)]">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="prevMonth()"
                                    class="w-7 h-7 border border-slate-200 rounded-md bg-white hover:bg-slate-50 flex items-center justify-center text-slate-500">
                                @svg('heroicon-o-chevron-left', 'w-3.5 h-3.5')
                            </button>
                            <span class="text-sm font-bold text-[var(--ui-secondary)] min-w-[160px] text-center"
                                  x-text="monthNames[currentMonth] + ' ' + currentYear"></span>
                            <button type="button" @click="nextMonth()"
                                    class="w-7 h-7 border border-slate-200 rounded-md bg-white hover:bg-slate-50 flex items-center justify-center text-slate-500">
                                @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5')
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[0.6rem] text-[var(--ui-muted)]">{{ count($calendarEvents) }} Veranstaltung{{ count($calendarEvents) === 1 ? '' : 'en' }}</span>
                            <button type="button" @click="goToday()"
                                    class="px-3 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-[0.65rem] font-semibold text-slate-600">
                                Heute
                            </button>
                        </div>
                    </div>

                    {{-- Wochentag-Header --}}
                    <div class="grid grid-cols-7 border-b border-[var(--ui-border)] bg-slate-50">
                        <template x-for="d in ['Mo','Di','Mi','Do','Fr','Sa','So']" :key="d">
                            <div class="px-2 py-1.5 text-center text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]"
                                 x-text="d"></div>
                        </template>
                    </div>

                    {{-- Kalender-Grid (6 Wochen × 7 Tage = 42 Zellen) --}}
                    <div class="grid grid-cols-7">
                        <template x-for="(cell, idx) in calendarCells" :key="idx">
                            <div class="min-h-[96px] px-1.5 py-1 border-b border-r border-slate-100"
                                 :class="cell.isToday ? 'bg-blue-50/40' : (cell.isCurrentMonth ? 'bg-white' : 'bg-slate-50/40')">
                                {{-- Tag-Nummer --}}
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[0.65rem]"
                                          :class="cell.isToday
                                              ? 'font-bold text-blue-600'
                                              : (cell.isCurrentMonth ? 'font-medium text-slate-700' : 'text-slate-300')"
                                          x-text="cell.day"></span>
                                </div>
                                {{-- Events an diesem Tag --}}
                                <div class="flex flex-col gap-0.5">
                                    <template x-for="ev in cell.events" :key="ev.id">
                                        <a :href="ev.url"
                                           wire:navigate
                                           class="block px-1.5 py-0.5 rounded text-[0.55rem] font-semibold no-underline truncate border-l-[3px] transition hover:opacity-80"
                                           :style="'border-left-color: ' + (statusColors[ev.status]?.bar || '#94a3b8')
                                               + '; background:' + (statusColors[ev.status]?.bg || '#f1f5f9')
                                               + '; color:' + (statusColors[ev.status]?.color || '#475569')"
                                           :title="(ev.is_highlight ? '⭐ ' : '') + ev.event_number + ' · ' + ev.name + (ev.customer ? ' — ' + ev.customer : '')">
                                            <span x-show="ev.is_highlight" class="mr-0.5">⭐</span><span x-text="ev.name"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @endif {{-- /viewMode calendar --}}
        </div>

        {{-- Create-Modal --}}
        <x-ui-modal wire:model="showCreateModal" size="md" :hideFooter="true">
            <x-slot name="header">Neue Veranstaltung</x-slot>

            <form wire:submit.prevent="create" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name *</label>
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

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Start *</label>
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
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Status</label>
                    <select wire:model="status"
                            class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @foreach($statusOptions as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

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
            window.eventsCalendar = function (events, statusColors) {
                var now = new Date();
                return {
                    events: events || [],
                    statusColors: statusColors || {},
                    currentMonth: now.getMonth(),
                    currentYear:  now.getFullYear(),
                    monthNames: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],

                    prevMonth() {
                        if (this.currentMonth === 0) { this.currentMonth = 11; this.currentYear--; }
                        else { this.currentMonth--; }
                    },
                    nextMonth() {
                        if (this.currentMonth === 11) { this.currentMonth = 0; this.currentYear++; }
                        else { this.currentMonth++; }
                    },
                    goToday() {
                        var t = new Date();
                        this.currentMonth = t.getMonth();
                        this.currentYear  = t.getFullYear();
                    },

                    fmtDate(dt) {
                        return dt.getFullYear() + '-'
                            + String(dt.getMonth() + 1).padStart(2, '0') + '-'
                            + String(dt.getDate()).padStart(2, '0');
                    },

                    eventsForDate(dt) {
                        var iso = this.fmtDate(dt);
                        return this.events.filter(function (ev) {
                            var start = ev.start_date || '';
                            var end   = ev.end_date   || start;
                            return iso >= start && iso <= end;
                        });
                    },

                    get calendarCells() {
                        var year  = this.currentYear;
                        var month = this.currentMonth;
                        var firstDay  = new Date(year, month, 1);
                        var lastDay   = new Date(year, month + 1, 0);
                        // Mo=0 .. So=6 (Wochenstart: Montag wie im Alt-System).
                        var startDow = (firstDay.getDay() + 6) % 7;
                        var daysInMonth = lastDay.getDate();
                        var today = new Date(); today.setHours(0, 0, 0, 0);

                        var cells = [];

                        // Vormonat (Padding bis Wochenstart).
                        var prevLast = new Date(year, month, 0).getDate();
                        for (var i = startDow - 1; i >= 0; i--) {
                            var d = prevLast - i;
                            var dt = new Date(year, month - 1, d);
                            cells.push({
                                day: d,
                                date: this.fmtDate(dt),
                                isCurrentMonth: false,
                                isToday: false,
                                events: this.eventsForDate(dt),
                            });
                        }

                        // Aktueller Monat.
                        for (var d = 1; d <= daysInMonth; d++) {
                            var dt = new Date(year, month, d);
                            cells.push({
                                day: d,
                                date: this.fmtDate(dt),
                                isCurrentMonth: true,
                                isToday: dt.getTime() === today.getTime(),
                                events: this.eventsForDate(dt),
                            });
                        }

                        // Folgemonat (auf 42 Zellen = 6 Reihen auffuellen).
                        var remaining = 42 - cells.length;
                        for (var d = 1; d <= remaining; d++) {
                            var dt = new Date(year, month + 1, d);
                            cells.push({
                                day: d,
                                date: this.fmtDate(dt),
                                isCurrentMonth: false,
                                isToday: false,
                                events: this.eventsForDate(dt),
                            });
                        }

                        return cells;
                    },
                };
            };
        }
    </script>
</x-ui-page>
