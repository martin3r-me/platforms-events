{{-- Board Panel Card: rendert ein Panel als Kanban-Card basierend auf panel_key --}}
@php
    $title = $panelConfig[$card->panel_key] ?? $card->panel_key;
    $lbl  = 'text-[0.55rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-0.5';
    $in   = 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1 text-[0.7rem] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30';
    $inMn = 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1 text-[0.7rem] font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30';
@endphp

<x-ui-kanban-card :title="$title" :sortable-id="$card->id" :href="null">
    @switch($card->panel_key)

        @case('termine')
            <div class="space-y-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[0.55rem] font-semibold text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ $days->count() }} Tage</span>
                    <button wire:click="openDayCreate" type="button"
                            class="w-5 h-5 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:border-[var(--ui-primary)]/40 flex items-center justify-center text-sm leading-none">+</button>
                </div>
                @if($days->isEmpty())
                    <div class="text-[0.62rem] text-[var(--ui-muted)] text-center italic">Keine Termine</div>
                @else
                    @php
                        $typeBadge = fn ($dt) => match($dt) {
                            'Veranstaltungstag' => ['bg-blue-50 text-blue-700', 'VA'],
                            'Aufbautag'         => ['bg-amber-50 text-amber-700', 'AUF'],
                            'Abbautag'          => ['bg-slate-100 text-slate-600', 'AB'],
                            'Rüsttag'           => ['bg-violet-50 text-violet-700', 'RÜST'],
                            default             => ['bg-slate-50 text-slate-500', $dt],
                        };
                        $statusBadge = fn ($st) => match($st) {
                            'Vertrag'   => 'bg-green-100 text-green-700',
                            'Definitiv' => 'bg-green-50 text-green-600',
                            'Option'    => 'bg-yellow-50 text-yellow-700',
                            'Storno'    => 'bg-red-50 text-red-700',
                            default     => 'bg-slate-100 text-slate-600',
                        };
                    @endphp
                    <div class="divide-y divide-[var(--ui-border)]/30">
                        @foreach($days as $day)
                            @php [$typeCls, $typeTxt] = $typeBadge($day->day_type ?: 'Veranstaltungstag'); @endphp
                            <div wire:click="openDayEdit('{{ $day->uuid }}')"
                                 class="px-1 py-1.5 hover:bg-[var(--ui-muted-5)]/50 cursor-pointer group flex gap-2 items-start">
                                <div class="flex flex-col items-center bg-[var(--ui-muted-5)] rounded px-1.5 py-0.5 min-w-[36px] border-l-2" style="border-color: {{ $day->color }}">
                                    <span class="text-[0.48rem] font-bold uppercase text-[var(--ui-muted)] leading-none">{{ $day->day_of_week ?: '—' }}</span>
                                    <span class="text-[0.85rem] font-bold text-[var(--ui-secondary)] leading-tight">{{ $day->datum?->format('d.m') ?: '—' }}</span>
                                </div>
                                <div class="flex-1 min-w-0 space-y-0.5">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <span class="text-[0.5rem] font-semibold uppercase px-1 py-0 rounded {{ $typeCls }}">{{ $typeTxt }}</span>
                                        <span class="text-[0.5rem] font-bold px-1 py-0 rounded {{ $statusBadge($day->day_status) }}">{{ $day->day_status }}</span>
                                        <button wire:click.stop="deleteDay('{{ $day->uuid }}')" wire:confirm="Tag löschen?"
                                                class="ml-auto opacity-0 group-hover:opacity-100 text-red-500 p-0.5">
                                            @svg('heroicon-o-trash', 'w-2.5 h-2.5')
                                        </button>
                                    </div>
                                    @if($day->von || $day->bis)
                                        <div class="flex items-center gap-1 text-[0.55rem] font-mono text-[var(--ui-muted)]">
                                            @svg('heroicon-o-clock', 'w-2.5 h-2.5')
                                            {{ $day->von ?: '00:00' }}–{{ $day->bis ?: '00:00' }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @break

        @case('name')
            <div class="p-1">
                <input wire:model.blur="event.name" type="text" placeholder="Name der Veranstaltung" class="{{ $in }} font-semibold">
            </div>
            @break

        @case('veranstalter')
            <div class="p-1 space-y-1.5">
                <div>
                    <label class="{{ $lbl }}">Unternehmen</label>
                    @php $s = ($crmSlots ?? [])['organizer'] ?? []; @endphp
                    @include('events::partials.crm-company-picker', [
                        'slot'          => 'organizer',
                        'available'     => $crmCompanyAvailable ?? false,
                        'options'       => $s['options']   ?? [],
                        'label'         => ($s['label']     ?? null) ?: $event->customer,
                        'url'           => $s['url']       ?? null,
                        'currentId'     => $s['currentId'] ?? null,
                        'fallbackField' => 'customer',
                        'placeholder'   => '— CRM-Firma wählen —',
                    ])
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <div>
                        <label class="{{ $lbl }}">Ansprechpartner</label>
                        @php $c = ($crmContactSlots ?? [])['organizer'] ?? []; @endphp
                        <div wire:key="crm-contact-organizer-{{ md5(json_encode($c['contacts'] ?? [])) }}">
                            @include('events::partials.crm-contact-picker', [
                                'slot'          => 'organizer',
                                'available'     => $crmContactAvailable ?? false,
                                'contacts'      => $c['contacts']     ?? [],
                                'currentId'     => $c['currentId']    ?? null,
                                'currentLabel'  => $c['currentLabel'] ?? $event->organizer_contact,
                                'currentUrl'    => $c['currentUrl']   ?? null,
                                'hasCompany'    => $c['hasCompany']   ?? false,
                                'fallbackField' => 'organizer_contact',
                                'placeholder'   => '— Kontakt wählen —',
                            ])
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Asp. vor Ort</label>
                        @php $c = ($crmContactSlots ?? [])['organizer_onsite'] ?? []; @endphp
                        <div wire:key="crm-contact-organizer-onsite-{{ md5(json_encode($c['contacts'] ?? [])) }}">
                            @include('events::partials.crm-contact-picker', [
                                'slot'          => 'organizer_onsite',
                                'available'     => $crmContactAvailable ?? false,
                                'contacts'      => $c['contacts']     ?? [],
                                'currentId'     => $c['currentId']    ?? null,
                                'currentLabel'  => $c['currentLabel'] ?? $event->organizer_contact_onsite,
                                'currentUrl'    => $c['currentUrl']   ?? null,
                                'hasCompany'    => $c['hasCompany']   ?? false,
                                'fallbackField' => 'organizer_contact_onsite',
                                'placeholder'   => '— Kontakt wählen —',
                            ])
                        </div>
                    </div>
                </div>
            </div>
            @break

        @case('besteller')
            <div class="p-1 space-y-1.5">
                <div>
                    <label class="{{ $lbl }}">Unternehmen</label>
                    @php $s = ($crmSlots ?? [])['orderer'] ?? []; @endphp
                    @include('events::partials.crm-company-picker', [
                        'slot'          => 'orderer',
                        'available'     => $crmCompanyAvailable ?? false,
                        'options'       => $s['options']   ?? [],
                        'label'         => ($s['label']     ?? null) ?: $event->orderer_company,
                        'url'           => $s['url']       ?? null,
                        'currentId'     => $s['currentId'] ?? null,
                        'fallbackField' => 'orderer_company',
                        'placeholder'   => '— CRM-Firma wählen —',
                    ])
                </div>
                <div>
                    <label class="{{ $lbl }}">Ansprechpartner</label>
                    @php $c = ($crmContactSlots ?? [])['orderer'] ?? []; @endphp
                    <div wire:key="crm-contact-orderer-{{ md5(json_encode($c['contacts'] ?? [])) }}">
                        @include('events::partials.crm-contact-picker', [
                            'slot'          => 'orderer',
                            'available'     => $crmContactAvailable ?? false,
                            'contacts'      => $c['contacts']     ?? [],
                            'currentId'     => $c['currentId']    ?? null,
                            'currentLabel'  => $c['currentLabel'] ?? $event->orderer_contact,
                            'currentUrl'    => $c['currentUrl']   ?? null,
                            'hasCompany'    => $c['hasCompany']   ?? false,
                            'fallbackField' => 'orderer_contact',
                            'placeholder'   => '— Kontakt wählen —',
                        ])
                    </div>
                </div>
            </div>
            @break

        @case('rechnung')
            <div class="p-1 space-y-1.5">
                <div>
                    <label class="{{ $lbl }}">Rechnung an</label>
                    @php $s = ($crmSlots ?? [])['invoice'] ?? []; @endphp
                    @include('events::partials.crm-company-picker', [
                        'slot'          => 'invoice',
                        'available'     => $crmCompanyAvailable ?? false,
                        'options'       => $s['options']   ?? [],
                        'label'         => ($s['label']     ?? null) ?: $event->invoice_to,
                        'url'           => $s['url']       ?? null,
                        'currentId'     => $s['currentId'] ?? null,
                        'fallbackField' => 'invoice_to',
                        'placeholder'   => '— CRM-Firma wählen —',
                    ])
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <div>
                        <label class="{{ $lbl }}">Ansprechpartner</label>
                        @php $c = ($crmContactSlots ?? [])['invoice'] ?? []; @endphp
                        <div wire:key="crm-contact-invoice-{{ md5(json_encode($c['contacts'] ?? [])) }}">
                            @include('events::partials.crm-contact-picker', [
                                'slot'          => 'invoice',
                                'available'     => $crmContactAvailable ?? false,
                                'contacts'      => $c['contacts']     ?? [],
                                'currentId'     => $c['currentId']    ?? null,
                                'currentLabel'  => $c['currentLabel'] ?? $event->invoice_contact,
                                'currentUrl'    => $c['currentUrl']   ?? null,
                                'hasCompany'    => $c['hasCompany']   ?? false,
                                'fallbackField' => 'invoice_contact',
                                'placeholder'   => '— Kontakt wählen —',
                            ])
                        </div>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Rechnungsdatum</label>
                        <select wire:model.live="event.invoice_date_type" class="{{ $in }}">
                            <option value="">— wählen —</option>
                            @foreach($days as $d)
                                <option value="{{ $d->datum?->format('Y-m-d') }}">{{ $d->datum?->format('d.m.Y') }}@if($d->day_of_week) ({{ $d->day_of_week }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            @break

        @case('zustaendigkeit')
            <div class="p-1 space-y-1.5">
                <div class="grid grid-cols-2 gap-1.5">
                    <div>
                        <label class="{{ $lbl }}">Verantwortlich</label>
                        @include('events::partials.user-picker', [
                            'field'       => 'event.responsible',
                            'users'       => $teamUsers,
                            'current'     => $event->responsible,
                            'placeholder' => '— Teammitglied wählen —',
                        ])
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Vor Ort</label>
                        @include('events::partials.user-picker', [
                            'field'       => 'event.responsible_onsite',
                            'users'       => $teamUsers,
                            'current'     => $event->responsible_onsite,
                            'placeholder' => '— Teammitglied wählen —',
                        ])
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <div>
                        <label class="{{ $lbl }}">Kostenstelle</label>
                        @if(!empty($settings['cost_centers']))
                            <select wire:model.blur="event.cost_center" class="{{ $inMn }}">
                                <option value="">— wählen —</option>
                                @foreach($settings['cost_centers'] as $cc)
                                    <option value="{{ $cc }}">{{ $cc }}</option>
                                @endforeach
                            </select>
                        @else
                            <input wire:model.blur="event.cost_center" type="text" class="{{ $inMn }}">
                        @endif
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Kostenträger</label>
                        <input wire:model.blur="event.cost_carrier" type="text" placeholder="{{ $event->event_number }}" class="{{ $inMn }}">
                    </div>
                </div>
                @if(!empty($orderNumber))
                    <div>
                        <label class="{{ $lbl }}">Ordernummer</label>
                        <div class="flex items-center gap-1.5 border border-[var(--ui-border)] rounded-md px-2 py-1 bg-[var(--ui-muted-5)]/40"
                             x-data="{ copied: false }">
                            <span class="flex-1 text-[0.7rem] font-mono font-semibold text-[var(--ui-secondary)] truncate">{{ $orderNumber }}</span>
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($orderNumber)).then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
                                    :title="copied ? 'Kopiert!' : 'In Zwischenablage kopieren'"
                                    class="p-0.5 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition">
                                <template x-if="!copied">@svg('heroicon-o-clipboard', 'w-3.5 h-3.5')</template>
                                <template x-if="copied">@svg('heroicon-o-check', 'w-3.5 h-3.5 text-green-600')</template>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
            @break

        @case('anlass')
            <div class="p-1 space-y-1.5">
                <div>
                    <label class="{{ $lbl }}">Anlassgruppe</label>
                    <input wire:model.blur="event.group" type="text" placeholder="z.B. Messe" class="{{ $in }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Anlass</label>
                    <input wire:model.blur="event.event_type" type="text" class="{{ $in }}"
                           list="event-type-options"
                           placeholder="{{ !empty($settings['event_types']) ? 'Freitext oder Auswahl…' : '' }}">
                    @if(!empty($settings['event_types']))
                        <datalist id="event-type-options">
                            @foreach($settings['event_types'] as $t)
                                <option value="{{ $t }}">
                            @endforeach
                        </datalist>
                    @endif
                </div>
            </div>
            @break

        @case('lieferung')
            <div class="p-1 space-y-1.5">
                <div>
                    <label class="{{ $lbl }}">Eigene Location</label>
                    @include('events::partials.location-picker', [
                        'model'       => 'event.delivery_location_id',
                        'locations'   => $locations,
                        'current'     => $event->delivery_location_id,
                        'placeholder' => '— Location wählen —',
                    ])
                </div>
                <div>
                    <label class="{{ $lbl }}">Externe Lieferadresse (CRM)</label>
                    @php $sa = ($crmSlots ?? [])['delivery_address'] ?? []; @endphp
                    @include('events::partials.crm-company-picker', [
                        'slot'          => 'delivery_address',
                        'available'     => $crmCompanyAvailable ?? false,
                        'options'       => $sa['options']   ?? [],
                        'label'         => ($sa['label']     ?? null) ?: $event->delivery_address,
                        'url'           => $sa['url']       ?? null,
                        'currentId'     => $sa['currentId'] ?? null,
                        'fallbackField' => 'delivery_address',
                        'placeholder'   => '— CRM-Firma wählen —',
                    ])
                </div>
                <div>
                    <label class="{{ $lbl }}">Bemerkung</label>
                    <input wire:model.blur="event.delivery_note" type="text"
                           placeholder="z.B. Haupteingang, Anlieferung über Hof…"
                           class="{{ $in }}">
                </div>
            </div>
            @break

        @case('follow_up')
            @php
                $followDate = $event->follow_up_date;
                $followStatus = ['label' => null, 'bg' => '#f1f5f9', 'fg' => '#64748b'];
                if ($followDate) {
                    $today = \Carbon\Carbon::today();
                    $diff = (int) $today->diffInDays($followDate, false);
                    if ($diff < 0)        { $followStatus = ['label' => abs($diff) . ' Tage überfällig', 'bg' => '#fee2e2', 'fg' => '#b91c1c']; }
                    elseif ($diff === 0)  { $followStatus = ['label' => 'heute',                          'bg' => '#fef3c7', 'fg' => '#b45309']; }
                    elseif ($diff <= 3)   { $followStatus = ['label' => 'in ' . $diff . ' Tagen',         'bg' => '#fef9c3', 'fg' => '#a16207']; }
                    elseif ($diff <= 14)  { $followStatus = ['label' => 'in ' . $diff . ' Tagen',         'bg' => '#dbeafe', 'fg' => '#1d4ed8']; }
                    else                  { $followStatus = ['label' => 'in ' . $diff . ' Tagen',         'bg' => '#dcfce7', 'fg' => '#15803d']; }
                }
            @endphp
            <div class="p-1 space-y-2">
                @if($followStatus['label'])
                    <span class="text-[0.55rem] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full"
                          style="background: {{ $followStatus['bg'] }}; color: {{ $followStatus['fg'] }};">
                        {{ $followStatus['label'] }}
                    </span>
                @endif
                <div>
                    <label class="{{ $lbl }}">Datum</label>
                    <div class="flex items-center gap-1.5">
                        <input wire:model.live="event.follow_up_date" type="date" class="{{ $inMn }} flex-1">
                        @if($event->follow_up_date)
                            <button type="button" wire:click="$set('event.follow_up_date', null)"
                                    title="Termin entfernen"
                                    class="px-1.5 py-1 text-slate-400 hover:text-red-600 transition">
                                @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                            </button>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 mt-1 flex-wrap">
                        <button type="button" wire:click="$set('event.follow_up_date', '{{ now()->toDateString() }}')"
                                class="text-[0.58rem] px-2 py-0.5 rounded border border-[var(--ui-border)] bg-white hover:bg-slate-50 text-slate-600">heute</button>
                        <button type="button" wire:click="$set('event.follow_up_date', '{{ now()->addDays(7)->toDateString() }}')"
                                class="text-[0.58rem] px-2 py-0.5 rounded border border-[var(--ui-border)] bg-white hover:bg-slate-50 text-slate-600">+1W</button>
                        <button type="button" wire:click="$set('event.follow_up_date', '{{ now()->addDays(14)->toDateString() }}')"
                                class="text-[0.58rem] px-2 py-0.5 rounded border border-[var(--ui-border)] bg-white hover:bg-slate-50 text-slate-600">+2W</button>
                        <button type="button" wire:click="$set('event.follow_up_date', '{{ now()->addMonth()->toDateString() }}')"
                                class="text-[0.58rem] px-2 py-0.5 rounded border border-[var(--ui-border)] bg-white hover:bg-slate-50 text-slate-600">+1M</button>
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Bemerkung</label>
                    <textarea wire:model.blur="event.follow_up_note" rows="3" placeholder="Was ist offen?" class="{{ $in }}"></textarea>
                </div>
            </div>
            @break

        @case('liefertext')
            <div>
                <div class="flex items-center justify-between px-1 mb-1">
                    <span class="text-[0.55rem] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ ($notesByType ?? collect())->get('liefertext', collect())->count() }} Einträge</span>
                </div>
                @include('events::partials.note-stream', ['type' => 'liefertext', 'notes' => ($notesByType ?? collect())->get('liefertext', collect())])
            </div>
            @break

        @case('eingang')
            <div class="p-1 space-y-1.5">
                <div class="grid grid-cols-[1fr_60px] gap-1.5">
                    <div>
                        <label class="{{ $lbl }}">Eingangsdatum</label>
                        <input wire:model.live="event.inquiry_date" type="date" class="{{ $inMn }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Uhrzeit</label>
                        @include('events::partials.time-input', ['model' => 'event.inquiry_time', 'placeholder' => '10:00', 'class' => $inMn.' text-center'])
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">Bestellt über</label>
                    <div class="flex gap-0.5 bg-[var(--ui-muted-5)] rounded-md p-0.5 w-fit">
                        @foreach([
                            'mail' => ['icon' => 'heroicon-o-envelope',         'label' => 'E-Mail'],
                            'phone' => ['icon' => 'heroicon-o-phone',           'label' => 'Telefon'],
                            'web' => ['icon' => 'heroicon-o-computer-desktop',  'label' => 'Web'],
                        ] as $via => $meta)
                            <button type="button" wire:click="$set('event.orderer_via', '{{ $via }}')"
                                    title="{{ $meta['label'] }}"
                                    class="p-1 rounded transition
                                           {{ ($event->orderer_via ?? 'mail') === $via
                                              ? 'bg-white shadow-sm text-sky-600'
                                              : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}">
                                @svg($meta['icon'], 'w-3 h-3')
                            </button>
                        @endforeach
                    </div>
                </div>
                @php $pct = (int) preg_replace('/[^0-9]/', '', (string) ($event->potential ?? '')); @endphp
                <div x-data="{
                        pct: {{ $pct }},
                        bg()   { return this.pct >= 70 ? '#dcfce7' : this.pct >= 50 ? '#fef3c7' : this.pct >= 30 ? '#ffedd5' : this.pct >= 10 ? '#fee2e2' : '#f1f5f9'; },
                        fill() { return this.pct >= 90 ? '#16a34a' : this.pct >= 70 ? '#22c55e' : this.pct >= 50 ? '#f59e0b' : this.pct >= 30 ? '#f97316' : this.pct >= 10 ? '#ef4444' : '#cbd5e1'; },
                        txt()  { return this.pct >= 90 ? '#15803d' : this.pct >= 70 ? '#16a34a' : this.pct >= 50 ? '#a16207' : this.pct >= 30 ? '#c2410c' : this.pct >= 10 ? '#dc2626' : '#94a3b8'; }
                     }">
                    <label class="{{ $lbl }}">Potenzialanalyse</label>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="flex-1 h-1.5 rounded-full overflow-hidden" :style="'background:' + bg()">
                            <div class="h-full rounded-full transition-all duration-300" :style="'width:' + pct + '%; background:' + fill()"></div>
                        </div>
                        <span class="text-[0.7rem] font-bold font-mono min-w-[2.5rem] text-right" :style="'color:' + txt()" x-text="pct ? pct + '%' : '—'"></span>
                    </div>
                    @php
                        $potentialOptions = [
                            '10% (unwahrscheinlich)',
                            '30% (unverbindliche Anfrage)',
                            '50% (Tendenz offen)',
                            '70% (deutliche Tendenz zur Buchung)',
                            '90% (ziemlich definitiv)',
                        ];
                        $currentPotential = (string) ($event->potential ?? '');
                        $isCustomPotential = $currentPotential !== '' && !in_array($currentPotential, $potentialOptions, true);
                    @endphp
                    <select wire:model.blur="event.potential" @change="pct = parseInt($event.target.value) || 0" class="{{ $in }}">
                        <option value="">— bitte wählen —</option>
                        @if($isCustomPotential)
                            <option value="{{ $currentPotential }}">{{ $currentPotential }} (extern gesetzt)</option>
                        @endif
                        @foreach($potentialOptions as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @break

        @case('weiterleitung')
            <div class="p-1 space-y-1.5">
                <label class="flex items-start gap-1.5 cursor-pointer select-none bg-[var(--ui-muted-5)]/40 border border-[var(--ui-border)] rounded-md px-2 py-1.5">
                    <input wire:model.live="event.forwarded" type="checkbox" class="w-3 h-3 mt-0.5 accent-violet-500 cursor-pointer flex-shrink-0">
                    <span class="text-[0.65rem] font-semibold text-[var(--ui-secondary)]">Bearbeitung nach Weiterleitung</span>
                </label>
                <div class="grid grid-cols-[1fr_60px] gap-1.5">
                    <div>
                        <label class="{{ $lbl }}">Eingangsdatum</label>
                        <input wire:model.live="event.forwarding_date" type="date" class="{{ $inMn }}">
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Uhrzeit</label>
                        @include('events::partials.time-input', ['model' => 'event.forwarding_time', 'placeholder' => '14:30', 'class' => $inMn.' text-center'])
                    </div>
                </div>
            </div>
            @break

        @case('absprache')
            <div>
                <div class="flex items-center justify-between px-1 mb-1">
                    <span class="text-[0.55rem] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ ($notesByType ?? collect())->get('absprache', collect())->count() }}</span>
                </div>
                @include('events::partials.note-stream', ['type' => 'absprache', 'notes' => ($notesByType ?? collect())->get('absprache', collect())])
            </div>
            @break

        @case('vereinbarung')
            <div>
                <div class="flex items-center justify-between px-1 mb-1">
                    <span class="text-[0.55rem] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ ($notesByType ?? collect())->get('vereinbarung', collect())->count() }}</span>
                </div>
                @include('events::partials.note-stream', ['type' => 'vereinbarung', 'notes' => ($notesByType ?? collect())->get('vereinbarung', collect())])
            </div>
            @break

        @default
            <div class="p-2 text-xs text-[var(--ui-muted)] italic">Panel: {{ $card->panel_key }}</div>
    @endswitch
</x-ui-kanban-card>
