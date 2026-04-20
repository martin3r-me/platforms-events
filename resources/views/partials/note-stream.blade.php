{{-- Wiederverwendbarer Notiz-Stream mit Inline-Edit und User-Ownership --}}
@php $currentUserId = auth()->id(); @endphp
<div x-data="{ newText: '' }" class="p-3 space-y-2">
    @if($notes->isEmpty())
        <div class="py-4 text-center border border-dashed border-[var(--ui-border)]/60 rounded-md bg-white">
            <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch keine Einträge.</p>
        </div>
    @else
        <div class="space-y-2 max-h-60 overflow-y-auto">
            @foreach($notes as $note)
                @php $canEdit = $note->user_id === $currentUserId; @endphp
                <div class="p-2 rounded-md bg-white border border-[var(--ui-border)]/40 group"
                     x-data="{ editing: false, text: @js($note->text) }">
                    <div class="flex items-center gap-2 text-[0.58rem] text-[var(--ui-muted)] mb-1">
                        <span class="font-semibold">{{ $note->user_name }}</span>
                        <span class="font-mono">{{ $note->created_at->format('d.m.Y H:i') }}</span>
                        @if($canEdit)
                            <div class="ml-auto flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                                <button type="button" x-show="!editing" @click="editing = true; text = @js($note->text)"
                                        class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] p-0.5 rounded" title="Bearbeiten">
                                    @svg('heroicon-o-pencil', 'w-3 h-3')
                                </button>
                                <button type="button" x-show="editing"
                                        @click="$wire.updateInlineNote(@js($note->uuid), text); editing = false"
                                        class="text-green-600 hover:bg-green-50 p-0.5 rounded" title="Speichern">
                                    @svg('heroicon-o-check', 'w-3 h-3')
                                </button>
                                <button type="button" x-show="editing" @click="editing = false; text = @js($note->text)"
                                        class="text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] p-0.5 rounded" title="Abbrechen">
                                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                                </button>
                                <button type="button" x-show="!editing"
                                        wire:click="deleteNote('{{ $note->uuid }}')" wire:confirm="Notiz löschen?"
                                        class="text-red-500 hover:bg-red-50 p-0.5 rounded" title="Löschen">
                                    @svg('heroicon-o-trash', 'w-3 h-3')
                                </button>
                            </div>
                        @else
                            <span class="ml-auto text-[0.55rem] italic opacity-60">nur eigene editierbar</span>
                        @endif
                    </div>
                    <p x-show="!editing" class="text-xs text-[var(--ui-secondary)] whitespace-pre-wrap">{{ $note->text }}</p>
                    @if($canEdit)
                        <textarea x-show="editing" x-cloak
                                  x-model="text" rows="3"
                                  @keydown.ctrl.enter.prevent="$wire.updateInlineNote(@js($note->uuid), text); editing = false"
                                  class="w-full border border-[var(--ui-primary)]/40 rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    <div class="flex gap-2 items-stretch pt-2 border-t border-[var(--ui-border)]/40">
        <textarea x-model="newText" rows="2" placeholder="Neue Notiz… (Strg+Enter)"
                  @keydown.ctrl.enter.prevent="$wire.addInlineNote('{{ $type }}', newText); newText = ''"
                  class="flex-1 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
        <button type="button"
                @click="$wire.addInlineNote('{{ $type }}', newText); newText = ''"
                class="px-3 bg-[var(--ui-primary)] text-white rounded-md text-xs hover:opacity-90">
            @svg('heroicon-o-plus', 'w-3.5 h-3.5')
        </button>
    </div>
</div>
