<?php

namespace Platform\Events\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Events\Models\Activity;
use Platform\Events\Models\Booking;
use Platform\Events\Models\Contract;
use Platform\Events\Models\MrFieldConfig;
use Platform\Events\Models\DocumentSignature;
use Platform\Events\Models\EmailLog;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\EventNote;
use Platform\Events\Models\FeedbackEntry;
use Platform\Events\Models\Invoice;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\PickList;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\ScheduleItem;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\MultiSelectHelper;
use Platform\Events\Services\SettingsService;
use Platform\Locations\Models\Location;

class Detail extends Component
{
    public const STATUS_OPTIONS = ['Option', 'Definitiv', 'Vertrag', 'Abgeschlossen', 'Storno', 'Warteliste', 'Tendenz'];
    public const BOOKING_RANGS  = ['1. Option', '2. Option', '3. Option', 'Definitiv', 'Vertrag', 'Abgesagt'];
    public const NOTE_TYPES     = [
        'liefertext'   => 'Liefertext',
        'absprache'    => 'Absprache',
        'vereinbarung' => 'Vereinbarung',
        'intern'       => 'Interne Info',
    ];

    public ?Event $event = null;

    #[Url(as: 'tab', except: 'basis')]
    public string $activeTab = 'basis';

    // Day-Modal
    public bool $showDayModal = false;
    public ?string $editingDayUuid = null;
    public array $dayForm = [];

    // Booking-Modal
    public bool $showBookingModal = false;
    public ?string $editingBookingUuid = null;
    public array $bookingForm = [];

    // Bulk-Booking: fuer jeden Termin eine Buchung anlegen mit Daten des Tages
    public bool $showBulkBookingModal = false;
    public array $bulkBookingForm = [
        'location_id' => null,
        'raum'        => '',
        'bestuhlung'  => '',
        'optionsrang' => '1. Option',
        'absprache'   => '',
    ];

    // Inline-Edit: Bookings (Raeume) – Map UUID → Felder
    public array $inlineBookings = [];

    // Mehrfachauswahl fuer Bulk-Delete in der Raeume-Tabelle
    public array $selectedBookingUuids = [];
    public array $newBookingInline = [
        'datum'       => '', 'beginn' => '', 'ende' => '',
        'pers'        => '', 'location_id' => null, 'raum' => '',
        'bestuhlung'  => '', 'optionsrang' => '1. Option', 'absprache' => '',
        'taeglich'    => false,
    ];

    // Schedule-Modal
    public bool $showScheduleModal = false;
    public ?string $editingScheduleUuid = null;
    public array $scheduleForm = [];

    // Inline-Edit: Ablaufplan – Map UUID → Felder
    public array $inlineSchedule = [];

    // Mehrfachauswahl fuer Bulk-Delete in der Ablauf-Tabelle
    public array $selectedScheduleUuids = [];
    public array $newScheduleInline = [
        'datum'        => '', 'von' => '', 'bis' => '',
        'beschreibung' => '', 'raum' => '', 'bemerkung' => '',
    ];

    // Note-Modal
    public bool $showNoteModal = false;
    public ?string $editingNoteUuid = null;
    public array $noteForm = [];

    // Drilldown aus Sidebar: Initial-State für die Angebote-/Bestellungen-Child-Components.
    public ?int $pendingQuoteItemId = null;
    public ?int $pendingOrderItemId = null;
    public ?int $pendingQuoteDayId  = null;
    public ?int $pendingOrderDayId  = null;
    public ?string $pendingQuoteView = null;
    public ?string $pendingOrderView = null;

    protected function resetPendingQuote(): void
    {
        $this->pendingQuoteItemId = null;
        $this->pendingQuoteDayId  = null;
        $this->pendingQuoteView   = null;
    }

    protected function resetPendingOrder(): void
    {
        $this->pendingOrderItemId = null;
        $this->pendingOrderDayId  = null;
        $this->pendingOrderView   = null;
    }

    public function openQuoteItem(int $itemId): void
    {
        $this->resetPendingQuote();
        $this->pendingQuoteItemId = $itemId;
        $this->activeTab = 'angebote';
    }

    public function openOrderItem(int $itemId): void
    {
        $this->resetPendingOrder();
        $this->pendingOrderItemId = $itemId;
        $this->activeTab = 'bestellungen';
    }

    public function openQuoteDay(int $dayId): void
    {
        $this->resetPendingQuote();
        $this->pendingQuoteDayId = $dayId;
        $this->activeTab = 'angebote';
    }

    public function openOrderDay(int $dayId): void
    {
        $this->resetPendingOrder();
        $this->pendingOrderDayId = $dayId;
        $this->activeTab = 'bestellungen';
    }

    public function openQuoteArticles(): void
    {
        $this->resetPendingQuote();
        $this->pendingQuoteView = 'articles';
        $this->activeTab = 'angebote';
    }

    public function openOrderArticles(): void
    {
        $this->resetPendingOrder();
        $this->pendingOrderView = 'articles';
        $this->activeTab = 'bestellungen';
    }

    public function resetQuoteView(): void
    {
        $this->resetPendingQuote();
        $this->activeTab = 'angebote';
    }

    public function resetOrderView(): void
    {
        $this->resetPendingOrder();
        $this->activeTab = 'bestellungen';
    }

    /**
     * CRM-Company-Picker: Slot → Event-Spalten (FK + Display-Cache).
     * organizer = Veranstalter, invoice = "Rechnung an", delivery = "Lieferung an".
     */
    public array $crmSearch = [
        'organizer' => '',
        'orderer'   => '',
        'invoice'   => '',
        'delivery'  => '',
    ];

    protected const CRM_SLOTS = [
        'organizer'        => ['id' => 'crm_company_id',                   'label' => 'customer'],
        'orderer'          => ['id' => 'orderer_crm_company_id',           'label' => 'orderer_company'],
        'invoice'          => ['id' => 'invoice_crm_company_id',           'label' => 'invoice_to'],
        'delivery'         => ['id' => 'delivery_crm_company_id',          'label' => 'delivery_supplier'],
        'delivery_address' => ['id' => 'delivery_address_crm_company_id',  'label' => 'delivery_address'],
    ];

    /**
     * Kontakt-Slots sind an einen Company-Slot gebunden. Die Kontakt-Liste wird
     * serverseitig anhand der jeweiligen crm_company_id gefiltert.
     */
    protected const CRM_CONTACT_SLOTS = [
        'organizer'        => ['company_slot' => 'organizer', 'id' => 'organizer_crm_contact_id',        'label' => 'organizer_contact'],
        'organizer_onsite' => ['company_slot' => 'organizer', 'id' => 'organizer_onsite_crm_contact_id', 'label' => 'organizer_contact_onsite'],
        'orderer'          => ['company_slot' => 'orderer',   'id' => 'orderer_crm_contact_id',          'label' => 'orderer_contact'],
        'invoice'          => ['company_slot' => 'invoice',   'id' => 'invoice_crm_contact_id',          'label' => 'invoice_contact'],
        'delivery'         => ['company_slot' => 'delivery',  'id' => 'delivery_crm_contact_id',         'label' => 'delivery_contact'],
    ];

    public function pickCrmCompany(string $slot, ?int $companyId, ?string $label = null): void
    {
        if (!$this->event) return;
        $cfg = self::CRM_SLOTS[$slot] ?? null;
        if (!$cfg) return;

        $this->event->{$cfg['id']} = $companyId ?: null;
        if ($label !== null && $label !== '') {
            $this->event->{$cfg['label']} = $label;
        }

        // Wechselt die Firma, sind die bisherigen Kontakt-Bindungen nicht mehr gueltig.
        // Neben der ID auch den Text-Fallback leeren, damit das Feld nicht den
        // alten Kontakt-Namen weiter anzeigt.
        foreach (self::CRM_CONTACT_SLOTS as $contactSlot => $contactCfg) {
            if ($contactCfg['company_slot'] === $slot) {
                $this->event->{$contactCfg['id']} = null;
                $this->event->{$contactCfg['label']} = null;
            }
        }

        $this->event->save();
        $this->crmSearch[$slot] = '';
    }

    public function clearCrmCompany(string $slot): void
    {
        $this->pickCrmCompany($slot, null);
    }

    public function pickCrmContact(string $slot, ?int $contactId, ?string $label = null): void
    {
        if (!$this->event) return;
        $cfg = self::CRM_CONTACT_SLOTS[$slot] ?? null;
        if (!$cfg) return;

        $this->event->{$cfg['id']} = $contactId ?: null;
        if ($label !== null && $label !== '') {
            $this->event->{$cfg['label']} = $label;
        }
        $this->event->save();
    }

    public function clearCrmContact(string $slot): void
    {
        $this->pickCrmContact($slot, null);
    }

    protected function rules(): array
    {
        return [
            'event.name'                     => 'required|string|max:255',
            'event.customer'                 => 'nullable|string|max:255',
            'event.crm_company_id'           => 'nullable|integer',
            'event.invoice_to'                    => 'nullable|string|max:255',
            'event.invoice_crm_company_id'        => 'nullable|integer',
            'event.invoice_contact'               => 'nullable|string|max:255',
            'event.invoice_crm_contact_id'        => 'nullable|integer',
            'event.delivery_supplier'             => 'nullable|string|max:255',
            'event.delivery_crm_company_id'       => 'nullable|integer',
            'event.delivery_contact'              => 'nullable|string|max:255',
            'event.delivery_crm_contact_id'       => 'nullable|integer',
            'event.organizer_contact'             => 'nullable|string|max:255',
            'event.organizer_crm_contact_id'      => 'nullable|integer',
            'event.organizer_contact_onsite'      => 'nullable|string|max:255',
            'event.organizer_onsite_crm_contact_id' => 'nullable|integer',
            'event.group'                    => 'nullable|string|max:255',
            'event.location'                 => 'nullable|string|max:255',
            'event.start_date'               => 'nullable|date',
            'event.end_date'                 => 'nullable|date',
            'event.status'                   => 'nullable|string|max:64',
            'event.event_type'               => 'nullable|string|max:255',
            'event.organizer_contact'        => 'nullable|string|max:255',
            'event.organizer_contact_onsite' => 'nullable|string|max:255',
            'event.organizer_for_whom'       => 'nullable|string|max:255',
            'event.orderer_company'          => 'nullable|string|max:255',
            'event.orderer_crm_company_id'   => 'nullable|integer',
            'event.orderer_contact'          => 'nullable|string|max:255',
            'event.orderer_crm_contact_id'   => 'nullable|integer',
            'event.orderer_via'              => 'nullable|string|max:32',
            'event.invoice_to'               => 'nullable|string|max:255',
            'event.invoice_contact'          => 'nullable|string|max:255',
            'event.invoice_date_type'        => 'nullable|string|max:64',
            'event.responsible'              => 'nullable|string|max:255',
            'event.cost_center'              => 'nullable|string|max:255',
            'event.cost_carrier'             => 'nullable|string|max:255',
            'event.sign_left'                => 'nullable|string|max:255',
            'event.sign_right'               => 'nullable|string|max:255',
            'event.follow_up_date'           => 'nullable|date',
            'event.follow_up_note'           => 'nullable|string',
            'event.delivery_supplier'        => 'nullable|string|max:255',
            'event.delivery_contact'         => 'nullable|string|max:255',
            'event.inquiry_date'             => 'nullable|date',
            'event.inquiry_time'             => 'nullable|string|max:32',
            'event.inquiry_note'             => 'nullable|string',
            'event.potential'                => 'nullable|string|max:64',
            'event.forwarded'                => 'boolean',
            'event.forwarding_date'          => 'nullable|date',
            'event.forwarding_time'          => 'nullable|string|max:32',
        ];
    }

    public function mount(string $slug): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $event = Event::resolveFromSlug($slug, $team?->id);
        if (!$event) {
            abort(404);
        }

        $this->event = $event;
    }

    public function saveEvent(): void
    {
        $this->validate();
        $this->event->save();
        $this->dispatch('events:toast', message: 'Grunddaten gespeichert');
    }

    /**
     * Live-Save: bei jedem Wechsel eines event.*-Felds sofort persistieren
     * (analog zum Alt-System: @change saveEventData()). Validierungsfehler
     * werden still verschluckt – die UI wuerde sie sonst bei jedem blur anzeigen.
     */
    public function updated($property): void
    {
        if (str_starts_with($property, 'event.') && $this->event) {
            try {
                $this->event->save();
            } catch (\Throwable $e) {
                // ignore – eventuelle Validierungsprobleme werden erst beim
                // expliziten saveEvent() Button gemeldet.
            }
        }
    }

    /**
     * Schneller Inline-Notiz-Creator (Alt-System: add-input in Stream).
     */
    public function addInlineNote(string $type, string $text): void
    {
        $text = trim($text);
        if ($text === '' || !in_array($type, array_keys(self::NOTE_TYPES), true)) {
            return;
        }
        EventNote::create([
            'event_id'  => $this->event->id,
            'team_id'   => $this->event->team_id,
            'user_id'   => Auth::id(),
            'type'      => $type,
            'text'      => $text,
            'user_name' => Auth::user()?->name ?? 'Benutzer',
        ]);
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::STATUS_OPTIONS, true)) {
            return;
        }
        $this->event->status = $status;
        $this->event->save();
    }

    public function duplicate(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $prefix = 'VA#' . now()->year . '-' . now()->format('m');
        $last = Event::where('team_id', $team->id)
            ->where('event_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(event_number) DESC, event_number DESC')
            ->value('event_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        $copy = $this->event->replicate(['uuid', 'event_number', 'status_changed_at']);
        $copy->event_number      = $prefix . $next;
        $copy->name              = $this->event->name . ' (Kopie)';
        $copy->status            = 'Option';
        $copy->status_changed_at = now();
        $copy->user_id           = $user->id;
        $copy->save();

        foreach ($this->event->days as $day) {
            $dayCopy = $day->replicate(['uuid']);
            $dayCopy->event_id = $copy->id;
            $dayCopy->save();
        }
        foreach ($this->event->bookings as $booking) {
            $bookingCopy = $booking->replicate(['uuid']);
            $bookingCopy->event_id = $copy->id;
            $bookingCopy->save();
        }
        foreach ($this->event->scheduleItems as $item) {
            $itemCopy = $item->replicate(['uuid']);
            $itemCopy->event_id = $copy->id;
            $itemCopy->save();
        }

        $this->redirectRoute('events.show', ['slug' => $copy->slug], navigate: true);
    }

    // ========== Management Report ==========

    public function setMrField(string $key, string $value): void
    {
        $data = $this->event->mr_data ?? [];
        $data[$key] = $value;
        $this->event->mr_data = $data;
        $this->event->save();
    }

    public static function mrDefaults(): array
    {
        return [
            ['group' => 'Logistik & Personal', 'key' => 'logistik',              'label' => 'Logistik',              'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group' => 'Logistik & Personal', 'key' => 'getraenkelogistik',     'label' => 'Getränkelogistik',      'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group' => 'Logistik & Personal', 'key' => 'personaldienstleister', 'label' => 'Personaldienstleister', 'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group' => 'Logistik & Personal', 'key' => 'kuechenpersonal',       'label' => 'Küchenpersonal',        'options' => ['fehlende Eingabe', 'Bedarf', 'kein Bedarf', 'OK', 'abgeschlossen']],
            ['group' => 'Produktion',          'key' => 'kuechenproduktion',     'label' => 'Küchenproduktion',      'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group' => 'Produktion',          'key' => 'ort_kueche',            'label' => 'Ort Küchenproduktion',  'options' => ['fehlende Eingabe', 'in Klärung', 'bestätigt', 'nicht benötigt']],
            ['group' => 'Rechnungen',          'key' => 'aconto_location',       'label' => 'A-Conto (Location)',    'options' => ['noch nicht erstellt', 'erstellt', 'versandt', 'bezahlt', 'keine Rechnung']],
            ['group' => 'Rechnungen',          'key' => 'aconto_catering',       'label' => 'A-Conto (Catering)',    'options' => ['noch nicht erstellt', 'erstellt', 'versandt', 'bezahlt', 'keine Rechnung']],
            ['group' => 'Rechnungen',          'key' => 'abschlussrechnung',     'label' => 'Abschlussrechnung',     'options' => ['keine Rechnung', 'noch nicht erstellt', 'erstellt', 'versandt', 'bezahlt']],
            ['group' => 'Controlling',         'key' => 'ctrl_getraenke',        'label' => 'Getränke Lieferant',    'options' => ['fehlende Eingabe', 'ausstehend', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group' => 'Controlling',         'key' => 'ablaufplan_kuelo',      'label' => 'Ablaufplan (KÜLO)',     'options' => ['unbekannt (PL)', 'fehlende Eingabe', 'vorhanden', 'in Bearbeitung', 'abgeschlossen']],
            ['group' => 'Controlling',         'key' => 'ctrl_verbrauch',        'label' => 'Getränkeverbrauch',     'options' => ['fehlende Eingabe', 'ausstehend', 'OK', 'abgeschlossen', 'nicht benötigt']],
        ];
    }

    // ========== Event-Tage ==========

    public function openDayCreate(): void
    {
        $this->dayForm = [
            'label'       => '',
            'day_type'    => 'Veranstaltungstag',
            'datum'       => $this->event->start_date?->format('Y-m-d') ?? '',
            'color'       => '#6366f1',
            'day_of_week' => '',
            'von'         => '',
            'bis'         => '',
            'pers_von'    => '',
            'pers_bis'    => '',
            'day_status'  => $this->event->status ?: 'Option',
        ];
        $this->editingDayUuid = null;
        $this->resetErrorBag();
        $this->showDayModal = true;
    }

    public function openDayEdit(string $uuid): void
    {
        $day = $this->event->days()->where('uuid', $uuid)->firstOrFail();
        $this->dayForm = [
            'label'       => $day->label,
            'day_type'    => $day->day_type ?: 'Veranstaltungstag',
            'datum'       => $day->datum?->format('Y-m-d') ?? '',
            'color'       => $day->color ?: '#6366f1',
            'day_of_week' => $day->day_of_week ?? '',
            'von'         => $day->von ?? '',
            'bis'         => $day->bis ?? '',
            'pers_von'    => $day->pers_von ?? '',
            'pers_bis'    => $day->pers_bis ?? '',
            'day_status'  => $day->day_status ?: 'Option',
        ];
        $this->editingDayUuid = $uuid;
        $this->resetErrorBag();
        $this->showDayModal = true;
    }

    public function closeDayModal(): void
    {
        $this->showDayModal = false;
    }

    public function saveDay(): void
    {
        $this->validate([
            'dayForm.label'      => 'required|string|max:50',
            'dayForm.day_type'   => 'nullable|string|max:40',
            'dayForm.datum'      => 'required|date',
            'dayForm.day_status' => 'nullable|string|max:64',
        ]);

        $payload = $this->dayForm;

        // Doppelte Datumseintraege pro Event vermeiden.
        $dupeQuery = $this->event->days()->whereDate('datum', $payload['datum']);
        if ($this->editingDayUuid) {
            $dupeQuery->where('uuid', '!=', $this->editingDayUuid);
        }
        if ($dupeQuery->exists()) {
            $this->addError('dayForm.datum', 'Fuer dieses Datum existiert bereits ein Termin.');
            return;
        }

        // Wochentag automatisch setzen
        try {
            $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
            $payload['day_of_week'] = $weekdays[Carbon::parse($payload['datum'])->dayOfWeek];
        } catch (\Throwable $e) {
            // Datum ungültig – Wochentag leer lassen
        }

        if ($this->editingDayUuid) {
            $this->event->days()->where('uuid', $this->editingDayUuid)->update($payload);
        } else {
            $maxSort = (int) $this->event->days()->max('sort_order');
            EventDay::create(array_merge($payload, [
                'event_id'   => $this->event->id,
                'team_id'    => $this->event->team_id,
                'user_id'    => Auth::id(),
                'sort_order' => $maxSort + 1,
            ]));
        }

        $this->showDayModal = false;
    }

    public function deleteDay(string $uuid): void
    {
        $this->event->days()->where('uuid', $uuid)->delete();
    }

    // ========== Buchungen ==========

    public function openBookingCreate(): void
    {
        $this->bookingForm = [
            'location_id' => null,
            'raum'        => '',
            'datum'       => $this->event->start_date?->format('Y-m-d') ?? '',
            'beginn'      => '',
            'ende'        => '',
            'pers'        => '',
            'bestuhlung'  => '',
            'optionsrang' => '1. Option',
            'absprache'   => '',
        ];
        $this->editingBookingUuid = null;
        $this->resetErrorBag();
        $this->showBookingModal = true;
    }

    public function openBookingEdit(string $uuid): void
    {
        $booking = $this->event->bookings()->where('uuid', $uuid)->firstOrFail();
        $this->bookingForm = [
            'location_id' => $booking->location_id,
            'raum'        => $booking->raum ?? '',
            'datum'       => $booking->datum ?? '',
            'beginn'      => $booking->beginn ?? '',
            'ende'        => $booking->ende ?? '',
            'pers'        => $booking->pers ?? '',
            'bestuhlung'  => $booking->bestuhlung ?? '',
            'optionsrang' => $booking->optionsrang ?: '1. Option',
            'absprache'   => $booking->absprache ?? '',
        ];
        $this->editingBookingUuid = $uuid;
        $this->resetErrorBag();
        $this->showBookingModal = true;
    }

    public function closeBookingModal(): void
    {
        $this->showBookingModal = false;
    }

    public function saveBooking(): void
    {
        $this->validate([
            'bookingForm.location_id' => 'nullable|integer|exists:locations_locations,id',
            'bookingForm.raum'        => 'nullable|string|max:255',
            'bookingForm.datum'       => 'nullable|string|max:32',
            'bookingForm.beginn'      => 'nullable|string|max:10',
            'bookingForm.ende'        => 'nullable|string|max:10',
            'bookingForm.optionsrang' => 'nullable|string|max:32',
        ]);

        if (empty($this->bookingForm['location_id']) && empty($this->bookingForm['raum'])) {
            $this->addError('bookingForm.location_id', 'Entweder Location oder Raum-Kürzel angeben.');
            return;
        }

        $payload = $this->bookingForm;
        $payload['location_id'] = $payload['location_id'] ?: null;

        if ($this->editingBookingUuid) {
            $this->event->bookings()->where('uuid', $this->editingBookingUuid)->update($payload);
            $label = self::bookingLabel($payload);
            ActivityLogger::log($this->event, 'booking', "Raumbuchung aktualisiert: {$label}");
        } else {
            $maxSort = (int) $this->event->bookings()->max('sort_order');
            $booking = Booking::create(array_merge($payload, [
                'event_id'   => $this->event->id,
                'team_id'    => $this->event->team_id,
                'user_id'    => Auth::id(),
                'sort_order' => $maxSort + 1,
            ]));
            $label = self::bookingLabel($payload);
            ActivityLogger::log($this->event, 'booking', "Raum gebucht: {$label}");
        }

        $this->showBookingModal = false;
    }

    /**
     * Kurzbeschreibung einer Buchung fuer den Activity-Log.
     */
    protected static function bookingLabel(array $data): string
    {
        $parts = [];
        if (!empty($data['datum'])) {
            try {
                $parts[] = Carbon::parse($data['datum'])->format('d.m.Y');
            } catch (\Throwable $e) {
                $parts[] = (string) $data['datum'];
            }
        }
        $raum = trim((string) ($data['raum'] ?? ''));
        if ($raum === '' && !empty($data['location_id'])) {
            $loc = \Platform\Locations\Models\Location::find($data['location_id']);
            $raum = (string) ($loc?->kuerzel ?? $loc?->name ?? '');
        }
        if ($raum !== '') $parts[] = $raum;

        return $parts ? implode(' · ', $parts) : 'Eintrag';
    }

    public function deleteBooking(string $uuid): void
    {
        $booking = $this->event->bookings()->where('uuid', $uuid)->first();
        if ($booking) {
            $label = self::bookingLabel([
                'datum'       => $booking->datum,
                'raum'        => $booking->raum,
                'location_id' => $booking->location_id,
            ]);
            $booking->delete();
            ActivityLogger::log($this->event, 'booking', "Raumbuchung geloescht: {$label}");
        }
        $this->selectedBookingUuids = MultiSelectHelper::remove($this->selectedBookingUuids, [$uuid]);
    }

    public function toggleAllBookings(): void
    {
        $all = $this->event->bookings()->pluck('uuid')->map(fn ($u) => (string) $u)->all();
        $this->selectedBookingUuids = MultiSelectHelper::toggleAll($this->selectedBookingUuids, $all);
    }

    public function toggleBookingSelection(string $uuid): void
    {
        $this->selectedBookingUuids = MultiSelectHelper::toggleSingle($this->selectedBookingUuids, $uuid);
    }

    public function toggleBookingRange(int $from, int $to, bool $select): void
    {
        $uuids = $this->event->bookings()->orderBy('sort_order')->pluck('uuid')
            ->map(fn ($u) => (string) $u)->toArray();
        $this->selectedBookingUuids = MultiSelectHelper::toggleRange($this->selectedBookingUuids, $uuids, $from, $to, $select);
    }

    public function clearBookingSelection(): void
    {
        $this->selectedBookingUuids = [];
    }

    /**
     * Neue Sortierung aus Drag-&-Drop uebernehmen (Array von UUIDs in
     * gewuenschter Reihenfolge).
     */
    public function reorderBookings(array $uuids): void
    {
        foreach ($uuids as $index => $uuid) {
            $this->event->bookings()->where('uuid', $uuid)->update(['sort_order' => $index]);
        }
    }

    public function deleteSelectedBookings(): void
    {
        if (empty($this->selectedBookingUuids)) {
            return;
        }
        $count = count($this->selectedBookingUuids);
        $this->event->bookings()->whereIn('uuid', $this->selectedBookingUuids)->delete();
        ActivityLogger::log($this->event, 'booking', "{$count} Raumbuchung(en) geloescht");
        $this->selectedBookingUuids = [];
    }

    // ---------- Inline-Edit: Bookings ----------

    public function updatedInlineBookings($value, $key): void
    {
        // $key = "uuid.feldname"
        if (!str_contains($key, '.')) return;
        [$uuid, $field] = explode('.', $key, 2);

        $allowed = ['datum', 'beginn', 'ende', 'pers', 'location_id', 'raum', 'bestuhlung', 'optionsrang', 'absprache'];
        if (!in_array($field, $allowed, true)) return;

        $this->event->bookings()->where('uuid', $uuid)->update([
            $field => $value === '' ? null : $value,
        ]);
    }

    /**
     * Wenn bei der neuen Raumbuchung ein Tag gewaehlt wird, Uhrzeit und
     * Personenzahl aus dem EventDay uebernehmen. Unabhaengig voneinander:
     * wenn nur Zeitraum gesetzt ist, wird der vorgeschlagen; wenn nur Pers
     * gesetzt ist, wird nur Pers vorgeschlagen.
     */
    public function updatedNewBookingInline($value, $key): void
    {
        if ($key !== 'datum') {
            return;
        }

        if (empty($value)) {
            $this->newBookingInline['beginn'] = '';
            $this->newBookingInline['ende']   = '';
            $this->newBookingInline['pers']   = '';
            return;
        }

        // Frische Query – Relation koennte gecached sein und aktuelle Aenderungen
        // am Tag (von/bis/pers) nicht enthalten.
        $target = trim((string) $value);
        $day = $this->event->days()->whereDate('datum', $target)->first();

        if (!$day) {
            return;
        }

        // Leere von/bis als 00:00 behandeln (so wird es in der Sidebar angezeigt).
        $von  = trim((string) ($day->von  ?? '')) ?: '00:00';
        $bis  = trim((string) ($day->bis  ?? '')) ?: '00:00';
        $pers = trim((string) ($day->pers_bis ?: $day->pers_von ?: ''));

        $this->newBookingInline['beginn'] = $von;
        $this->newBookingInline['ende']   = $bis;
        $this->newBookingInline['pers']   = $pers;
    }

    public function addInlineBooking(): void
    {
        $data = $this->newBookingInline;
        $taeglich = !empty($data['taeglich']);

        if (empty($data['location_id']) && empty($data['raum'])) {
            return;
        }

        $targetDates = [];
        if ($taeglich) {
            foreach ($this->event->days as $d) {
                $targetDates[] = $d->datum?->format('Y-m-d');
            }
            $targetDates = array_values(array_filter($targetDates));
        } else {
            // Fallback: kein Datum → ersten Event-Tag nehmen, sonst start_date
            $datum = $data['datum'] ?: null;
            if (!$datum) {
                $firstDay = $this->event->days()->orderBy('sort_order')->first();
                $datum = $firstDay?->datum?->format('Y-m-d')
                    ?? $this->event->start_date?->format('Y-m-d');
            }
            $targetDates = [$datum];
        }

        $maxSort = (int) Booking::where('event_id', $this->event->id)->max('sort_order');

        foreach ($targetDates as $dt) {
            $maxSort++;
            Booking::create([
                'event_id'    => $this->event->id,
                'team_id'     => $this->event->team_id,
                'user_id'     => Auth::id(),
                'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null,
                'raum'        => $data['raum'] ?: null,
                'datum'       => $dt,
                'beginn'      => $data['beginn'] ?: null,
                'ende'        => $data['ende'] ?: null,
                'pers'        => $data['pers'] ?: null,
                'bestuhlung'  => $data['bestuhlung'] ?: null,
                'optionsrang' => $data['optionsrang'] ?: '1. Option',
                'absprache'   => $data['absprache'] ?: null,
                'sort_order'  => $maxSort,
            ]);
        }

        $count = count($targetDates);
        $label = self::bookingLabel(['location_id' => $data['location_id'] ?? null, 'raum' => $data['raum'] ?? '']);
        ActivityLogger::log($this->event, 'booking', $count > 1
            ? "{$count} Raumbuchungen angelegt ({$label})"
            : "Raum gebucht: {$label}");

        $this->newBookingInline = [
            'datum'       => '', 'beginn' => '', 'ende' => '',
            'pers'        => '', 'location_id' => null, 'raum' => '',
            'bestuhlung'  => '', 'optionsrang' => '1. Option', 'absprache' => '',
            'taeglich'    => false,
        ];
    }

    public function openBulkBooking(): void
    {
        $this->bulkBookingForm = [
            'location_id' => null,
            'raum'        => '',
            'bestuhlung'  => '',
            'optionsrang' => '1. Option',
            'absprache'   => '',
        ];
        $this->resetErrorBag('bulkBookingForm.raum');
        $this->showBulkBookingModal = true;
    }

    public function closeBulkBooking(): void
    {
        $this->showBulkBookingModal = false;
    }

    /**
     * Legt pro EventDay eine Buchung an und uebernimmt dabei die Uhrzeiten
     * und Personenzahlen des jeweiligen Tages. Raum/Bestuhlung/Optionsrang/
     * Absprache kommen aus dem Bulk-Formular.
     */
    public function submitBulkBooking(): void
    {
        $data = $this->bulkBookingForm;

        if (empty($data['location_id']) && empty($data['raum'])) {
            $this->addError('bulkBookingForm.raum', 'Bitte einen Raum waehlen.');
            return;
        }

        $days = $this->event->days()->orderBy('sort_order')->get();
        if ($days->isEmpty()) {
            $this->showBulkBookingModal = false;
            return;
        }

        $maxSort = (int) Booking::where('event_id', $this->event->id)->max('sort_order');

        foreach ($days as $day) {
            $datum = $day->datum?->format('Y-m-d');
            if (!$datum) continue;

            $pers = $day->pers_bis ?: $day->pers_von;
            $maxSort++;

            Booking::create([
                'event_id'    => $this->event->id,
                'team_id'     => $this->event->team_id,
                'user_id'     => Auth::id(),
                'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null,
                'raum'        => $data['raum'] ?: null,
                'datum'       => $datum,
                'beginn'      => $day->von ?: null,
                'ende'        => $day->bis ?: null,
                'pers'        => $pers ? (string) $pers : null,
                'bestuhlung'  => $data['bestuhlung'] ?: null,
                'optionsrang' => $data['optionsrang'] ?: '1. Option',
                'absprache'   => $data['absprache'] ?: null,
                'sort_order'  => $maxSort,
            ]);
        }

        $label = self::bookingLabel(['location_id' => $data['location_id'] ?? null, 'raum' => $data['raum'] ?? '']);
        ActivityLogger::log($this->event, 'booking', $days->count() . ' Raumbuchungen aus Terminen uebernommen (' . $label . ')');

        $this->showBulkBookingModal = false;
    }

    // ========== Ablaufplan ==========

    public function openScheduleCreate(): void
    {
        $this->scheduleForm = [
            'datum'        => $this->event->start_date?->format('Y-m-d') ?? '',
            'von'          => '',
            'bis'          => '',
            'beschreibung' => '',
            'raum'         => '',
            'bemerkung'    => '',
            'linked'       => false,
        ];
        $this->editingScheduleUuid = null;
        $this->resetErrorBag();
        $this->showScheduleModal = true;
    }

    public function openScheduleEdit(string $uuid): void
    {
        $item = $this->event->scheduleItems()->where('uuid', $uuid)->firstOrFail();
        $this->scheduleForm = [
            'datum'        => $item->datum ?? '',
            'von'          => $item->von ?? '',
            'bis'          => $item->bis ?? '',
            'beschreibung' => $item->beschreibung,
            'raum'         => $item->raum ?? '',
            'bemerkung'    => $item->bemerkung ?? '',
            'linked'       => (bool) $item->linked,
        ];
        $this->editingScheduleUuid = $uuid;
        $this->resetErrorBag();
        $this->showScheduleModal = true;
    }

    public function closeScheduleModal(): void
    {
        $this->showScheduleModal = false;
    }

    public function saveSchedule(): void
    {
        $this->validate([
            'scheduleForm.beschreibung' => 'required|string|max:255',
            'scheduleForm.datum'        => 'nullable|string|max:32',
            'scheduleForm.von'          => 'nullable|string|max:10',
            'scheduleForm.bis'          => 'nullable|string|max:10',
        ]);

        $payload = $this->scheduleForm;

        $label = trim((string) ($payload['beschreibung'] ?? '')) ?: 'Eintrag';

        if ($this->editingScheduleUuid) {
            $this->event->scheduleItems()->where('uuid', $this->editingScheduleUuid)->update($payload);
            ActivityLogger::log($this->event, 'schedule', "Ablauf-Eintrag aktualisiert: {$label}");
        } else {
            $maxSort = (int) $this->event->scheduleItems()->max('sort_order');
            ScheduleItem::create(array_merge($payload, [
                'event_id'   => $this->event->id,
                'team_id'    => $this->event->team_id,
                'user_id'    => Auth::id(),
                'sort_order' => $maxSort + 1,
            ]));
            ActivityLogger::log($this->event, 'schedule', "Ablauf-Eintrag angelegt: {$label}");
        }

        $this->showScheduleModal = false;
    }

    public function deleteSchedule(string $uuid): void
    {
        $item = $this->event->scheduleItems()->where('uuid', $uuid)->first();
        if ($item) {
            $label = trim((string) $item->beschreibung) ?: 'Eintrag';
            $item->delete();
            ActivityLogger::log($this->event, 'schedule', "Ablauf-Eintrag geloescht: {$label}");
        }
        $this->selectedScheduleUuids = MultiSelectHelper::remove($this->selectedScheduleUuids, [$uuid]);
    }

    public function toggleAllSchedule(): void
    {
        $all = $this->event->scheduleItems()->pluck('uuid')->map(fn ($u) => (string) $u)->all();
        $this->selectedScheduleUuids = MultiSelectHelper::toggleAll($this->selectedScheduleUuids, $all);
    }

    public function toggleScheduleSelection(string $uuid): void
    {
        $this->selectedScheduleUuids = MultiSelectHelper::toggleSingle($this->selectedScheduleUuids, $uuid);
    }

    public function toggleScheduleRange(int $from, int $to, bool $select): void
    {
        $uuids = $this->event->scheduleItems()->orderBy('sort_order')->pluck('uuid')
            ->map(fn ($u) => (string) $u)->toArray();
        $this->selectedScheduleUuids = MultiSelectHelper::toggleRange($this->selectedScheduleUuids, $uuids, $from, $to, $select);
    }

    public function clearScheduleSelection(): void
    {
        $this->selectedScheduleUuids = [];
    }

    public function reorderSchedule(array $uuids): void
    {
        foreach ($uuids as $index => $uuid) {
            $this->event->scheduleItems()->where('uuid', $uuid)->update(['sort_order' => $index]);
        }
    }

    public function deleteSelectedSchedule(): void
    {
        if (empty($this->selectedScheduleUuids)) return;
        $count = count($this->selectedScheduleUuids);
        $this->event->scheduleItems()->whereIn('uuid', $this->selectedScheduleUuids)->delete();
        ActivityLogger::log($this->event, 'schedule', "{$count} Ablauf-Eintrag/Eintraege geloescht");
        $this->selectedScheduleUuids = [];
    }

    // ---------- Inline-Edit: Schedule ----------

    public function updatedInlineSchedule($value, $key): void
    {
        if (!str_contains($key, '.')) return;
        [$uuid, $field] = explode('.', $key, 2);

        $allowed = ['datum', 'von', 'bis', 'beschreibung', 'raum', 'bemerkung'];
        if (!in_array($field, $allowed, true)) return;

        $this->event->scheduleItems()->where('uuid', $uuid)->update([
            $field => $value === '' ? null : $value,
        ]);
    }

    public function addInlineSchedule(): void
    {
        $data = $this->newScheduleInline;
        if (trim((string) ($data['beschreibung'] ?? '')) === '') {
            return;
        }

        $maxSort = (int) ScheduleItem::where('event_id', $this->event->id)->max('sort_order');

        // Fallback: kein Datum angegeben → ersten Event-Tag nehmen, sonst start_date des Events
        $datum = $data['datum'] ?: null;
        if (!$datum) {
            $firstDay = $this->event->days()->orderBy('sort_order')->first();
            $datum = $firstDay?->datum?->format('Y-m-d')
                ?? $this->event->start_date?->format('Y-m-d');
        }

        ScheduleItem::create([
            'event_id'     => $this->event->id,
            'team_id'      => $this->event->team_id,
            'user_id'      => Auth::id(),
            'datum'        => $datum,
            'von'          => $data['von'] ?: null,
            'bis'          => $data['bis'] ?: null,
            'beschreibung' => $data['beschreibung'],
            'raum'         => $data['raum'] ?: null,
            'bemerkung'    => $data['bemerkung'] ?: null,
            'sort_order'   => $maxSort + 1,
        ]);

        ActivityLogger::log($this->event, 'schedule', "Ablauf-Eintrag angelegt: {$data['beschreibung']}");

        $this->newScheduleInline = [
            'datum' => '', 'von' => '', 'bis' => '',
            'beschreibung' => '', 'raum' => '', 'bemerkung' => '',
        ];
    }

    // ========== Notizen ==========

    public function openNoteCreate(string $type = 'absprache'): void
    {
        $this->noteForm = [
            'type'      => in_array($type, array_keys(self::NOTE_TYPES), true) ? $type : 'absprache',
            'text'      => '',
            'user_name' => Auth::user()?->name ?? 'Benutzer',
        ];
        $this->editingNoteUuid = null;
        $this->resetErrorBag();
        $this->showNoteModal = true;
    }

    public function openNoteEdit(string $uuid): void
    {
        $note = $this->event->notes()->where('uuid', $uuid)->firstOrFail();
        $this->noteForm = [
            'type'      => $note->type,
            'text'      => $note->text,
            'user_name' => $note->user_name ?: 'Benutzer',
        ];
        $this->editingNoteUuid = $uuid;
        $this->resetErrorBag();
        $this->showNoteModal = true;
    }

    public function closeNoteModal(): void
    {
        $this->showNoteModal = false;
    }

    public function saveNote(): void
    {
        $this->validate([
            'noteForm.type' => 'required|string|in:' . implode(',', array_keys(self::NOTE_TYPES)),
            'noteForm.text' => 'required|string',
        ]);

        $payload = $this->noteForm;

        if ($this->editingNoteUuid) {
            $this->event->notes()->where('uuid', $this->editingNoteUuid)->update($payload);
        } else {
            EventNote::create(array_merge($payload, [
                'event_id' => $this->event->id,
                'team_id'  => $this->event->team_id,
                'user_id'  => Auth::id(),
            ]));
        }

        $this->showNoteModal = false;
    }

    public function deleteNote(string $uuid): void
    {
        $userId = Auth::id();
        // Nur eigene Notizen loeschen
        $note = $this->event->notes()->where('uuid', $uuid)->first();
        if (!$note || $note->user_id !== $userId) {
            return;
        }
        $note->delete();
    }

    public function updateInlineNote(string $uuid, string $text): void
    {
        $userId = Auth::id();
        $note = $this->event->notes()->where('uuid', $uuid)->first();
        if (!$note || $note->user_id !== $userId) {
            return;
        }
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $note->update(['text' => $text]);
    }

    // ========== Render ==========

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $days      = $this->event->days()->orderBy('sort_order')->get();
        $bookings  = $this->event->bookings()->with('location')->orderBy('datum')->orderBy('sort_order')->get();
        $schedule  = $this->event->scheduleItems()->orderBy('datum')->orderBy('sort_order')->get();
        $notes     = $this->event->notes()->orderByDesc('created_at')->get();

        // Inline-State aus DB in Livewire-Properties mappen (ueberschreibt nach jedem Request,
        // was im updated-Hook zuvor persistierte Aenderungen enthaelt).
        $this->inlineBookings = $bookings->mapWithKeys(fn ($b) => [
            $b->uuid => [
                'datum'       => $b->datum,
                'beginn'      => $b->beginn,
                'ende'        => $b->ende,
                'pers'        => $b->pers,
                'location_id' => $b->location_id,
                'raum'        => $b->raum,
                'bestuhlung'  => $b->bestuhlung,
                'optionsrang' => $b->optionsrang,
                'absprache'   => $b->absprache,
            ],
        ])->toArray();

        $this->inlineSchedule = $schedule->mapWithKeys(fn ($s) => [
            $s->uuid => [
                'datum'        => $s->datum,
                'von'          => $s->von,
                'bis'          => $s->bis,
                'beschreibung' => $s->beschreibung,
                'raum'         => $s->raum,
                'bemerkung'    => $s->bemerkung,
            ],
        ])->toArray();
        $locations = Location::where('team_id', $team->id)->orderBy('sort_order')->orderBy('name')->get();

        // Sidebar-Counts + Drilldown-Baum
        $dayIds = $days->pluck('id');
        $quoteItems = QuoteItem::whereIn('event_day_id', $dayIds)->orderBy('sort_order')->get();
        $orderItems = OrderItem::whereIn('event_day_id', $dayIds)->orderBy('sort_order')->get();

        // Farbpalette fuer Tages-Bullets, wenn EventDay keine eigene Farbe hat
        $dayPalette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#14b8a6'];

        $quoteTree = $days->values()->map(function (EventDay $day, int $idx) use ($quoteItems, $dayPalette) {
            $dayItems = $quoteItems->where('event_day_id', $day->id)->values();
            return [
                'day_id'       => $day->id,
                'label'        => $day->label,
                'datum'        => $day->datum?->format('d.m.Y'),
                'color'        => $day->color ?: $dayPalette[$idx % count($dayPalette)],
                'positions'    => (int) $dayItems->sum('positionen'),
                'types'        => $dayItems->map(fn ($i) => [
                    'id'         => $i->id,
                    'typ'        => $i->typ,
                    'positions'  => (int) $i->positionen,
                ])->values()->all(),
            ];
        })->values();

        $orderTree = $days->values()->map(function (EventDay $day, int $idx) use ($orderItems, $dayPalette) {
            $dayItems = $orderItems->where('event_day_id', $day->id)->values();
            return [
                'day_id'       => $day->id,
                'label'        => $day->label,
                'datum'        => $day->datum?->format('d.m.Y'),
                'color'        => $day->color ?: $dayPalette[$idx % count($dayPalette)],
                'positions'    => (int) $dayItems->sum('positionen'),
                'types'        => $dayItems->map(fn ($i) => [
                    'id'        => $i->id,
                    'typ'       => $i->typ,
                    'positions' => (int) $i->positionen,
                ])->values()->all(),
            ];
        })->values();

        $counts = [
            'ablauf'                => $schedule->count(),
            'aktivitaeten'          => Activity::where('event_id', $this->event->id)->count(),
            'vertraege'             => Contract::where('event_id', $this->event->id)->count(),
            'packliste'             => PickList::where('event_id', $this->event->id)->count(),
            'kommunikation'         => EmailLog::where('event_id', $this->event->id)->count(),
            'angebote_items'        => $quoteItems->count(),
            'angebote_positionen'   => (int) $quoteItems->sum('positionen'),
            'bestellungen_items'    => $orderItems->count(),
            'bestellungen_positionen'=> (int) $orderItems->sum('positionen'),
            'rechnungen'            => Invoice::where('event_id', $this->event->id)->count(),
            'feedback'              => FeedbackEntry::where('event_id', $this->event->id)->count(),
            'notizen'               => $notes->count(),
            'buchungen'             => $bookings->count(),
            'tage'                  => $days->count(),
        ];

        // MR-Felder: team-spezifische Konfiguration aus DB (seeded einmalig mit Defaults)
        if ($team) {
            MrFieldConfig::seedDefaultsFor($team->id, Auth::id());
        }
        $mrConfigs = $team
            ? MrFieldConfig::where('team_id', $team->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
            : collect();

        $mrFields = $mrConfigs->map(fn ($c) => [
            'group'   => $c->group_label,
            'key'     => 'mrf_' . $c->id,
            'label'   => $c->label,
            'options' => array_map(fn ($o) => is_array($o) ? ($o['label'] ?? '') : (string) $o, $c->options ?? []),
            'colors'  => collect($c->options ?? [])->mapWithKeys(fn ($o) => [
                (is_array($o) ? ($o['label'] ?? '') : (string) $o) => (is_array($o) ? ($o['color'] ?? 'gray') : 'gray'),
            ])->toArray(),
        ])->groupBy('group')->toArray();

        // Settings-Dropdowns (team-scoped mit Defaults)
        $teamId = $team?->id;
        $settings = [
            'cost_centers'          => SettingsService::costCenters($teamId),
            'cost_carriers'         => SettingsService::costCarriers($teamId),
            'event_types'           => SettingsService::eventTypes($teamId),
            'bestuhlung'            => SettingsService::bestuhlungOptions($teamId),
            'schedule_descriptions' => SettingsService::scheduleDescriptions($teamId),
            'day_types'             => SettingsService::dayTypes($teamId),
        ];

        // Signaturen pro role
        $signatures = DocumentSignature::where('event_id', $this->event->id)
            ->with('user:id,name')
            ->get()
            ->keyBy('role');

        // Team-Mitglieder fuer User-Picker (Verantwortlich / Unterschriften)
        $teamUsers = $team
            ? $team->users()->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->values()
                ->all()
            : [];

        // CRM-Company-Slots (Veranstalter / Rechnung / Lieferung) – lose via Contracts aus platform-crm.
        // Ohne CRM-Modul bleiben Listen leer und der Picker faellt auf den Freitext-Fallback zurueck.
        $crmCompanyAvailable = app()->bound(\Platform\Core\Contracts\CrmCompanyOptionsProviderInterface::class);
        $crmProvider = $crmCompanyAvailable ? app(\Platform\Core\Contracts\CrmCompanyOptionsProviderInterface::class) : null;
        $crmResolver = app()->bound(\Platform\Core\Contracts\CrmCompanyResolverInterface::class)
            ? app(\Platform\Core\Contracts\CrmCompanyResolverInterface::class)
            : null;

        $crmSlots = [];
        foreach (self::CRM_SLOTS as $slot => $cfg) {
            $query = trim($this->crmSearch[$slot] ?? '');
            $currentId = $this->event?->{$cfg['id']};
            $crmSlots[$slot] = [
                'options'   => $crmProvider ? $crmProvider->options($query !== '' ? $query : null, 30) : [],
                'label'     => $currentId && $crmResolver ? $crmResolver->displayName($currentId) : null,
                'url'       => $currentId && $crmResolver ? $crmResolver->url($currentId) : null,
                'currentId' => $currentId,
                'fallback'  => $this->event?->{$cfg['label']},
            ];
        }

        // Contact-Slots via Service auflösen (Fallback auf Veranstalter,
        // Liste, Label, URL, hasCompany-Flag).
        $contactResolver = new \Platform\Events\Services\ContactPickerResolver(
            self::CRM_SLOTS,
            self::CRM_CONTACT_SLOTS
        );
        $contactData = $this->event ? $contactResolver->resolve($this->event) : ['available' => false, 'slots' => []];
        $crmContactAvailable = $contactData['available'];
        $crmContactSlots = $contactData['slots'];

        $notesByType = $notes->groupBy('type');

        // Räume, die beim Event tatsaechlich gebucht sind → Auswahlliste fuer Ablaufplan.
        // value = was gespeichert wird (bevorzugt Kuerzel)
        // short = kurze Anzeige (Kuerzel) in Closed-State
        // label = Lange Anzeige (Kuerzel — Name) im Dropdown
        $eventRooms = $bookings
            ->map(function ($b) {
                $kuerzel = trim((string) ($b->location?->kuerzel ?? ''));
                $name    = trim((string) ($b->location?->name ?? ''));
                $raum    = trim((string) ($b->raum ?? ''));
                $value   = $kuerzel !== '' ? $kuerzel : ($name !== '' ? $name : $raum);
                if ($value === '') return null;
                $short = $value;
                $label = $value;
                if ($kuerzel !== '' && $name !== '' && $kuerzel !== $name) {
                    $label = $kuerzel . ' — ' . $name;
                } elseif ($kuerzel === '' && $name !== '' && $raum !== '' && $name !== $raum) {
                    $label = $name . ' — ' . $raum;
                }
                return ['value' => $value, 'short' => $short, 'label' => $label];
            })
            ->filter()
            ->unique(fn ($r) => $r['value'])
            ->values()
            ->all();

        return view('events::livewire.detail', [
            'days'           => $days,
            'bookings'       => $bookings,
            'schedule'       => $schedule,
            'notes'          => $notes,
            'notesByType'    => $notesByType,
            'locations'      => $locations,
            'statusOptions'  => self::STATUS_OPTIONS,
            'bookingRangs'   => self::BOOKING_RANGS,
            'noteTypes'      => self::NOTE_TYPES,
            'mrFields'       => $mrFields,
            'counts'         => $counts,
            'quoteTree'      => $quoteTree,
            'orderTree'      => $orderTree,
            'settings'       => $settings,
            'signatures'     => $signatures,
            'teamUsers'      => $teamUsers,
            'crmCompanyAvailable' => $crmCompanyAvailable,
            'crmSlots'            => $crmSlots,
            'crmContactAvailable' => $crmContactAvailable,
            'crmContactSlots'     => $crmContactSlots,
            'eventRooms'          => $eventRooms,
        ])->layout('platform::layouts.app');
    }
}
