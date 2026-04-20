<div>
    <x-ui-panel title="Aktivitäten" subtitle="Änderungen und Ereignisse zu diesem Event">
        @if($activities->isEmpty())
            <div class="p-12 text-center">
                @svg('heroicon-o-bolt', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Noch keine Aktivitäten</p>
                <p class="text-xs text-[var(--ui-muted)]">Aktivitäten werden hier protokolliert sobald Statuswechsel und größere Änderungen geloggt werden.</p>
            </div>
        @else
            <div class="divide-y divide-[var(--ui-border)]/40">
                @foreach($activities as $activity)
                    <div class="p-4 flex items-start gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                            @php
                                $icon = match ($activity->type) {
                                    'status'  => 'heroicon-o-arrow-path',
                                    'created' => 'heroicon-o-plus-circle',
                                    'updated' => 'heroicon-o-pencil',
                                    'deleted' => 'heroicon-o-trash',
                                    'email'   => 'heroicon-o-envelope',
                                    default   => 'heroicon-o-bolt',
                                };
                            @endphp
                            @svg($icon, 'w-4 h-4 text-[var(--ui-muted)]')
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 text-[0.62rem] text-[var(--ui-muted)] mb-0.5">
                                <span class="font-semibold uppercase tracking-wider">{{ $activity->type }}</span>
                                <span>·</span>
                                <span class="font-mono">{{ $activity->created_at->format('d.m.Y H:i') }}</span>
                                @if($activity->user)
                                    <span>·</span>
                                    <span>{{ $activity->user }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-[var(--ui-secondary)]">{{ $activity->description }}</p>
                        </div>
                        <button wire:click="delete('{{ $activity->uuid }}')"
                                wire:confirm="Eintrag löschen?"
                                class="flex-shrink-0 text-[var(--ui-muted)] hover:text-red-600 p-1">
                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui-panel>
</div>
