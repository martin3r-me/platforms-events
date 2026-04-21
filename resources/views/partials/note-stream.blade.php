{{-- Notiz-Stream mit Avatar, Header + farbigem Speichern-Button (analog Alt-System). --}}
@php
    $currentUserId = auth()->id();
    $currentUserName = auth()->user()?->name ?? '?';

    // Farbvarianten je note-type (Save-Button)
    $colorMap = [
        'liefertext'    => 'bg-amber-500 hover:bg-amber-600',
        'absprache'     => 'bg-amber-500 hover:bg-amber-600',
        'vereinbarung'  => 'bg-blue-600 hover:bg-blue-700',
        'intern'        => 'bg-slate-700 hover:bg-slate-800',
        'schlussbericht'=> 'bg-purple-600 hover:bg-purple-700',
    ];
    $btnColor = $colorMap[$type] ?? 'bg-[var(--ui-primary)] hover:opacity-90';

    // Avatar-Helper
    $initials = function (?string $name) {
        if (!$name) return '?';
        $parts = preg_split('/\s+/', trim($name));
        return strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    };
    $avatarColor = function (?string $name) {
        $colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444'];
        $sum = 0;
        foreach (str_split((string) $name) as $ch) $sum = ($sum * 31 + ord($ch)) & 0xffff;
        return $colors[$sum % count($colors)];
    };

    $emptyLabel = match ($type) {
        'liefertext'    => 'Noch kein Liefertext',
        'absprache'     => 'Noch keine Absprachen',
        'vereinbarung'  => 'Noch keine Vereinbarungen',
        'schlussbericht'=> 'Noch kein Kommentar',
        'intern'        => 'Noch keine interne Notiz',
        default         => 'Noch keine Einträge',
    };

    $placeholderLabel = match ($type) {
        'liefertext'    => 'Liefertext hinzufügen …',
        'absprache'     => 'Absprache hinzufügen …',
        'vereinbarung'  => 'Vereinbarung hinzufügen …',
        'schlussbericht'=> 'Kommentar / Empfehlung …',
        'intern'        => 'Interne Notiz …',
        default         => 'Notiz hinzufügen …',
    };
@endphp

<div class="p-3 space-y-2.5" x-data="{ newText: '' }">
    {{-- Eintragsliste oder Empty-State --}}
    @if($notes->isEmpty())
        <div class="flex flex-col items-center justify-center py-5 border border-dashed border-[var(--ui-border)]/60 rounded-lg bg-white">
            @svg('heroicon-o-document-text', 'w-5 h-5 text-slate-300 mb-1')
            <p class="text-[0.62rem] text-slate-400 m-0">{{ $emptyLabel }}</p>
        </div>
    @else
        <div class="space-y-1 max-h-60 overflow-y-auto">
            @foreach($notes as $note)
                @php
                    $canEdit  = $note->user_id === $currentUserId;
                    $noteUser = $note->user_name ?? $note->user?->name ?? '?';
                @endphp
                <div class="group flex gap-2 p-2 rounded-md bg-white border border-[var(--ui-border)]/40 hover:border-[var(--ui-border)] transition"
                     x-data="{ editing: false, text: @js($note->text) }">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm"
                         style="background: {{ $avatarColor($noteUser) }}">
                        <span class="text-[0.58rem] font-bold text-white">{{ $initials($noteUser) }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="text-[0.68rem] font-semibold text-[var(--ui-secondary)]">{{ $noteUser }}</span>
                            <span class="text-[0.55rem] text-[var(--ui-muted)] bg-slate-100 rounded-full px-1.5 py-0.5 font-mono">
                                {{ $note->created_at->format('d.m.Y · H:i') }}h
                            </span>
                            @if($canEdit)
                                <div class="ml-auto flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                                    <button type="button" x-show="!editing" @click="editing = true; text = @js($note->text)"
                                            class="p-0.5 text-slate-400 hover:text-[var(--ui-primary)] hover:bg-slate-50 rounded" title="Bearbeiten">
                                        @svg('heroicon-o-pencil', 'w-3 h-3')
                                    </button>
                                    <button type="button" x-show="editing"
                                            @click="$wire.updateInlineNote(@js($note->uuid), text); editing = false"
                                            class="p-0.5 text-green-600 hover:bg-green-50 rounded" title="Speichern">
                                        @svg('heroicon-o-check', 'w-3 h-3')
                                    </button>
                                    <button type="button" x-show="editing" @click="editing = false; text = @js($note->text)"
                                            class="p-0.5 text-slate-400 hover:bg-slate-50 rounded" title="Abbrechen">
                                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                                    </button>
                                    <button type="button" x-show="!editing"
                                            wire:click="deleteNote('{{ $note->uuid }}')" wire:confirm="Notiz löschen?"
                                            class="p-0.5 text-red-500 hover:bg-red-50 rounded" title="Löschen">
                                        @svg('heroicon-o-trash', 'w-3 h-3')
                                    </button>
                                </div>
                            @endif
                        </div>
                        <p x-show="!editing" class="text-[0.7rem] text-slate-700 whitespace-pre-wrap leading-snug m-0">{{ $note->text }}</p>
                        @if($canEdit)
                            <textarea x-show="editing" x-cloak x-model="text" rows="3"
                                      @keydown.ctrl.enter.prevent="$wire.updateInlineNote(@js($note->uuid), text); editing = false"
                                      class="w-full border border-[var(--ui-primary)]/40 rounded-md px-2 py-1.5 text-[0.7rem] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Add-Form: Avatar links, Textarea, farbiger Speichern-Button --}}
    <div class="flex items-start gap-2 bg-white border border-[var(--ui-border)]/60 rounded-lg p-2">
        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm mt-0.5"
             style="background: {{ $avatarColor($currentUserName) }}">
            <span class="text-[0.58rem] font-bold text-white">{{ $initials($currentUserName) }}</span>
        </div>
        <div class="flex-1 min-w-0">
            <textarea x-model="newText" rows="2" placeholder="{{ $placeholderLabel }}"
                      @keydown.ctrl.enter.prevent="if (newText.trim()) { $wire.addInlineNote('{{ $type }}', newText); newText = ''; }"
                      class="w-full border-0 bg-transparent resize-none text-[0.7rem] focus:outline-none placeholder:text-slate-400 leading-snug"></textarea>
            <p class="text-[0.52rem] text-slate-400 m-0 mt-1">Strg+Enter zum Speichern</p>
        </div>
        <button type="button"
                @click="if (newText.trim()) { $wire.addInlineNote('{{ $type }}', newText); newText = ''; }"
                :disabled="!newText.trim()"
                :class="newText.trim() ? '{{ $btnColor }} text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                class="px-3 py-1.5 rounded-md text-[0.65rem] font-semibold transition flex-shrink-0">
            Speichern
        </button>
    </div>
</div>
