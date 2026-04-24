{{-- Basis-Tab 4-Spalten-Layout analog Alt-System (kompakt) --}}
@php
    $lbl  = 'text-[0.55rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] block mb-0.5';
    $in   = 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1 text-[0.7rem] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30';
    $inMn = 'w-full border border-[var(--ui-border)] rounded-md px-2 py-1 text-[0.7rem] font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30';
@endphp

<div class="grid grid-cols-1 xl:grid-cols-[190px_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] gap-3">

    {{-- ========== Col 1: Termine ========== --}}
    <div>
        <x-ui-panel>
            <div class="flex items-center justify-between p-2 border-b border-[var(--ui-border)]">
                <div class="flex items-center gap-2">
                    <span class="w-0.5 h-3.5 rounded-full bg-blue-500"></span>
                    <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Termine</span>
                    <span class="text-[0.55rem] font-semibold text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ $days->count() }} Tage</span>
                </div>
                <button wire:click="openDayCreate" type="button"
                        class="w-5 h-5 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:border-[var(--ui-primary)]/40 flex items-center justify-center text-sm leading-none">+</button>
            </div>
            @if($days->isEmpty())
                <div class="p-3 text-[0.62rem] text-[var(--ui-muted)] text-center italic">Keine Termine</div>
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
                        @php
                            [$typeCls, $typeTxt] = $typeBadge($day->day_type ?: 'Veranstaltungstag');
                            $splitA = (int) ($day->split_a ?? 50);
                        @endphp
                        <div wire:click="openDayEdit('{{ $day->uuid }}')"
                             class="px-2 py-2 hover:bg-[var(--ui-muted-5)]/50 cursor-pointer group flex gap-2.5 items-start">
                            <div class="flex flex-col items-center justify-center bg-[var(--ui-muted-5)] rounded-md px-2 py-1 min-w-[42px] border-l-2" style="border-color: {{ $day->color }}">
                                <span class="text-[0.5rem] font-bold uppercase text-[var(--ui-muted)] leading-none">{{ $day->day_of_week ?: '—' }}</span>
                                <span class="text-[1rem] font-bold text-[var(--ui-secondary)] leading-tight tracking-tight">{{ $day->datum?->format('d.m') ?: '—' }}</span>
                                <span class="text-[0.48rem] font-mono text-[var(--ui-muted)] leading-none">{{ $day->datum?->format('Y') }}</span>
                            </div>
                            <div class="flex-1 min-w-0 space-y-1">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="text-[0.5rem] font-semibold uppercase tracking-wide px-1 py-0 rounded {{ $typeCls }}" title="{{ $day->day_type ?: 'Veranstaltungstag' }}">{{ $typeTxt }}</span>
                                    <span class="text-[0.55rem] font-bold px-1.5 py-0 rounded {{ $statusBadge($day->day_status) }}">{{ $day->day_status }}</span>
                                    <button wire:click.stop="deleteDay('{{ $day->uuid }}')" wire:confirm="Tag löschen?"
                                            class="ml-auto opacity-0 group-hover:opacity-100 text-red-500 p-0.5">
                                        @svg('heroicon-o-trash', 'w-2.5 h-2.5')
                                    </button>
                                </div>
                                @if($day->von || $day->bis)
                                    <div class="flex items-center gap-1 text-[0.58rem] font-mono text-[var(--ui-muted)]">
                                        @svg('heroicon-o-clock', 'w-2.5 h-2.5')
                                        {{ $day->von ?: '00:00' }}–{{ $day->bis ?: '00:00' }}
                                    </div>
                                @endif
                                @if($day->pers_von || $day->pers_bis || $splitA !== 50 || $day->children_count)
                                    <div class="flex items-center gap-1 flex-wrap">
                                        @if($day->pers_von || $day->pers_bis)
                                            <span class="text-[0.55rem] text-[var(--ui-muted)] flex items-center gap-0.5">
                                                @svg('heroicon-o-users', 'w-2.5 h-2.5')
                                                {{ $day->pers_von ?: '?' }}–{{ $day->pers_bis ?: '?' }}
                                            </span>
                                        @endif
                                        @if($splitA !== 50)
                                            <span class="text-[0.5rem] font-mono font-semibold px-1 py-0 rounded bg-slate-100" title="Verteilung A/B">
                                                <span class="text-blue-600">{{ $splitA }}</span>/<span class="text-pink-600">{{ 100 - $splitA }}</span>
                                            </span>
                                        @endif
                                        @if($day->children_count)
                                            <span class="text-[0.5rem] font-semibold text-amber-700 bg-amber-50 px-1 py-0 rounded" title="Kinderanzahl">K {{ $day->children_count }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui-panel>
    </div>

    {{-- ========== Col 2: Name / Veranstalter / Besteller / Rechnung / Zuständigkeit ========== --}}
    <div class="space-y-3">

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-slate-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Name</span>
            </div>
            <div class="p-2">
                <input wire:model.blur="event.name" type="text" placeholder="Name der Veranstaltung" class="{{ $in }} font-semibold">
            </div>
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-blue-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Veranstalter</span>
            </div>
            <div class="p-2 space-y-1.5">
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
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-pink-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Besteller</span>
            </div>
            <div class="p-2 space-y-1.5">
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
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-amber-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Rechnung</span>
            </div>
            <div class="p-2 space-y-1.5">
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
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-indigo-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Zuständigkeit</span>
            </div>
            <div class="p-2 space-y-1.5">
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
        </x-ui-panel>
    </div>

    {{-- ========== Col 3: Anlass / Lieferung an / Wiedervorlage / Liefertext ========== --}}
    <div class="space-y-3">

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-emerald-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Anlass</span>
            </div>
            <div class="p-2 space-y-1.5">
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
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-orange-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Lieferung an</span>
            </div>
            <div class="p-2 space-y-1.5">
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
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-cyan-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Wiedervorlage</span>
            </div>
            <div class="p-2 space-y-1.5">
                <div>
                    <label class="{{ $lbl }}">Datum</label>
                    <input wire:model.live="event.follow_up_date" type="date" class="{{ $inMn }}">
                </div>
                <div>
                    <label class="{{ $lbl }}">Bemerkung</label>
                    <textarea wire:model.blur="event.follow_up_note" rows="2" class="{{ $in }}"></textarea>
                </div>
            </div>
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center justify-between p-2 border-b border-[var(--ui-border)]">
                <div class="flex items-center gap-2">
                    <span class="w-0.5 h-3.5 rounded-full bg-orange-500"></span>
                    <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Liefertext</span>
                </div>
                <span class="text-[0.55rem] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ $notesByType->get('liefertext', collect())->count() }} Einträge</span>
            </div>
            @include('events::partials.note-stream', ['type' => 'liefertext', 'notes' => $notesByType->get('liefertext', collect())])
        </x-ui-panel>

    </div>

    {{-- ========== Col 4: Eingang / Weiterleitung / Absprache+Vereinbarung ========== --}}
    <div class="space-y-3">

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-sky-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Eingang</span>
            </div>
            <div class="p-2 space-y-1.5">
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
                <div>
                    <label class="{{ $lbl }}">Bemerkung zur Anfrage</label>
                    <input wire:model.blur="event.inquiry_note" type="text" placeholder="Bemerkung hinzufügen…" class="{{ $in }}">
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
                    <select wire:model.blur="event.potential" @change="pct = parseInt($event.target.value) || 0" class="{{ $in }}">
                        <option value="">— bitte wählen —</option>
                        <option value="10% (unwahrscheinlich)">10% (unwahrscheinlich)</option>
                        <option value="30% (unverbindliche Anfrage)">30% (unverbindliche Anfrage)</option>
                        <option value="50% (Tendenz offen)">50% (Tendenz offen)</option>
                        <option value="70% (deutliche Tendenz zur Buchung)">70% (deutliche Tendenz zur Buchung)</option>
                        <option value="90% (ziemlich definitiv)">90% (ziemlich definitiv)</option>
                    </select>
                </div>
            </div>
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center gap-2 p-2 border-b border-[var(--ui-border)]">
                <span class="w-0.5 h-3.5 rounded-full bg-violet-500"></span>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Weiterleitung</span>
            </div>
            <div class="p-2 space-y-1.5">
                <label class="flex items-start gap-1.5 cursor-pointer select-none bg-[var(--ui-muted-5)]/40 border border-[var(--ui-border)] rounded-md px-2 py-1.5">
                    <input wire:model.live="event.forwarded" type="checkbox" class="w-3 h-3 mt-0.5 accent-violet-500 cursor-pointer flex-shrink-0">
                    <span class="text-[0.65rem] font-semibold text-[var(--ui-secondary)]">Bearbeitung nach Weiterleitung durch anderes Team</span>
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
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center justify-between p-2 border-b border-[var(--ui-border)]">
                <div class="flex items-center gap-2">
                    <span class="w-0.5 h-3.5 rounded-full bg-sky-500"></span>
                    <span class="text-[0.7rem] font-bold text-[var(--ui-secondary)]">Erste Absprache</span>
                </div>
                <span class="text-[0.55rem] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ $notesByType->get('absprache', collect())->count() }}</span>
            </div>
            @include('events::partials.note-stream', ['type' => 'absprache', 'notes' => $notesByType->get('absprache', collect())])
        </x-ui-panel>

        <x-ui-panel>
            <div class="flex items-center justify-between p-2 border-b border-[var(--ui-border)]">
                <div class="flex items-center gap-2">
                    <span class="w-0.5 h-3.5 rounded-full bg-emerald-500"></span>
                    <span class="text-[0.7rem] font-bold text-[var(--ui-secondary)]">Vereinbarung</span>
                </div>
                <span class="text-[0.55rem] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-full">{{ $notesByType->get('vereinbarung', collect())->count() }}</span>
            </div>
            @include('events::partials.note-stream', ['type' => 'vereinbarung', 'notes' => $notesByType->get('vereinbarung', collect())])
        </x-ui-panel>

    </div>
</div>
