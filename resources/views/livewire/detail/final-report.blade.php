<div class="space-y-3 max-w-[960px]">
    @php
        $fmtEur = fn($v) => number_format((float)$v, 2, ',', '.') . ' €';
        $initials = function ($name) {
            if (!$name) return '?';
            $parts = preg_split('/\s+/', trim($name));
            return strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
        };
        $avatarColor = function ($name) {
            $colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444'];
            $sum = 0;
            foreach (str_split((string)$name) as $ch) $sum = ($sum * 31 + ord($ch)) & 0xffff;
            return $colors[$sum % count($colors)];
        };
        $invTypeLabel = [
            'rechnung'         => 'Rechnung',
            'teilrechnung'     => 'Teilrechnung',
            'schlussrechnung'  => 'Schlussrechnung',
            'gutschrift'       => 'Gutschrift',
            'storno'           => 'Storno',
        ];
        $invStatusBadge = [
            'paid'     => ['label' => 'bezahlt',    'cls' => 'bg-green-100 text-green-700'],
            'sent'     => ['label' => 'versendet',  'cls' => 'bg-blue-100 text-blue-700'],
            'draft'    => ['label' => 'Entwurf',    'cls' => 'bg-slate-100 text-slate-600'],
            'overdue'  => ['label' => 'überfällig', 'cls' => 'bg-red-100 text-red-700'],
            'cancelled'=> ['label' => 'storniert',  'cls' => 'bg-slate-100 text-slate-400'],
        ];
    @endphp

    {{-- Header + PDF --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-[1rem] font-bold text-[var(--ui-secondary)]">Schlussbericht</p>
            <p class="text-[0.65rem] text-[var(--ui-muted)]">Veranstaltung {{ $event->event_number }} · Stand {{ now()->format('d.m.Y') }}</p>
        </div>
        <a href="{{ route('events.final-report.pdf', ['event' => $event->slug]) }}" target="_blank"
           class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-md bg-slate-800 hover:bg-slate-900 text-white border-0 text-[0.65rem] font-semibold">
            @svg('heroicon-o-printer', 'w-3.5 h-3.5')
            Drucken / PDF
        </a>
    </div>

    {{-- 1. Eckdaten --}}
    <div class="bg-slate-50 border border-[var(--ui-border)] rounded-lg overflow-hidden">
        <div class="flex items-center gap-2 px-3.5 py-2.5 border-b border-[var(--ui-border)] bg-white">
            <div class="w-[3px] h-3.5 bg-blue-600 rounded-sm flex-shrink-0"></div>
            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Eckdaten der Veranstaltung</span>
        </div>
        <div class="p-3.5 grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach([
                ['label' => 'Veranstaltungsname',       'value' => $event->name ?: '—'],
                ['label' => 'Zeitraum',                 'value' => ($event->start_date?->format('d.m.Y') ?? '—') . ($event->end_date ? ' – '.$event->end_date->format('d.m.Y') : '')],
                ['label' => 'Projektnummer',            'value' => $event->event_number, 'mono' => true],
                ['label' => 'Auftraggeber',             'value' => $event->customer ?: '—'],
                ['label' => 'Projektverantwortliche/r', 'value' => $event->responsible ?: '—'],
                ['label' => 'Location',                 'value' => $event->location ?: '—'],
                ['label' => 'Anlass',                   'value' => $event->event_type ?: '—'],
                ['label' => 'Kostenstelle / Kostenträger', 'value' => ($event->cost_center ?: '—') . ' / ' . ($event->cost_carrier ?: '—'), 'mono' => true],
            ] as $row)
                <div>
                    <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">{{ $row['label'] }}</p>
                    <p class="text-[0.72rem] text-slate-700 m-0 {{ ($row['mono'] ?? false) ? 'font-mono' : '' }}">{{ $row['value'] }}</p>
                </div>
            @endforeach
            <div>
                <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Status</p>
                <span class="text-[0.65rem] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-200">{{ $event->status ?: '—' }}</span>
            </div>
        </div>
    </div>

    {{-- 2. Teilnehmer & Räume --}}
    <div class="bg-slate-50 border border-[var(--ui-border)] rounded-lg overflow-hidden">
        <div class="flex items-center gap-2 px-3.5 py-2.5 border-b border-[var(--ui-border)] bg-white">
            <div class="w-[3px] h-3.5 bg-purple-500 rounded-sm flex-shrink-0"></div>
            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Teilnehmer & Räume</span>
        </div>
        <div class="p-3.5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3.5">
                <div class="bg-white border border-[var(--ui-border)] rounded-md px-3 py-2.5 text-center">
                    <p class="text-[1.4rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $roomsUnique }}</p>
                    <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Räume belegt</p>
                </div>
                <div class="bg-white border border-[var(--ui-border)] rounded-md px-3 py-2.5 text-center">
                    <p class="text-[1.4rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $event->days->count() }}</p>
                    <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Veranstaltungstage</p>
                </div>
                <div class="bg-white border border-[var(--ui-border)] rounded-md px-3 py-2.5 text-center">
                    <p class="text-[1.4rem] font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $event->bookings->count() }}</p>
                    <p class="text-[0.6rem] text-[var(--ui-muted)] mt-1 font-medium">Raumbuchungen</p>
                </div>
            </div>

            @if($event->bookings->isNotEmpty())
                <table class="w-full border-collapse text-[0.68rem]">
                    <thead>
                        <tr class="border-b border-slate-200 text-[0.58rem] uppercase text-[var(--ui-muted)]">
                            <th class="text-left py-1.5 px-2">Datum</th>
                            <th class="text-left py-1.5 px-2">Raum</th>
                            <th class="text-left py-1.5 px-2">Bestuhlung</th>
                            <th class="text-left py-1.5 px-2">Beginn</th>
                            <th class="text-left py-1.5 px-2">Ende</th>
                            <th class="text-right py-1.5 px-2">Pers.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $byDate = $event->bookings->groupBy(fn($b) => $b->datum);
                        @endphp
                        @foreach($byDate as $datum => $rows)
                            @foreach($rows as $i => $b)
                                <tr class="border-b border-slate-100">
                                    <td class="py-1.5 px-2 font-semibold text-slate-700">{{ $i === 0 ? $datum : '' }}</td>
                                    <td class="py-1.5 px-2"><span class="text-[0.6rem] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700">{{ $b->location?->kuerzel ?: $b->raum }}</span></td>
                                    <td class="py-1.5 px-2">{{ $b->bestuhlung ?: '—' }}</td>
                                    <td class="py-1.5 px-2">{{ $b->beginn ?: '—' }}</td>
                                    <td class="py-1.5 px-2">{{ $b->ende ?: '—' }}</td>
                                    <td class="py-1.5 px-2 text-right font-mono">{{ $b->pers ?: '—' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- 3. Umsatz & Finanzen --}}
    <div class="bg-slate-50 border border-[var(--ui-border)] rounded-lg overflow-hidden">
        <div class="flex items-center gap-2 px-3.5 py-2.5 border-b border-[var(--ui-border)] bg-white">
            <div class="w-[3px] h-3.5 bg-green-600 rounded-sm flex-shrink-0"></div>
            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Umsatz & Finanzen</span>
        </div>
        <div class="p-3.5 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:pr-5 md:border-r border-slate-200">
                <p class="text-[0.6rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Umsatzübersicht nach Angebot</p>
                <table class="w-full border-collapse">
                    @foreach($quotes as $q)
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 text-[0.68rem] text-slate-600">{{ $q->title ?? ('Angebot #'.$q->id) }}</td>
                            <td class="py-1.5 text-[0.68rem] font-semibold text-[var(--ui-secondary)] text-right font-mono">{{ $fmtEur($quoteSums[$q->id] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-green-50">
                        <td class="py-2 px-1.5 text-[0.72rem] font-bold text-green-700">Gesamtumsatz</td>
                        <td class="py-2 px-1.5 text-[0.72rem] font-bold text-green-700 text-right font-mono">{{ $fmtEur($totalRevenue) }}</td>
                    </tr>
                </table>
            </div>
            <div class="md:pl-0">
                <p class="text-[0.6rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Rechnungsstatus</p>
                <div class="flex flex-col gap-1.5">
                    @forelse($invoices as $inv)
                        @php $s = $invStatusBadge[$inv->status] ?? ['label' => $inv->status, 'cls' => 'bg-slate-100 text-slate-600']; @endphp
                        <div class="flex items-center justify-between px-2.5 py-2 bg-white border border-[var(--ui-border)] rounded-md">
                            <div>
                                <p class="text-[0.68rem] font-semibold text-[var(--ui-secondary)] m-0">{{ $invTypeLabel[$inv->type] ?? $inv->type }}</p>
                                <p class="text-[0.6rem] text-[var(--ui-muted)] m-0 font-mono">{{ $inv->invoice_number }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[0.68rem] font-semibold text-[var(--ui-secondary)] m-0 font-mono">{{ $fmtEur($inv->brutto) }}</p>
                                <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full {{ $s['cls'] }}">{{ $s['label'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-[0.62rem] text-slate-400 text-center py-3">Keine Rechnungen vorhanden</p>
                    @endforelse
                    <div class="flex items-center justify-between px-2.5 py-2 bg-blue-50 border border-blue-200 rounded-md mt-1">
                        <p class="text-[0.68rem] font-bold text-blue-700 m-0">Bereits bezahlt</p>
                        <p class="text-[0.68rem] font-bold text-blue-700 m-0 font-mono">{{ $fmtEur($paid) }}</p>
                    </div>
                    <div class="flex items-center justify-between px-2.5 py-2 bg-red-50 border border-red-200 rounded-md">
                        <p class="text-[0.68rem] font-bold text-red-700 m-0">Offener Restbetrag</p>
                        <p class="text-[0.68rem] font-bold text-red-700 m-0 font-mono">{{ $fmtEur($open) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Management Report Status --}}
    <div class="bg-slate-50 border border-[var(--ui-border)] rounded-lg overflow-hidden">
        <div class="flex items-center gap-2 px-3.5 py-2.5 border-b border-[var(--ui-border)] bg-white">
            <div class="w-[3px] h-3.5 bg-amber-500 rounded-sm flex-shrink-0"></div>
            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Management Report Status</span>
        </div>
        <div class="p-3.5">
            @if($mrConfigs->isEmpty())
                <p class="text-[0.65rem] text-[var(--ui-muted)] italic">Noch keine Report-Felder konfiguriert.</p>
            @else
                <div class="grid gap-1.5" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));">
                    @foreach($mrConfigs as $cfg)
                        @php
                            $options = is_array($cfg->options) ? $cfg->options : [];
                            $val = $mrData[(string)$cfg->id] ?? ($options[0]['label'] ?? '—');
                            $matching = collect($options)->firstWhere('label', $val);
                            $color = $matching['color'] ?? 'gray';
                            $cls = match ($color) {
                                'red'    => 'bg-red-100 text-red-600',
                                'yellow' => 'bg-amber-100 text-amber-700',
                                'green'  => 'bg-green-100 text-green-700',
                                default  => 'bg-slate-100 text-slate-500',
                            };
                        @endphp
                        <div class="bg-white border border-[var(--ui-border)] rounded-md px-2.5 py-1.5">
                            <p class="text-[0.55rem] font-semibold text-slate-600 m-0 mb-0.5">{{ $cfg->label }}</p>
                            <span class="text-[0.52rem] font-bold px-1.5 py-0.5 rounded-full {{ $cls }}">{{ $val }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- 5. Nachbewertung --}}
    <div class="bg-slate-50 border border-[var(--ui-border)] rounded-lg overflow-hidden">
        <div class="flex items-center gap-2 px-3.5 py-2.5 border-b border-[var(--ui-border)] bg-white">
            <div class="w-[3px] h-3.5 bg-pink-500 rounded-sm flex-shrink-0"></div>
            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Nachbewertung</span>
        </div>
        <div class="p-3.5 grid grid-cols-1 md:grid-cols-3 gap-3.5">
            <div>
                <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Interne Bewertung</p>
                <select wire:model.live="internalRating"
                        class="w-full bg-white border border-slate-200 rounded-md px-2 py-1 text-[0.65rem] text-slate-700 cursor-pointer">
                    <option value="">— bitte wählen —</option>
                    @foreach(['Sehr gut','Gut','Befriedigend','Verbesserungsbedarf','Nicht zufriedenstellend'] as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Kundenzufriedenheit</p>
                <select wire:model.live="customerSatisfaction"
                        class="w-full bg-white border border-slate-200 rounded-md px-2 py-1 text-[0.65rem] text-slate-700 cursor-pointer">
                    <option value="">— bitte wählen —</option>
                    @foreach(['Sehr zufrieden','Zufrieden','Neutral','Unzufrieden','Sehr unzufrieden'] as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <p class="text-[0.58rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Folgebuchung empfohlen</p>
                <select wire:model.live="rebookingRecommendation"
                        class="w-full bg-white border border-slate-200 rounded-md px-2 py-1 text-[0.65rem] text-slate-700 cursor-pointer">
                    <option value="">— bitte wählen —</option>
                    @foreach(['Ja, unbedingt','Ja, mit Anmerkungen','Neutral','Eher nicht','Nein'] as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Kommentar / Empfehlung --}}
            <div class="md:col-span-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-1.5">
                        <div class="w-[3px] h-3.5 bg-purple-500 rounded-sm"></div>
                        <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Kommentar / Empfehlung</span>
                    </div>
                    <span class="text-[0.6rem] font-medium text-[var(--ui-muted)] bg-slate-100 px-1.5 py-0.5 rounded-full">{{ $schlussNotes->count() }} Einträge</span>
                </div>

                <div class="flex flex-col gap-0.5 mb-2.5 max-h-[200px] overflow-y-auto">
                    @forelse($schlussNotes as $note)
                        @php
                            $name = $note->user?->name ?? 'Unbekannt';
                            $canEdit = $note->user_id === $currentUserId;
                        @endphp
                        <div class="group flex gap-2 px-2 py-1.5 rounded-md bg-white border border-slate-100 relative">
                            <div class="w-[22px] h-[22px] rounded-full flex items-center justify-center flex-shrink-0" style="background: {{ $avatarColor($name) }}">
                                <span class="text-[0.52rem] font-bold text-white">{{ $initials($name) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="text-[0.65rem] font-semibold text-slate-700">{{ $name }}</span>
                                    <span class="text-[0.58rem] text-[var(--ui-muted)] bg-slate-100 px-1.5 rounded-full">
                                        {{ $note->created_at->format('d.m.Y · H:i') }}h
                                    </span>
                                </div>
                                <p class="text-[0.65rem] text-slate-600 leading-snug whitespace-pre-wrap m-0">{{ $note->text }}</p>
                            </div>
                            @if($canEdit)
                                <button wire:click="deleteNote('{{ $note->uuid }}')" wire:confirm="Eintrag löschen?"
                                        class="absolute top-1 right-1 w-[18px] h-[18px] rounded bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                    @svg('heroicon-o-trash', 'w-2.5 h-2.5')
                                </button>
                            @endif
                        </div>
                    @empty
                        <div class="flex items-center justify-center py-5 border border-dashed border-slate-200 rounded-md bg-white">
                            <span class="text-[0.62rem] text-slate-400">Noch kein Kommentar</span>
                        </div>
                    @endforelse
                </div>

                <div class="flex gap-1.5 items-start bg-white border border-slate-200 rounded-md px-2 py-1.5">
                    <div class="w-[22px] h-[22px] rounded-full flex items-center justify-center flex-shrink-0" style="background: {{ $avatarColor($currentUser) }}">
                        <span class="text-[0.52rem] font-bold text-white">{{ $initials($currentUser) }}</span>
                    </div>
                    <textarea wire:model="newNote" wire:keydown.ctrl.enter="addNote" rows="2"
                              placeholder="Kommentar oder Empfehlung... (Ctrl+Enter)"
                              class="flex-1 border-0 bg-transparent resize-none text-[0.65rem] text-slate-700 outline-none leading-snug py-0.5"></textarea>
                    <button type="button" wire:click="addNote"
                            class="bg-purple-500 hover:bg-purple-600 text-white border-0 rounded px-2 py-0.5 text-[0.6rem] font-semibold whitespace-nowrap">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
