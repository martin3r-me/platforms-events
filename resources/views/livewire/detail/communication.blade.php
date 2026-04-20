<div class="space-y-4">
    @php
        $typeMap = [
            'quote'    => ['label' => 'Angebot',  'color' => 'text-blue-600'],
            'invoice'  => ['label' => 'Rechnung', 'color' => 'text-green-600'],
            'contract' => ['label' => 'Vertrag',  'color' => 'text-purple-600'],
            'reminder' => ['label' => 'Mahnung',  'color' => 'text-orange-600'],
            'custom'   => ['label' => 'Frei',     'color' => 'text-slate-600'],
        ];
        $statusMap = [
            'sent'   => ['label' => 'Versandt', 'bg' => 'bg-blue-50',   'text' => 'text-blue-700'],
            'opened' => ['label' => 'Geöffnet', 'bg' => 'bg-green-100', 'text' => 'text-green-700'],
            'failed' => ['label' => 'Fehler',   'bg' => 'bg-red-50',    'text' => 'text-red-700'],
        ];
    @endphp

    <x-ui-panel>
        <div class="p-4 flex justify-between items-center border-b border-[var(--ui-border)]">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)]">E-Mail-Historie</h3>
                <p class="text-[0.62rem] text-[var(--ui-muted)]">Ausgehende E-Mails zu diesem Event</p>
            </div>
            <x-ui-button variant="secondary-outline" size="sm" disabled
                         title="Mail-Versand wird in einer späteren Etappe angebunden.">
                @svg('heroicon-o-envelope', 'w-3.5 h-3.5 inline') Neu senden (folgt)
            </x-ui-button>
        </div>

        @if($emails->isEmpty())
            <div class="p-12 text-center">
                @svg('heroicon-o-envelope', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine E-Mails</p>
                <p class="text-xs text-[var(--ui-muted)]">Bisher wurden zu diesem Event keine E-Mails versandt.</p>
            </div>
        @else
            <div class="divide-y divide-[var(--ui-border)]/40">
                @foreach($emails as $e)
                    @php
                        $tm = $typeMap[$e->type] ?? ['label' => $e->type, 'color' => 'text-slate-600'];
                        $sm = $statusMap[$e->status] ?? $statusMap['sent'];
                    @endphp
                    <div class="p-4 flex items-start gap-3">
                        <span class="{{ $tm['color'] }} mt-0.5">@svg('heroicon-o-envelope', 'w-4 h-4')</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 text-[0.62rem] text-[var(--ui-muted)] mb-1">
                                <span class="font-bold uppercase">{{ $tm['label'] }}</span>
                                <span class="font-bold px-1.5 py-0.5 rounded-full {{ $sm['bg'] }} {{ $sm['text'] }}">{{ $sm['label'] }}</span>
                                <span>·</span>
                                <span class="font-mono">{{ $e->created_at->format('d.m.Y H:i') }}</span>
                                @if($e->opened_at)
                                    <span>·</span>
                                    <span class="text-green-600">geöffnet {{ $e->opened_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            <p class="text-xs font-semibold text-[var(--ui-secondary)] truncate">{{ $e->subject }}</p>
                            <p class="text-[0.7rem] text-[var(--ui-muted)] truncate">An: {{ $e->to }}@if($e->cc) · CC: {{ $e->cc }} @endif</p>
                            @if($e->attachment_name)
                                <p class="text-[0.62rem] text-[var(--ui-muted)] mt-1 font-mono">📎 {{ $e->attachment_name }}</p>
                            @endif
                        </div>
                        <button wire:click="deleteEmail({{ $e->id }})"
                                wire:confirm="Eintrag löschen?"
                                class="flex-shrink-0 text-[var(--ui-muted)] hover:text-red-600 p-1">
                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui-panel>

    <x-ui-panel>
        <div class="p-4 border-b border-[var(--ui-border)]">
            <h3 class="text-sm font-bold text-[var(--ui-secondary)]">Benachrichtigungen</h3>
            <p class="text-[0.62rem] text-[var(--ui-muted)]">Interne Notifications zu diesem Event (eigene)</p>
        </div>

        @if($notifications->isEmpty())
            <div class="p-8 text-center text-xs text-[var(--ui-muted)]">
                Keine Benachrichtigungen zu diesem Event.
            </div>
        @else
            <div class="divide-y divide-[var(--ui-border)]/40">
                @foreach($notifications as $n)
                    <div class="p-3 flex items-start gap-3 {{ $n->read_at ? 'opacity-60' : '' }}">
                        <span class="mt-0.5 text-[var(--ui-muted)]">@svg('heroicon-o-bell', 'w-4 h-4')</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[var(--ui-secondary)]">{{ $n->title }}</p>
                            @if($n->body)
                                <p class="text-[0.7rem] text-[var(--ui-muted)]">{{ $n->body }}</p>
                            @endif
                            <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-mono">{{ $n->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui-panel>
</div>
