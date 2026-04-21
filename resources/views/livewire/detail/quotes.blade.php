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
    @endphp

    @if($view === 'editor' && $activeItem)
        {{-- ================= Modus: Einzel-Vorgang (Positions-Editor als Seite) ================= --}}
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
                Zurück
            </button>
        </div>

        @include('events::partials.quote-positions-editor')

    @elseif($view === 'day' && $activeDay)
        {{-- ================= Modus: Tages-Übersicht (eine einzelne Tag-Karte) ================= --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-1 h-6 rounded-sm flex-shrink-0" style="background: {{ $activeDay->color ?? '#2563eb' }};"></div>
                <div class="min-w-0">
                    <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0 truncate">Angebot · {{ $activeDay->label ?? $activeDay->day_of_week }}</p>
                    <p class="text-[0.65rem] text-[var(--ui-muted)] m-0">
                        {{ $activeDay->datum?->format('d.m.Y') }} · {{ $activeDay->day_of_week }}
                        @php $dayItems = $items->get($activeDay->id, collect()); @endphp
                        · <span class="font-mono">{{ $dayItems->count() }} Vorgänge · {{ $fmt($dayItems->sum('umsatz')) }} €</span>
                    </p>
                </div>
            </div>
            <button wire:click="backToOverview"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.68rem] font-semibold">
                @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                Übersicht
            </button>
        </div>

        @php $dayItems = $items->get($activeDay->id, collect()); @endphp
        @include('events::partials.quote-day-card', ['day' => $activeDay, 'dayItems' => $dayItems, 'fmt' => $fmt])

    @elseif($view === 'articles')
        {{-- ================= Modus: Alle Artikel (flache Liste über alle Tage) ================= --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <p class="text-[1rem] font-bold text-[var(--ui-secondary)] m-0">Alle Artikel</p>
                <p class="text-[0.65rem] text-[var(--ui-muted)] m-0">{{ $allPositions->count() }} Positionen über alle Tage</p>
            </div>
            <button wire:click="backToOverview"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-[0.68rem] font-semibold">
                @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                Übersicht
            </button>
        </div>

        <div class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden">
            @if($allPositions->isEmpty())
                <div class="p-10 text-center">
                    @svg('heroicon-o-squares-2x2', 'w-10 h-10 text-slate-300 mx-auto mb-3')
                    <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">Noch keine Artikel angelegt.</p>
                </div>
            @else
                <table class="w-full border-collapse text-[0.65rem]">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left py-1.5 px-2.5 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Tag</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Vorgang</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gruppe</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Name</th>
                            <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Anz</th>
                            <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gebinde</th>
                            <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Preis</th>
                            <th class="text-center py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">MwSt</th>
                            <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $allItemsById = $items->flatten()->keyBy('id');
                            $daysById     = $days->keyBy('id');
                        @endphp
                        @foreach($allPositions as $p)
                            @php
                                $qi = $allItemsById->get($p->quote_item_id);
                                $d  = $qi ? $daysById->get($qi->event_day_id) : null;
                            @endphp
                            <tr class="border-b border-slate-100 hover:bg-slate-50/60">
                                <td class="py-1.5 px-2.5">
                                    @if($d)
                                        <button wire:click="openDay({{ $d->id }})"
                                                class="text-[0.62rem] font-semibold text-[var(--ui-secondary)] hover:text-blue-600 bg-transparent border-0 cursor-pointer p-0 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $d->color ?? '#2563eb' }};"></span>
                                            {{ $d->datum?->format('d.m.') }}
                                        </button>
                                    @else —
                                    @endif
                                </td>
                                <td class="py-1.5 px-2">
                                    @if($qi)
                                        <button wire:click="openPositions({{ $qi->id }})"
                                                class="text-[0.62rem] font-semibold text-blue-600 hover:text-blue-700 bg-transparent border-0 cursor-pointer p-0">
                                            {{ $qi->typ }}
                                        </button>
                                    @else — @endif
                                </td>
                                <td class="py-1.5 px-2 text-slate-600">{{ $p->gruppe }}</td>
                                <td class="py-1.5 px-2 text-[var(--ui-secondary)]">{{ $p->name }}</td>
                                <td class="py-1.5 px-2 text-right font-mono">{{ $p->anz }}</td>
                                <td class="py-1.5 px-2 text-slate-500">{{ $p->gebinde }}</td>
                                <td class="py-1.5 px-2 text-right font-mono">{{ $fmt($p->preis) }}</td>
                                <td class="py-1.5 px-2 text-center text-slate-500">{{ $p->mwst }}</td>
                                <td class="py-1.5 px-2 text-right font-mono font-semibold">{{ $fmt($p->gesamt) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    @else
        {{-- ================= Modus: Gesamt-Übersicht (alle Tage + KPIs + Angebots-Versand) ================= --}}

        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-[1rem] font-bold text-[var(--ui-secondary)]">Angebote · Übersicht</p>
                <p class="text-[0.65rem] text-[var(--ui-muted)]">Alle Vorgänge je Tag mit Artikel- und Umsatzzusammenfassung</p>
            </div>
        </div>

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

        @if($days->isEmpty())
            <div class="bg-white border border-[var(--ui-border)] rounded-xl p-10 text-center">
                @svg('heroicon-o-calendar-days', 'w-10 h-10 text-slate-300 mx-auto mb-3')
                <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">Noch keine Event-Tage angelegt. Lege zuerst Tage im Basis-Tab an, bevor du Angebots-Vorgänge erstellst.</p>
            </div>
        @else
            <div class="flex flex-col gap-2.5 mb-5">
                @foreach($days as $day)
                    @php $dayItems = $items->get($day->id, collect()); @endphp
                    @include('events::partials.quote-day-card', ['day' => $day, 'dayItems' => $dayItems, 'fmt' => $fmt])
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
                        <option value="0%">0%</option>
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
