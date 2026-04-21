<div class="space-y-4 max-w-[960px]">
    @php
        $fmt = fn($v) => number_format((float)$v, 2, ',', '.');
        $quoteStatusMeta = [
            'draft'    => ['bg' => '#f1f5f9', 'color' => '#64748b', 'label' => 'Entwurf'],
            'sent'     => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'label' => 'Versendet'],
            'accepted' => ['bg' => '#dcfce7', 'color' => '#15803d', 'label' => 'Angenommen'],
            'rejected' => ['bg' => '#fee2e2', 'color' => '#b91c1c', 'label' => 'Abgelehnt'],
        ];

        $totalVorgaenge = 0;
        $totalArtikel = 0;
        $totalUmsatz = 0.0;
        foreach ($days as $day) {
            $dayItems = $items->get($day->id, collect());
            $totalVorgaenge += $dayItems->count();
            $totalArtikel += $dayItems->sum('artikel');
            $totalUmsatz += (float) $dayItems->sum('umsatz');
        }

        // Modus: ist ein Vorgang aktiv → zeige nur den Positions-Editor als "Seite"
        $mode = $activeItem ? 'editor' : 'overview';
        $activeDay = $activeItem ? $days->firstWhere('id', $activeItem->event_day_id) : null;
    @endphp

    @if($mode === 'editor' && $activeItem)
        {{-- ========== Modus: Einzel-Vorgang (Positions-Editor als Seite) ========== --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-1 h-6 rounded-sm flex-shrink-0" style="background: {{ $activeDay?->color ?? '#2563eb' }};"></div>
                <div class="min-w-0">
                    <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 truncate">Angebot · {{ $activeItem->typ }}</p>
                    <p class="text-[0.65rem] text-[var(--ui-muted)] m-0">
                        @if($activeDay)
                            {{ $activeDay->label ?? $activeDay->day_of_week }} · {{ $activeDay->datum?->format('d.m.Y') }}
                        @endif
                        · <span class="font-mono">{{ $activeItem->positionen }} Pos · {{ $fmt($activeItem->umsatz) }} €</span>
                    </p>
                </div>
            </div>
            <button wire:click="closePositions"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.68rem] font-semibold">
                @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                Zurück zur Übersicht
            </button>
        </div>

        @include('events::partials.quote-positions-editor')
    @else
        {{-- ========== Modus: Übersicht (alle Tage + KPIs + Angebots-Versand) ========== --}}

        {{-- Header --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-[1rem] font-bold text-[var(--ui-secondary)]">Angebote · Übersicht</p>
                <p class="text-[0.65rem] text-[var(--ui-muted)]">Alle Vorgänge je Tag mit Artikel- und Umsatzzusammenfassung</p>
            </div>
        </div>

    {{-- Gesamt-KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-4">
        <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
            <p class="text-[1.3rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $days->count() }}</p>
            <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Veranstaltungstage</p>
        </div>
        <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
            <p class="text-[1.3rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $totalVorgaenge }}</p>
            <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Vorgänge gesamt</p>
        </div>
        <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
            <p class="text-[1.3rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $totalArtikel }}</p>
            <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Artikel gesamt</p>
        </div>
        <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
            <p class="text-[0.95rem] font-bold text-green-700 m-0 leading-none font-mono">{{ $fmt($totalUmsatz) }} €</p>
            <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Umsatz gesamt</p>
        </div>
    </div>

    {{-- Tag-Karten --}}
    @if($days->isEmpty())
        <div class="bg-white border border-[var(--ui-border)] rounded-xl p-10 text-center">
            @svg('heroicon-o-calendar-days', 'w-10 h-10 text-slate-300 mx-auto mb-3')
            <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">Noch keine Event-Tage angelegt. Lege zuerst Tage im Basis-Tab an, bevor du Angebots-Vorgänge erstellst.</p>
        </div>
    @else
        <div class="flex flex-col gap-2.5 mb-5">
            @foreach($days as $day)
                @php $dayItems = $items->get($day->id, collect()); @endphp
                <div class="bg-white border border-[var(--ui-border)] rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between px-3.5 py-2.5 border-b border-slate-100 bg-slate-50">
                        <div class="flex items-center gap-2.5">
                            <div class="w-1 h-5 rounded-sm flex-shrink-0" style="background: {{ $day->color ?? '#2563eb' }};"></div>
                            <span class="text-[0.8rem] font-bold text-[var(--ui-secondary)]">{{ $day->label ?? $day->day_of_week }}</span>
                            <span class="text-[0.62rem] text-[var(--ui-muted)]">
                                {{ $day->datum?->format('d.m.Y') }}
                                · {{ $dayItems->count() }} {{ $dayItems->count() === 1 ? 'Vorgang' : 'Vorgänge' }}
                                · {{ $dayItems->sum('artikel') }} Artikel
                            </span>
                        </div>
                        <button wire:click="openItemCreate({{ $day->id }})"
                                class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white border-0 cursor-pointer text-[0.62rem] font-semibold">
                            @svg('heroicon-o-plus', 'w-2.5 h-2.5')
                            Neuer Vorgang
                        </button>
                    </div>

                    @if($dayItems->isEmpty())
                        <div class="px-3.5 py-4 text-center text-[0.65rem] text-[var(--ui-muted)]">
                            Noch keine Vorgänge — über „+ Neuer Vorgang" anlegen.
                        </div>
                    @else
                        <div class="flex flex-wrap {{ $activeItem && $activeItem->event_day_id === $day->id ? 'border-b border-slate-100' : '' }}">
                            @foreach($dayItems as $i => $v)
                                @php
                                    $umsatz = (float) $v->umsatz;
                                @endphp
                                <div class="p-3.5 flex-1 min-w-[220px] flex flex-col gap-2.5 {{ $i > 0 ? 'border-l border-slate-100' : '' }}">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-[26px] h-[26px] rounded-md flex items-center justify-center flex-shrink-0"
                                                 style="background: {{ ($day->color ?? '#2563eb').'20' }};">
                                                @svg(strtolower($v->typ) === 'speisen' ? 'heroicon-o-shopping-cart' : 'heroicon-o-squares-2x2', 'w-3.5 h-3.5', ['style' => 'color: '.($day->color ?? '#2563eb').';'])
                                            </div>
                                            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">{{ $v->typ }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            <span class="text-[0.58rem] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 whitespace-nowrap">{{ $v->status }}</span>
                                            <button wire:click="deleteItem({{ $v->id }})" wire:confirm="Vorgang «{{ $v->typ }}» mit allen Positionen wirklich löschen?"
                                                    class="w-6 h-6 border border-red-200 rounded bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center flex-shrink-0">
                                                @svg('heroicon-o-trash', 'w-2.5 h-2.5')
                                            </button>
                                        </div>
                                    </div>

                                    @if($v->positionen > 0)
                                        <div class="grid grid-cols-3 gap-1.5">
                                            <div class="bg-slate-50 rounded px-2 py-1.5 text-center">
                                                <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $v->artikel }}</p>
                                                <p class="text-[0.55rem] text-[var(--ui-muted)] mt-1 font-medium">Artikel</p>
                                            </div>
                                            <div class="bg-slate-50 rounded px-2 py-1.5 text-center">
                                                <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $v->positionen }}</p>
                                                <p class="text-[0.55rem] text-[var(--ui-muted)] mt-1 font-medium">Positionen</p>
                                            </div>
                                            <div class="bg-green-50 rounded px-2 py-1.5 text-center">
                                                <p class="text-[0.85rem] font-bold text-green-700 m-0 leading-none font-mono">{{ $fmt($umsatz) }}</p>
                                                <p class="text-[0.55rem] text-[var(--ui-muted)] mt-1 font-medium">Umsatz €</p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="px-2.5 py-1.5 bg-slate-50 rounded-md border border-dashed border-slate-200 text-center">
                                            <span class="text-[0.62rem] text-slate-400">Noch keine Artikel</span>
                                        </div>
                                    @endif

                                    <div class="flex items-center justify-between">
                                        @if($v->positionen > 0)
                                            <span class="text-[0.6rem] text-[var(--ui-muted)]">MwSt: {{ $v->mwst }}</span>
                                        @else
                                            <span></span>
                                        @endif
                                        <button wire:click="openPositions({{ $v->id }})"
                                                class="text-[0.62rem] font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 bg-transparent border-0 cursor-pointer">
                                            Öffnen
                                            @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif

    {{-- Angebote versenden --}}
    <div class="mt-7 pt-6 border-t border-slate-200">
        <div class="flex items-center justify-between mb-3.5">
            <div>
                <p class="text-[0.85rem] font-bold text-[var(--ui-secondary)] mb-0.5">Angebote versenden</p>
                <p class="text-[0.65rem] text-[var(--ui-muted)]">PDF erstellen · Kundenlink generieren · Antwort verfolgen</p>
            </div>
            <div class="flex items-center gap-1.5">
                <button wire:click="createQuote"
                        class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-md bg-blue-600 hover:bg-blue-700 text-white border-0 cursor-pointer text-[0.65rem] font-semibold">
                    @svg('heroicon-o-plus', 'w-3 h-3')
                    Neues Angebot
                </button>
                @if($activeQuote)
                    <button wire:click="newVersion" wire:confirm="Neue Version anlegen?"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold">
                        @svg('heroicon-o-document-duplicate', 'w-3 h-3')
                        Neue Version
                    </button>
                @endif
            </div>
        </div>

        @if($quotes->isEmpty())
            <div class="bg-white border border-[var(--ui-border)] rounded-xl p-8 text-center">
                <p class="text-[0.7rem] text-[var(--ui-muted)] m-0">Noch kein Angebot als Dokument versendet.</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($quotes as $q)
                    @php $qs = $quoteStatusMeta[$q->status] ?? $quoteStatusMeta['draft']; @endphp
                    <div class="bg-white border rounded-xl px-4 py-3 {{ $activeQuote && $activeQuote->id === $q->id ? 'border-blue-300 ring-1 ring-blue-100' : 'border-[var(--ui-border)]' }}">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="text-[0.56rem] font-bold px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-200 flex-shrink-0">v{{ $q->version }}</span>
                            <span class="text-[0.58rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap"
                                  style="background: {{ $qs['bg'] }}; color: {{ $qs['color'] }};">{{ $qs['label'] }}</span>
                            @if(!$q->is_current)
                                <span class="text-[0.54rem] font-semibold text-[var(--ui-muted)] italic">Alte Version</span>
                            @endif

                            <div class="flex-1 min-w-0">
                                <button wire:click="selectQuote({{ $q->id }})"
                                        class="text-[0.72rem] font-semibold text-[var(--ui-secondary)] bg-transparent border-0 cursor-pointer p-0">
                                    Angebot · {{ $q->created_at->format('d.m.Y') }}
                                    @if($q->valid_until)
                                        <span class="text-[0.6rem] text-[var(--ui-muted)] ml-1.5">gültig bis {{ $q->valid_until->format('d.m.Y') }}</span>
                                    @endif
                                </button>
                                @if($q->sent_at)
                                    <p class="text-[0.58rem] text-[var(--ui-muted)] mt-0.5">Versendet {{ $q->sent_at->format('d.m.Y H:i') }}</p>
                                @endif
                            </div>

                            <div class="flex gap-1.5 flex-wrap">
                                <a href="{{ route('events.quote.pdf', ['event' => $event->slug, 'quoteId' => $q->id]) }}" target="_blank"
                                   class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold no-underline">
                                    PDF
                                </a>
                                <button type="button"
                                        x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText('{{ route('events.public.quote', ['token' => $q->token]) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        :class="copied ? 'bg-green-50 border-green-200 text-green-600' : 'bg-white border-slate-200 text-slate-600'"
                                        class="px-2.5 py-1 border rounded-md hover:bg-slate-50 text-[0.62rem] font-semibold cursor-pointer">
                                    <span x-text="copied ? 'Kopiert' : 'Link'"></span>
                                </button>
                                @if($q->status === 'draft')
                                    <button wire:click="selectQuote({{ $q->id }}); $wire.setQuoteStatus('sent')"
                                            class="px-2.5 py-1 border-0 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-[0.62rem] font-bold cursor-pointer">
                                        Versenden
                                    </button>
                                @endif
                                <button wire:click="deleteQuote({{ $q->id }})" wire:confirm="Angebot-Version löschen?"
                                        class="px-2.5 py-1 border border-red-200 rounded-md bg-red-50 hover:bg-red-100 text-red-500 text-[0.62rem] font-semibold cursor-pointer">
                                    Löschen
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- Modal: Item anlegen --}}
    <x-ui-modal wire:model="showItemModal" size="md" :hideFooter="true">
        <x-slot name="header">Neuer Angebots-Vorgang</x-slot>
        <form wire:submit.prevent="saveItem" class="space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                    <input wire:model="itemTyp" type="text" placeholder="Speisen / Getränke / Personal / …"
                           class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Status</label>
                    <input wire:model="itemStatus" type="text"
                           class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs">
                </div>
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">MwSt</label>
                    <select wire:model="itemMwst" class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs">
                        <option value="7%">7%</option>
                        <option value="19%">19%</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeItemModal">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Anlegen</x-ui-button>
            </div>
        </form>
    </x-ui-modal>

</div>
