<div class="space-y-4 {{ ($view ?? 'overview') === 'editor' ? '' : 'max-w-[960px]' }}">
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
            @if($totalVorgaenge > 0)
                <button wire:click="convertAllQuoteItemsToOrder"
                        wire:confirm="Alle {{ $totalVorgaenge }} Vorgänge aller Tage in Bestellungen überführen?"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-700 text-[0.68rem] font-bold cursor-pointer">
                    @svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5')
                    Alle in Bestellung
                </button>
            @endif
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
                        @php
                            $qs = $quoteStatusMeta[$q->status] ?? $quoteStatusMeta['draft'];
                            $apStatus = $q->approval_status ?? 'none';
                            $apMeta = match ($apStatus) {
                                'pending'  => ['bg' => '#fef3c7', 'color' => '#b45309', 'label' => 'Freigabe: ausstehend'],
                                'approved' => ['bg' => '#dcfce7', 'color' => '#15803d', 'label' => 'Freigegeben'],
                                'rejected' => ['bg' => '#fee2e2', 'color' => '#b91c1c', 'label' => 'Abgelehnt'],
                                default    => null,
                            };
                            $isApprover = $apStatus === 'pending' && (int) $q->approver_id === (int) $currentUserId;
                            $canSend = $apStatus === 'approved' || $apStatus === 'none';
                        @endphp
                        <div class="bg-white border rounded-xl px-4 py-3 {{ $activeQuote && $activeQuote->id === $q->id ? 'border-blue-300 ring-1 ring-blue-100' : 'border-[var(--ui-border)]' }}">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="text-[0.56rem] font-bold px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-200 flex-shrink-0">v{{ $q->version }}</span>
                                <span class="text-[0.58rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap"
                                      style="background: {{ $qs['bg'] }}; color: {{ $qs['color'] }};">{{ $qs['label'] }}</span>
                                @if($apMeta)
                                    <span class="text-[0.58rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap"
                                          style="background: {{ $apMeta['bg'] }}; color: {{ $apMeta['color'] }};">{{ $apMeta['label'] }}</span>
                                @endif
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
                                    @if($apStatus === 'pending' && $q->approver)
                                        <p class="text-[0.58rem] text-amber-700 mt-0.5">Wartet auf Freigabe von <strong>{{ $q->approver->name }}</strong>@if($q->approval_requested_at) (angefragt {{ $q->approval_requested_at->format('d.m.Y H:i') }})@endif</p>
                                    @elseif($apStatus === 'approved' && $q->approver)
                                        <p class="text-[0.58rem] text-green-700 mt-0.5">Freigegeben von <strong>{{ $q->approver->name }}</strong>@if($q->approval_decided_at) am {{ $q->approval_decided_at->format('d.m.Y H:i') }}@endif</p>
                                    @elseif($apStatus === 'rejected' && $q->approver)
                                        <p class="text-[0.58rem] text-red-700 mt-0.5">Abgelehnt von <strong>{{ $q->approver->name }}</strong>@if($q->approval_decided_at) am {{ $q->approval_decided_at->format('d.m.Y H:i') }}@endif</p>
                                    @endif
                                    @if($q->approval_comment)
                                        <p class="text-[0.58rem] text-[var(--ui-muted)] italic mt-0.5">„{{ $q->approval_comment }}"</p>
                                    @endif
                                </div>

                                <div class="flex gap-1.5 flex-wrap">
                                    @if($isApprover)
                                        <button wire:click="approveQuote({{ $q->id }})"
                                                class="px-2.5 py-1 border-0 rounded-md bg-green-600 hover:bg-green-700 text-white text-[0.62rem] font-bold cursor-pointer">
                                            Freigeben
                                        </button>
                                        <button wire:click="rejectQuote({{ $q->id }})" wire:confirm="Angebot wirklich ablehnen?"
                                                class="px-2.5 py-1 border border-red-200 rounded-md bg-red-50 hover:bg-red-100 text-red-600 text-[0.62rem] font-bold cursor-pointer">
                                            Ablehnen
                                        </button>
                                    @endif
                                    @if($apStatus === 'none' || $apStatus === 'rejected')
                                        <button wire:click="openApprovalRequest({{ $q->id }})"
                                                class="px-2.5 py-1 border border-amber-300 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-700 text-[0.62rem] font-semibold cursor-pointer">
                                            Freigabe anfordern
                                        </button>
                                    @elseif($apStatus === 'pending' && (int) $q->approval_requested_by === (int) $currentUserId)
                                        <button wire:click="cancelApprovalRequest({{ $q->id }})" wire:confirm="Freigabe-Anfrage zurueckziehen?"
                                                class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold cursor-pointer">
                                            Anfrage zurück
                                        </button>
                                    @endif
                                    <a href="{{ route('events.quote.pdf', ['event' => $event->slug, 'quoteId' => $q->id]) }}" target="_blank"
                                       class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold no-underline">
                                        PDF
                                    </a>
                                    <button type="button"
                                            x-data="{ copied: false }"
                                            @click="navigator.clipboard.writeText('{{ route('events.public.quote', ['token' => $q->token]) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                            :class="copied ? 'bg-green-50 border-green-200 text-green-600' : 'bg-white border-slate-200 text-slate-600'"
                                            class="px-2.5 py-1 border rounded-md hover:bg-slate-50 text-[0.62rem] font-semibold cursor-pointer {{ $canSend ? '' : 'opacity-50 cursor-not-allowed' }}"
                                            @if(!$canSend) disabled @endif>
                                        <span x-text="copied ? 'Kopiert' : 'Link'"></span>
                                    </button>
                                    @if($q->status === 'draft')
                                        <button wire:click="selectQuote({{ $q->id }}); $wire.setQuoteStatus('sent')"
                                                @if(!$canSend) disabled title="Erst Freigabe einholen" @endif
                                                class="px-2.5 py-1 border-0 rounded-md text-white text-[0.62rem] font-bold cursor-pointer {{ $canSend ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-300 cursor-not-allowed' }}">
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

    {{-- Modal: Vorlage einfuegen (Package-Picker mit Vorschau) --}}
    <x-ui-modal wire:model="showPackagePicker" size="xl" :hideFooter="true">
        <x-slot name="header">
            <span class="flex items-center gap-2">
                @svg('heroicon-o-rectangle-group', 'w-4 h-4 text-purple-600')
                Vorlage einfügen
            </span>
        </x-slot>
        <div class="flex gap-4 w-full" style="min-height: 460px;">
            {{-- Links: Suche + Liste --}}
            <div class="w-[280px] flex-shrink-0 flex flex-col min-h-0">
                <div class="relative mb-2">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400">
                        @svg('heroicon-o-magnifying-glass', 'w-3.5 h-3.5')
                    </span>
                    <input wire:model.live.debounce.300ms="packageSearch" type="text"
                           placeholder="Vorlage suchen …"
                           class="w-full border border-slate-200 rounded-md pl-7 pr-2 py-1.5 text-[0.7rem] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>
                <div class="flex-1 overflow-y-auto border border-slate-100 rounded-md p-1">
                    @forelse(($articlePackages ?? collect()) as $pkg)
                        <button type="button"
                                wire:click="selectPackagePreview({{ $pkg->id }})"
                                class="flex items-center gap-2 w-full px-2.5 py-2 rounded text-left text-[0.7rem] font-medium transition cursor-pointer border-0
                                       {{ $selectedPackagePreviewId === $pkg->id ? 'bg-purple-50 border border-purple-200 text-purple-700' : 'bg-transparent hover:bg-slate-50 text-slate-700' }}">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: {{ $pkg->color ?: '#8b5cf6' }};"></span>
                            <div class="flex-1 min-w-0">
                                <div class="truncate font-semibold">{{ $pkg->name }}</div>
                                @if($pkg->description)
                                    <div class="text-[0.58rem] text-slate-400 truncate">{{ $pkg->description }}</div>
                                @endif
                            </div>
                            <span class="text-[0.58rem] text-slate-400 flex-shrink-0">{{ $pkg->items_count ?? 0 }} Pos.</span>
                        </button>
                    @empty
                        <div class="px-3 py-6 text-center text-[0.65rem] text-slate-400">
                            @if(trim($packageSearch) !== '')
                                Keine Treffer für „{{ $packageSearch }}"
                            @else
                                Noch keine Vorlagen angelegt.
                            @endif
                        </div>
                    @endforelse
                </div>
                <a href="{{ route('events.articles') }}"
                   class="mt-2 flex items-center gap-1.5 px-2 py-1 text-[0.6rem] text-slate-400 hover:text-slate-600 no-underline">
                    @svg('heroicon-o-cog-6-tooth', 'w-3 h-3')
                    Vorlagen verwalten
                </a>
            </div>

            {{-- Rechts: Vorschau --}}
            <div class="flex-1 min-w-0 flex flex-col border-l border-slate-100 pl-4">
                @if($selectedPackagePreview)
                    <div class="flex items-center justify-between mb-2 flex-shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $selectedPackagePreview->color ?: '#8b5cf6' }};"></span>
                            <h3 class="text-[0.85rem] font-bold text-[var(--ui-secondary)] m-0 truncate">{{ $selectedPackagePreview->name }}</h3>
                            <span class="text-[0.62rem] text-slate-400 flex-shrink-0">{{ $selectedPackagePreview->items->count() }} Positionen</span>
                        </div>
                    </div>
                    @if($selectedPackagePreview->description)
                        <p class="text-[0.62rem] text-slate-500 italic mb-2 flex-shrink-0">{{ $selectedPackagePreview->description }}</p>
                    @endif
                    <div class="flex-1 overflow-auto border border-slate-100 rounded-md min-h-0">
                        <table class="w-full text-[0.65rem]">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr class="border-b border-slate-200">
                                    <th class="text-left py-1.5 px-2 font-semibold text-slate-500">Gruppe</th>
                                    <th class="text-left py-1.5 px-2 font-semibold text-slate-500">Name</th>
                                    <th class="text-right py-1.5 px-2 font-semibold text-slate-500">Anz.</th>
                                    <th class="text-left py-1.5 px-2 font-semibold text-slate-500">Gebinde</th>
                                    <th class="text-right py-1.5 px-2 font-semibold text-slate-500">VK</th>
                                    <th class="text-center py-1.5 px-2 font-semibold text-slate-500">MwSt.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($selectedPackagePreview->items->sortBy('sort_order') as $pi)
                                    <tr class="border-b border-slate-100">
                                        <td class="py-1 px-2 text-slate-500">{{ $pi->gruppe }}</td>
                                        <td class="py-1 px-2 text-slate-700">{{ $pi->name }}</td>
                                        <td class="py-1 px-2 text-right font-mono">{{ $pi->quantity }}</td>
                                        <td class="py-1 px-2 text-slate-500">{{ $pi->gebinde }}</td>
                                        <td class="py-1 px-2 text-right font-mono font-semibold text-green-700">{{ $pi->vk ? number_format((float)$pi->vk, 2, ',', '.').' €' : '—' }}</td>
                                        <td class="py-1 px-2 text-center text-slate-500">{{ $pi->article?->mwst ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-4 text-center text-slate-400">Keine Positionen in dieser Vorlage</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end pt-3 flex-shrink-0">
                        <button wire:click="applySelectedPackage"
                                class="flex items-center gap-1.5 px-4 py-2 rounded-md bg-purple-600 hover:bg-purple-700 text-white border-0 cursor-pointer text-[0.72rem] font-bold whitespace-nowrap">
                            @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                            Vorlage einfügen
                        </button>
                    </div>
                @else
                    <div class="flex-1 flex items-center justify-center text-[0.7rem] text-slate-400">
                        Wähle eine Vorlage links zur Vorschau.
                    </div>
                @endif
            </div>
        </div>
    </x-ui-modal>

    {{-- Modal: Freigabe anfordern --}}
    <x-ui-modal wire:model="showApprovalModal" size="md" :hideFooter="true">
        <x-slot name="header">Freigabe anfordern</x-slot>
        <form wire:submit.prevent="requestApproval" class="space-y-4">
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Vorgesetzter / Freigeber *</label>
                <select wire:model="approvalApproverId"
                        class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs">
                    <option value="">— Team-Mitglied wählen —</option>
                    @foreach($teamUsers ?? [] as $u)
                        <option value="{{ $u['id'] }}">{{ $u['name'] }}@if(!empty($u['email'])) ({{ $u['email'] }})@endif</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Hinweis / Kommentar (optional)</label>
                <textarea wire:model="approvalComment" rows="3"
                          placeholder="z.B. Bitte bis Freitag freigeben"
                          class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeApprovalModal">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Freigabe anfordern</x-ui-button>
            </div>
        </form>
    </x-ui-modal>

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
