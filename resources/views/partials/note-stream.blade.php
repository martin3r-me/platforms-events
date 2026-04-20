{{-- Wiederverwendbarer Notiz-Stream (Alpine-basiert fuer schnelle Eingabe) --}}
<div x-data="{ newText: '' }" class="p-3 space-y-2">
    @if($notes->isEmpty())
        <div class="py-4 text-center border border-dashed border-[var(--ui-border)]/60 rounded-md bg-white">
            <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch keine Einträge.</p>
        </div>
    @else
        <div class="space-y-2 max-h-60 overflow-y-auto">
            @foreach($notes as $note)
                <div class="p-2 rounded-md bg-white border border-[var(--ui-border)]/40 group">
                    <div class="flex items-center gap-2 text-[0.58rem] text-[var(--ui-muted)] mb-1">
                        <span class="font-semibold">{{ $note->user_name }}</span>
                        <span class="font-mono">{{ $note->created_at->format('d.m.Y H:i') }}</span>
                        <button wire:click="deleteNote('{{ $note->uuid }}')" wire:confirm="Notiz löschen?"
                                class="ml-auto opacity-0 group-hover:opacity-100 text-red-500 hover:bg-red-50 p-0.5 rounded transition">
                            @svg('heroicon-o-x-mark', 'w-3 h-3')
                        </button>
                    </div>
                    <p class="text-xs text-[var(--ui-secondary)] whitespace-pre-wrap">{{ $note->text }}</p>
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
