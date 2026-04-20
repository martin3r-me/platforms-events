<div class="space-y-4">
    <x-ui-panel>
        <div class="p-4 flex justify-between items-center border-b border-[var(--ui-border)]">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)]">Projekt-Function</h3>
                <p class="text-[0.62rem] text-[var(--ui-muted)]">Ablauf-Uebersicht fuer die Event-Durchfuehrung</p>
            </div>
            <x-ui-button variant="secondary-outline" size="sm"
                         :href="route('events.projekt-function.pdf', ['event' => $event->slug])">
                @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5 inline mr-1') PDF
            </x-ui-button>
        </div>

        <div class="p-5 space-y-5">
            @foreach($event->days->sortBy('sort_order') as $day)
                @php
                    $bookings = $event->bookings->filter(fn($b) => $b->datum === $day->datum?->format('Y-m-d'));
                    $schedule = $event->scheduleItems->filter(fn($s) => $s->datum === $day->datum?->format('Y-m-d') || $s->datum === $day->datum?->format('d.m.Y'));
                @endphp
                <div class="border border-[var(--ui-border)] rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-3 rounded-full" style="background: {{ $day->color }}"></span>
                        <h4 class="text-sm font-bold text-[var(--ui-secondary)]">{{ $day->label }}</h4>
                        <span class="text-[0.62rem] text-[var(--ui-muted)] font-mono">{{ $day->datum?->format('d.m.Y') }} · {{ $day->day_of_week }}</span>
                        @if($day->von || $day->bis)
                            <span class="text-[0.62rem] text-[var(--ui-muted)] font-mono">· {{ $day->von }}–{{ $day->bis }} Uhr</span>
                        @endif
                    </div>

                    @if($bookings->isNotEmpty())
                        <div class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)] mb-1">Räume</div>
                        <ul class="text-xs mb-3 space-y-0.5">
                            @foreach($bookings as $b)
                                <li class="text-[var(--ui-secondary)]">
                                    <span class="font-mono font-bold">{{ $b->location?->kuerzel ?: $b->raum }}</span>:
                                    {{ $b->beginn }}–{{ $b->ende }}
                                    @if($b->pers) · {{ $b->pers }} Pers @endif
                                    @if($b->bestuhlung) · {{ $b->bestuhlung }} @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($schedule->isNotEmpty())
                        <div class="text-[0.62rem] font-bold uppercase text-[var(--ui-muted)] mb-1">Ablauf</div>
                        <ul class="text-xs space-y-0.5">
                            @foreach($schedule as $s)
                                <li class="text-[var(--ui-secondary)]">
                                    <span class="font-mono">{{ $s->von }}{{ $s->bis ? '–' . $s->bis : '' }}</span>
                                    · {{ $s->beschreibung }}
                                    @if($s->raum) @ {{ $s->raum }} @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($bookings->isEmpty() && $schedule->isEmpty())
                        <p class="text-xs text-[var(--ui-muted)] italic">Keine Räume/Ablaufpunkte fuer diesen Tag.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-ui-panel>
</div>
