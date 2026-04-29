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
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                <span class="flex items-center gap-2">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neue Veranstaltung
                </span>
            </x-ui-button>
        </x-ui-page-actionbar>

        {{-- Spacer zur Breadcrumb-Leiste --}}
        <div aria-hidden="true" style="height:0.625rem;"></div>

        <div class="space-y-6">

            {{-- Stats --}}
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

            {{-- Filter-Panel --}}
            <x-ui-panel>
                <div class="flex flex-col gap-4">
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

                    <div class="flex items-center gap-3 flex-wrap border-t border-[var(--ui-border)]/40 pt-4">
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
                    </div>
                </div>
            </x-ui-panel>

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
                                       class="text-sm font-bold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] no-underline truncate">
                                        {{ $event->name ?: 'Unbenannte Veranstaltung' }}
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

                                {{-- MR-Progress --}}
                                @if($mrTotal > 0)
                                    <div class="hidden md:flex items-center gap-2 px-4 py-3 flex-shrink-0">
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
</x-ui-page>
