<div class="space-y-4 max-w-[900px]">
    @php
        $typeMap = [
            'quote'        => ['label' => 'Angebot',           'color' => 'text-blue-700',   'bg' => 'bg-blue-50'],
            'invoice'      => ['label' => 'Rechnung',          'color' => 'text-green-700',  'bg' => 'bg-green-50'],
            'contract'     => ['label' => 'Vertrag',           'color' => 'text-purple-700', 'bg' => 'bg-purple-50'],
            'confirmation' => ['label' => 'Bestätigung',       'color' => 'text-purple-700', 'bg' => 'bg-purple-50'],
            'reminder'     => ['label' => 'Mahnung',           'color' => 'text-red-700',    'bg' => 'bg-red-50'],
            'inquiry'      => ['label' => 'Rückfrage',         'color' => 'text-amber-700',  'bg' => 'bg-amber-50'],
            'custom'       => ['label' => 'Sonstiges',         'color' => 'text-slate-600',  'bg' => 'bg-slate-100'],
        ];
        $statusMap = [
            'sent'   => ['label' => '✓ Gesendet',   'color' => 'text-slate-500'],
            'opened' => ['label' => '✓✓ Gelesen',   'color' => 'text-green-600'],
            'failed' => ['label' => '✕ Fehler',     'color' => 'text-red-600'],
        ];
    @endphp

    {{-- Header --}}
    <div class="mb-4">
        <p class="text-[0.95rem] font-bold text-[var(--ui-secondary)]">Kommunikation</p>
        <p class="text-[0.65rem] text-[var(--ui-muted)]">{{ $event->customer ?: $event->name }}</p>
    </div>

    {{-- Verlauf --}}
    <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden mb-4">
        <div class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
            <span class="text-[0.65rem] font-bold text-[var(--ui-secondary)]">Verlauf</span>
            <span class="text-[0.6rem] text-[var(--ui-muted)]">{{ $emails->count() }} Einträge</span>
        </div>

        @forelse($emails as $e)
            @php
                $t = $typeMap[$e->type] ?? $typeMap['custom'];
                $s = $statusMap[$e->status] ?? $statusMap['sent'];
                $isSelected = $selectedId === $e->id;
            @endphp
            <div>
                <div wire:click="select({{ $e->id }})"
                     class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-3 px-4 py-2.5 border-b border-slate-100 cursor-pointer transition {{ $isSelected ? 'bg-blue-50' : 'hover:bg-slate-50' }}">
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <span class="text-[0.7rem] font-bold w-4 text-center text-blue-600">↗</span>
                        <span class="text-[0.58rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap {{ $t['bg'] }} {{ $t['color'] }}">{{ $t['label'] }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[0.72rem] font-semibold text-[var(--ui-secondary)] mb-0.5 truncate">{{ $e->subject ?: '(ohne Betreff)' }}</p>
                        <p class="text-[0.62rem] text-[var(--ui-muted)] truncate">{{ $e->to }}</p>
                    </div>
                    @if($e->attachment_name)
                        <span class="text-[var(--ui-muted)] flex-shrink-0">
                            @svg('heroicon-o-paper-clip', 'w-3.5 h-3.5')
                        </span>
                    @else
                        <span></span>
                    @endif
                    <div class="text-right flex-shrink-0">
                        <p class="text-[0.6rem] text-[var(--ui-muted)] font-mono mb-0.5">{{ $e->created_at->format('d.m.Y · H:i') }}</p>
                        <p class="text-[0.58rem] font-mono {{ $s['color'] }}">{{ $s['label'] }}</p>
                    </div>
                </div>

                @if($isSelected)
                    <div class="border-b border-[var(--ui-border)] bg-slate-50 px-5 py-4">
                        @if($e->body)
                            <p class="text-[0.7rem] text-slate-700 leading-relaxed mb-3 whitespace-pre-wrap">{{ $e->body }}</p>
                        @else
                            <p class="text-[0.7rem] text-[var(--ui-muted)] italic mb-3">Kein Inhalt hinterlegt.</p>
                        @endif

                        @if($e->attachment_name)
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-md px-2.5 py-1">
                                    @svg('heroicon-o-paper-clip', 'w-3 h-3 text-slate-500')
                                    <span class="text-[0.62rem] text-slate-700 font-mono">{{ $e->attachment_name }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-1.5">
                            <button type="button" disabled
                                    class="flex items-center gap-1.5 px-3 py-1 rounded-md bg-blue-50 text-blue-600 border border-blue-200 text-[0.65rem] font-semibold opacity-60 cursor-not-allowed">
                                @svg('heroicon-o-arrow-uturn-left', 'w-3 h-3')
                                Antworten
                            </button>
                            <button type="button" disabled
                                    class="flex items-center gap-1.5 px-3 py-1 rounded-md bg-white text-slate-500 border border-slate-200 text-[0.65rem] opacity-60 cursor-not-allowed">
                                @svg('heroicon-o-share', 'w-3 h-3')
                                Weiterleiten
                            </button>
                            <button wire:click="deleteEmail({{ $e->id }})" wire:confirm="E-Mail wirklich löschen?"
                                    class="ml-auto flex items-center gap-1.5 px-3 py-1 rounded-md bg-white text-red-500 border border-red-200 hover:bg-red-50 text-[0.65rem] font-semibold">
                                @svg('heroicon-o-trash', 'w-3 h-3')
                                Löschen
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="px-4 py-8 text-center text-[0.72rem] text-[var(--ui-muted)]">
                Noch keine Nachrichten.
            </div>
        @endforelse
    </div>

    {{-- Neue Nachricht verfassen --}}
    <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden">
        <div wire:click="toggleCompose"
             class="bg-gradient-to-br from-blue-800 to-blue-600 px-4 py-3 flex items-center justify-between cursor-pointer">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-plus', 'w-3.5 h-3.5 text-white')
                <span class="text-[0.72rem] font-bold text-white">Neue Nachricht verfassen</span>
            </div>
            <svg class="w-3 h-3 text-white transition-transform {{ $compose ? 'rotate-180' : '' }}"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        @if($compose)
            <div class="p-4 flex flex-col gap-2.5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
                    <div>
                        <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">An</p>
                        <input wire:model="newTo" type="text" placeholder="empfaenger@firma.de"
                               class="w-full border border-slate-200 rounded-md px-2 py-1 text-[0.68rem]">
                    </div>
                    <div>
                        <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Betreff</p>
                        <input wire:model="newSubject" type="text" placeholder="Betreff eingeben …"
                               class="w-full border border-slate-200 rounded-md px-2 py-1 text-[0.68rem]">
                    </div>
                    <div>
                        <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Typ</p>
                        <select wire:model="newType"
                                class="w-full border border-slate-200 rounded-md px-2 py-1 text-[0.68rem] cursor-pointer">
                            <option value="quote">Angebot</option>
                            <option value="confirmation">Auftragsbestätigung</option>
                            <option value="invoice">Rechnung</option>
                            <option value="reminder">Mahnung</option>
                            <option value="inquiry">Rückfrage</option>
                            <option value="custom">Sonstiges</option>
                        </select>
                    </div>
                </div>
                <div>
                    <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Nachricht</p>
                    <textarea wire:model="newBody" rows="5" placeholder="Nachricht eingeben …"
                              class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-[0.68rem] leading-relaxed resize-y"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" wire:click="resetCompose"
                            class="px-3.5 py-1.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-500 border border-slate-200 text-[0.7rem]">
                        Verwerfen
                    </button>
                    <button type="button" wire:click="send"
                            @disabled(trim($newTo) === '' || trim($newSubject) === '')
                            class="px-4 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white border-0 text-[0.7rem] font-semibold flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                        @svg('heroicon-o-paper-airplane', 'w-3 h-3')
                        Senden
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Benachrichtigungen (AppNotifications) --}}
    @if($notifications->isNotEmpty())
        <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-100">
                <h3 class="text-[0.65rem] font-bold text-[var(--ui-secondary)]">Benachrichtigungen</h3>
                <p class="text-[0.55rem] text-[var(--ui-muted)]">Interne Notifications zu diesem Event</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($notifications as $n)
                    <div class="px-4 py-2 flex items-start gap-3 {{ $n->read_at ? 'opacity-60' : '' }}">
                        <span class="mt-0.5 text-[var(--ui-muted)]">@svg('heroicon-o-bell', 'w-3.5 h-3.5')</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-[0.7rem] font-semibold text-[var(--ui-secondary)]">{{ $n->title }}</p>
                            @if($n->body)
                                <p class="text-[0.62rem] text-[var(--ui-muted)]">{{ $n->body }}</p>
                            @endif
                            <p class="text-[0.58rem] text-[var(--ui-muted)] mt-0.5 font-mono">{{ $n->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
