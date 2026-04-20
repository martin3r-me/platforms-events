<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Events Dashboard" icon="heroicon-o-calendar-days" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <x-ui-panel title="Events" subtitle="Plane und verwalte deine Veranstaltungen">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        @svg('heroicon-o-calendar-days', 'w-16 h-16 text-[var(--ui-primary)] mx-auto')
                    </div>
                    <h2 class="text-xl font-semibold text-[var(--ui-secondary)] mb-2">
                        Veranstaltungen
                    </h2>
                    <p class="text-[var(--ui-muted)]">
                        Alle Events mit Tagen, Räumen, Ablaufplan und Notizen.
                    </p>
                </div>
            </x-ui-panel>

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
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
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
