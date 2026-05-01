{{-- Basis-Tab: Kanban-Board mit Backlog + konfigurierbaren Spalten --}}
<div class="h-full w-full overflow-x-auto">
    <x-ui-kanban-board wire:sortable="updateSlotOrder" wire:sortable-group="updateCardOrder">

        {{-- Backlog-Spalte --}}
        @php $backlog = $boardGroups->first(fn($g) => $g->isBacklog); @endphp
        @if($backlog)
            <x-ui-kanban-column :title="'Backlog'" :sortable-id="null" :scrollable="true">
                <x-slot name="extra">
                    <span class="text-xs text-[var(--ui-muted)] font-medium">
                        {{ $backlog->cards->count() }} Karten
                    </span>
                </x-slot>
                @foreach($backlog->cards as $card)
                    @include('events::partials.board-panel-card', ['card' => $card])
                @endforeach
            </x-ui-kanban-column>
        @endif

        {{-- User-Spalten (sortierbar) --}}
        @foreach($boardGroups->filter(fn($g) => !$g->isBacklog) as $column)
            <x-ui-kanban-column :title="$column->label" :sortable-id="$column->id" :scrollable="true">
                <x-slot name="extra">
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-[var(--ui-muted)] font-medium">{{ $column->cards->count() }}</span>
                        {{-- Inline-Rename --}}
                        <div x-data="{ editing: false, name: @js($column->label) }" class="inline-flex">
                            <button x-show="!editing" @click.stop="editing = true"
                                    class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] p-0.5" title="Umbenennen">
                                @svg('heroicon-o-pencil', 'w-3 h-3')
                            </button>
                            <form x-show="editing" @submit.prevent="$wire.renameBoardSlot({{ $column->id }}, name); editing = false" class="flex items-center gap-1">
                                <input x-model="name" x-ref="renameInput" @keydown.escape="editing = false"
                                       x-init="$watch('editing', v => { if(v) $nextTick(() => $refs.renameInput.focus()) })"
                                       class="w-24 px-1 py-0.5 text-xs border border-[var(--ui-border)] rounded" @click.stop>
                                <button type="submit" class="text-green-600 p-0.5">@svg('heroicon-o-check', 'w-3 h-3')</button>
                                <button type="button" @click.stop="editing = false" class="text-[var(--ui-muted)] p-0.5">@svg('heroicon-o-x-mark', 'w-3 h-3')</button>
                            </form>
                        </div>
                        {{-- Spalte löschen --}}
                        <button wire:click="deleteBoardSlot({{ $column->id }})"
                                wire:confirm="Spalte löschen? Cards wandern zurück in den Backlog."
                                class="text-[var(--ui-muted)] hover:text-red-500 p-0.5" title="Spalte löschen">
                            @svg('heroicon-o-trash', 'w-3 h-3')
                        </button>
                    </div>
                </x-slot>
                @foreach($column->cards as $card)
                    @include('events::partials.board-panel-card', ['card' => $card])
                @endforeach
            </x-ui-kanban-column>
        @endforeach

    </x-ui-kanban-board>
</div>
