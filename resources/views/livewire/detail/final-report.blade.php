<div class="space-y-4">
    <x-ui-panel>
        <div class="p-4 flex justify-between items-center border-b border-[var(--ui-border)]">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)]">Schlussbericht</h3>
                <p class="text-[0.62rem] text-[var(--ui-muted)]">Zusammenfassung der Veranstaltung</p>
            </div>
            <x-ui-button variant="secondary-outline" size="sm"
                         :href="route('events.final-report.pdf', ['event' => $event->slug])">
                @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5 inline mr-1') PDF
            </x-ui-button>
        </div>

        <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <p class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">VA-Nummer</p>
                    <p class="font-mono font-bold">{{ $event->event_number }}</p>
                </div>
                <div>
                    <p class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Status</p>
                    <p>{{ $event->status }}</p>
                </div>
                <div>
                    <p class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Name</p>
                    <p>{{ $event->name }}</p>
                </div>
                <div>
                    <p class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Kunde</p>
                    <p>{{ $event->customer ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Zeitraum</p>
                    <p class="font-mono">{{ $event->start_date?->format('d.m.Y') }}@if($event->end_date) – {{ $event->end_date->format('d.m.Y') }} @endif</p>
                </div>
                <div>
                    <p class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Verantwortlich</p>
                    <p>{{ $event->responsible ?: '—' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-5 gap-3 pt-3 border-t border-[var(--ui-border)]">
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $event->days->count() }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Tage</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $event->bookings->count() }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Buchungen</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $event->scheduleItems->count() }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Ablaufpunkte</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $event->quotes->count() }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Angebote</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $event->invoices->count() }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Rechnungen</p>
                </div>
            </div>

            @if($event->bookings->isNotEmpty())
                <div class="pt-3 border-t border-[var(--ui-border)]">
                    <p class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)] mb-2">Räume</p>
                    <div class="flex gap-1.5 flex-wrap">
                        @foreach($event->bookings as $b)
                            <span class="text-[0.62rem] font-mono font-bold px-2 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/60">
                                {{ $b->location?->kuerzel ?: $b->raum }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-ui-panel>
</div>
