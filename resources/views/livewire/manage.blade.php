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

            {{-- Tabelle --}}
            <x-ui-panel title="Veranstaltungen" subtitle="Klick auf Name oder VA-Nr. öffnet die Detail-Ansicht">
                @if($events->isEmpty())
                    <div class="p-12 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-calendar-days', 'w-12 h-12 text-[var(--ui-muted)] mx-auto')
                        </div>
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
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">VA-Nr.</th>
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Kunde</th>
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Zeitraum</th>
                                    <th class="px-4 py-3 text-center text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Tage</th>
                                    <th class="px-4 py-3 text-center text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Status</th>
                                    <th class="px-4 py-3 w-24"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($events as $event)
                                    @php $sc = $statusColor[$event->status] ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-600']; @endphp
                                    <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60 transition-colors">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                               class="text-xs font-mono font-bold text-[var(--ui-primary)] hover:underline">
                                                {{ $event->event_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('events.show', ['slug' => $event->slug]) }}" wire:navigate
                                               class="text-sm font-semibold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)]">
                                                {{ $event->name }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs text-[var(--ui-muted)]">{{ $event->customer ?: '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs font-mono text-[var(--ui-muted)]">
                                                {{ $event->start_date?->format('d.m.Y') ?: '—' }}
                                                @if($event->end_date && $event->end_date != $event->start_date)
                                                    – {{ $event->end_date->format('d.m.Y') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="text-xs font-mono text-[var(--ui-muted)]">{{ $event->days_count }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full {{ $sc['bg'] }} {{ $sc['text'] }}">
                                                {{ $event->status ?: 'Option' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <x-ui-button variant="secondary-outline" size="sm" :href="route('events.show', ['slug' => $event->slug])" wire:navigate>
                                                    Öffnen
                                                </x-ui-button>
                                                <x-ui-button
                                                    variant="danger-outline"
                                                    size="sm"
                                                    wire:click="delete('{{ $event->uuid }}')"
                                                    wire:confirm="Veranstaltung „{{ $event->name }}“ wirklich löschen?"
                                                >
                                                    @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                                </x-ui-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($events->hasPages())
                        <div class="p-4 border-t border-[var(--ui-border)]">
                            {{ $events->links() }}
                        </div>
                    @endif
                @endif
            </x-ui-panel>
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
                    <input wire:model="customer" type="text" placeholder="z.B. Max Mustermann GmbH"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Start *</label>
                        <input wire:model="start_date" type="date"
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
