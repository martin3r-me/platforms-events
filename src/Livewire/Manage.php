<?php

namespace Platform\Events\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\EventFactory;
use Platform\Events\Services\EventMover;

class Manage extends Component
{
    public const STATUS_OPTIONS = ['Option', 'Definitiv', 'Vertrag', 'Abgeschlossen', 'Storno', 'Warteliste', 'Tendenz'];

    #[Url(as: 'period', except: 'month')]
    public string $period = 'month';

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    /** Ansichtsmodus der Veranstaltungs-Uebersicht: 'list' (Default) oder 'calendar'. */
    #[Url(as: 'view', except: 'list')]
    public string $viewMode = 'list';

    /** Sub-Ansicht des Kalenders: month (Default), week, day oder agenda. */
    #[Url(as: 'cal', except: 'month')]
    public string $calendarView = 'month';

    /** Faerbung im Kalender: 'status' (Default) oder 'type'. */
    #[Url(as: 'color', except: 'status')]
    public string $colorMode = 'status';

    /** Filter „Nur Highlights": zeigt nur besonders markierte Veranstaltungen. */
    #[Url(as: 'highlights', except: false)]
    public bool $highlightsOnly = false;

    #[Url(as: 'resp', except: '')]
    public string $responsibleFilter = '';

    #[Url(as: 'type', except: '')]
    public string $eventTypeFilter = '';

    #[Url(as: 'loc', except: '')]
    public string $locationFilter = '';

    // Create-Modal
    public bool $showCreateModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $customer = null;

    #[Validate('nullable|integer')]
    public ?int $crm_company_id = null;

    public array $crmSearch = ['customer' => ''];

    #[Validate('required|date')]
    public string $start_date = '';

    #[Validate('nullable|date|after_or_equal:start_date')]
    public ?string $end_date = null;

    #[Validate('nullable|string')]
    public ?string $status = 'Option';

    public function updatingPeriod($value): void
    {
        if (!in_array($value, ['week', 'month', 'year', 'all', 'custom'], true)) {
            $this->period = 'month';
        }
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['list', 'calendar'], true) ? $mode : 'list';
    }

    public function setCalendarView(string $view): void
    {
        $this->calendarView = in_array($view, ['month', 'week', 'day', 'agenda'], true) ? $view : 'month';
    }

    public function setColorMode(string $mode): void
    {
        $this->colorMode = in_array($mode, ['status', 'type'], true) ? $mode : 'status';
    }

    public function clearCalendarFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'responsibleFilter', 'eventTypeFilter', 'locationFilter', 'highlightsOnly', 'dateFrom', 'dateTo']);
    }

    /**
     * Verschiebt eine Veranstaltung auf ein neues Startdatum. EventMover zieht
     * EventDays, Bookings und ScheduleItems mit dem gleichen Offset mit.
     * Wird vom Kalender-Drag&Drop nach Confirm aufgerufen.
     */
    public function moveEvent(int $id, string $newStart): void
    {
        $team = Auth::user()->currentTeam;
        $event = Event::where('team_id', $team->id)->find($id);
        if (!$event || !$event->start_date) return;

        $oldStart = $event->start_date->format('d.m.Y');
        $result = EventMover::move($event, $newStart);

        if ($result['offset_days'] !== 0) {
            $event->refresh();
            $msg = sprintf(
                'Veranstaltung %s → %s (%+d Tage, %d Tage / %d Bookings / %d Ablauf-Items mitgezogen)',
                $oldStart,
                $event->start_date->format('d.m.Y'),
                $result['offset_days'],
                $result['affected_event_days'],
                $result['affected_bookings'],
                $result['affected_schedule_items'],
            );
            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log($event, 'event', $msg);
            }
            session()->flash('eventMoved', $msg);
        }
    }

    public function toggleHighlightsOnly(): void
    {
        $this->highlightsOnly = !$this->highlightsOnly;
    }

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function closeCreate(): void
    {
        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    protected function resetCreateForm(): void
    {
        $this->reset(['name', 'customer', 'start_date', 'end_date', 'crm_company_id']);
        $this->crmSearch = ['customer' => ''];
        $this->status = 'Option';
        $this->resetErrorBag();
    }

    public function updatedStartDate($value): void
    {
        if (!empty($value) && empty($this->end_date)) {
            $this->end_date = $value;
        }
    }

    public function pickCrmCompany(string $slot, ?int $companyId, ?string $label = null): void
    {
        if ($slot !== 'customer') return;

        $this->crm_company_id = $companyId ?: null;
        if ($companyId && $label !== null && trim($label) !== '') {
            $this->customer = trim($label);
        }
        $this->crmSearch[$slot] = '';
    }

    public function clearCrmCompany(string $slot): void
    {
        if ($slot !== 'customer') return;

        $this->crm_company_id = null;
        $this->customer = null;
    }

    public function create(): void
    {
        $data = $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        $event = EventFactory::create($user, $team->id, [
            'name'           => $data['name'],
            'customer'       => $data['customer'] ?? null,
            'crm_company_id' => $this->crm_company_id,
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'] ?? null,
            'status'         => $data['status'] ?? 'Option',
        ]);

        $this->showCreateModal = false;
        $this->resetCreateForm();

        $this->redirectRoute('events.show', ['slug' => $event->slug], navigate: true);
    }

    public function delete(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        Event::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail()->delete();
    }

    protected function periodRange(): array
    {
        if ($this->dateFrom || $this->dateTo) {
            try {
                $s = $this->dateFrom ? Carbon::createFromFormat('d.m.Y', $this->dateFrom)->format('Y-m-d') : null;
                $e = $this->dateTo ? Carbon::createFromFormat('d.m.Y', $this->dateTo)->format('Y-m-d') : null;
                return [$s, $e];
            } catch (\Throwable $e) {
                return [null, null];
            }
        }

        return match ($this->period) {
            'week'  => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'  => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'all'   => [null, null],
            default => [null, null],
        };
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        [$periodStart, $periodEnd] = $this->periodRange();

        $query = Event::query()
            ->where('team_id', $team->id)
            ->withCount('days');

        if ($periodStart) {
            $query->where(function ($q) use ($periodStart, $periodEnd) {
                $q->where(function ($q2) use ($periodStart, $periodEnd) {
                    $q2->where('start_date', '<=', $periodEnd)
                        ->where(function ($q3) use ($periodStart) {
                            $q3->where('end_date', '>=', $periodStart)->orWhereNull('end_date');
                        });
                })->orWhereNull('start_date');
            });
        }

        if ($this->search !== '') {
            $q = '%' . $this->search . '%';
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $q)
                    ->orWhere('customer', 'like', $q)
                    ->orWhere('event_number', 'like', $q);
            });
        }

        if ($this->statusFilter !== '' && $this->statusFilter !== 'Alle') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->responsibleFilter !== '') {
            $query->where('responsible', $this->responsibleFilter);
        }

        if ($this->eventTypeFilter !== '') {
            $query->where('event_type', $this->eventTypeFilter);
        }

        if ($this->locationFilter !== '') {
            $query->where('location', $this->locationFilter);
        }

        if ($this->highlightsOnly) {
            $query->where('is_highlight', true);
        }

        $today = now()->toDateString();

        $events = $query
            ->orderByRaw("CASE WHEN start_date >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('start_date')
            ->paginate(50);

        // Eager-load Tage (nur Pax-Felder) fuer die Karten – min/max Personenzahl.
        $events->getCollection()->load(['days' => fn ($q) => $q->select('id', 'event_id', 'pers_von', 'pers_bis')]);

        // Umsatz pro Event aggregieren: Summe events_quote_items.umsatz ueber alle Event-Days.
        $eventIds = $events->getCollection()->pluck('id')->all();
        $revenueByEvent = [];
        if (!empty($eventIds)) {
            $rows = DB::table('events_quote_items')
                ->join('events_event_days', 'events_quote_items.event_day_id', '=', 'events_event_days.id')
                ->whereIn('events_event_days.event_id', $eventIds)
                ->whereNull('events_quote_items.deleted_at')
                ->whereNull('events_event_days.deleted_at')
                ->select('events_event_days.event_id as event_id', DB::raw('SUM(events_quote_items.umsatz) as total'))
                ->groupBy('events_event_days.event_id')
                ->get();
            foreach ($rows as $r) {
                $revenueByEvent[(int) $r->event_id] = (float) $r->total;
            }
        }

        $stats = [
            'total'    => Event::where('team_id', $team->id)->count(),
            'upcoming' => Event::where('team_id', $team->id)
                ->where(function ($q) use ($today) {
                    $q->where('end_date', '>=', $today)->orWhereNull('end_date');
                })->count(),
            'past'     => Event::where('team_id', $team->id)->where('end_date', '<', $today)->count(),
        ];

        // MR-Felder fuer den Team (seeded beim ersten Zugriff)
        \Platform\Events\Models\MrFieldConfig::seedDefaultsFor($team->id, $user->id);
        $mrFields = \Platform\Events\Models\MrFieldConfig::where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // CRM-Company-Picker fuer das Create-Modal (optionale Kopplung an CRM).
        $crmCompanyAvailable = app()->bound(\Platform\Core\Contracts\CrmCompanyOptionsProviderInterface::class);
        $crmProvider = $crmCompanyAvailable ? app(\Platform\Core\Contracts\CrmCompanyOptionsProviderInterface::class) : null;
        $crmResolver = app()->bound(\Platform\Core\Contracts\CrmCompanyResolverInterface::class)
            ? app(\Platform\Core\Contracts\CrmCompanyResolverInterface::class)
            : null;

        $query = trim($this->crmSearch['customer'] ?? '');
        $crmSlots = [
            'customer' => [
                'options'   => $crmProvider ? $crmProvider->options($query !== '' ? $query : null, 30) : [],
                'label'     => $this->crm_company_id && $crmResolver ? $crmResolver->displayName($this->crm_company_id) : ($this->customer ?: null),
                'url'       => $this->crm_company_id && $crmResolver ? $crmResolver->url($this->crm_company_id) : null,
                'currentId' => $this->crm_company_id,
            ],
        ];

        // Customer-Label pro Event vorberechnen: Wenn crm_company_id gesetzt ist,
        // bevorzugt den CRM-Namen; sonst Legacy-Freitext customer.
        $customerLabels = [];
        foreach ($events as $e) {
            $label = null;
            if ($e->crm_company_id && $crmResolver) {
                $label = $crmResolver->displayName($e->crm_company_id);
            }
            $customerLabels[$e->id] = $label ?: ($e->customer ?: null);
        }

        // Filter-Optionen fuer die Kalender-/Listen-Dropdowns: Distinct-Werte
        // aus dem Team-Bestand. event_type-Vorschlaege kommen aus Settings.
        $filterOptions = [
            'responsibles' => Event::where('team_id', $team->id)
                ->whereNotNull('responsible')->where('responsible', '!=', '')
                ->orderBy('responsible')->distinct()->pluck('responsible')->all(),
            'locations'    => Event::where('team_id', $team->id)
                ->whereNotNull('location')->where('location', '!=', '')
                ->orderBy('location')->distinct()->pluck('location')->all(),
            'eventTypes'   => \Platform\Events\Services\SettingsService::eventTypes($team->id),
        ];

        // Kalender-Dataset: alle Events mit start_date (ohne Pagination), gefiltert
        // nach allen aktiven Filtern. Period wird im Kalender ignoriert — die
        // JS-Komponente navigiert selbst.
        $calendarEvents = [];
        $typeColorMap   = [];
        if ($this->viewMode === 'calendar') {
            $calQuery = Event::query()
                ->where('team_id', $team->id)
                ->whereNotNull('start_date');
            if ($this->search !== '') {
                $q = '%' . $this->search . '%';
                $calQuery->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', $q)
                        ->orWhere('customer', 'like', $q)
                        ->orWhere('event_number', 'like', $q);
                });
            }
            if ($this->statusFilter !== '' && $this->statusFilter !== 'Alle') {
                $calQuery->where('status', $this->statusFilter);
            }
            if ($this->responsibleFilter !== '') {
                $calQuery->where('responsible', $this->responsibleFilter);
            }
            if ($this->eventTypeFilter !== '') {
                $calQuery->where('event_type', $this->eventTypeFilter);
            }
            if ($this->locationFilter !== '') {
                $calQuery->where('location', $this->locationFilter);
            }
            if ($this->highlightsOnly) {
                $calQuery->where('is_highlight', true);
            }
            // Zeitraum-Filter auch im Kalender, sofern explizit gesetzt.
            if ($periodStart) {
                $calQuery->where(function ($q) use ($periodStart, $periodEnd) {
                    $q->where(function ($q2) use ($periodStart, $periodEnd) {
                        $q2->where('start_date', '<=', $periodEnd)
                            ->where(function ($q3) use ($periodStart) {
                                $q3->where('end_date', '>=', $periodStart)->orWhereNull('end_date');
                            });
                    });
                });
            }
            $calendarEvents = $calQuery
                ->orderBy('start_date')
                ->get(['id', 'event_number', 'name', 'customer', 'location', 'status', 'event_type', 'responsible', 'start_date', 'end_date', 'is_highlight'])
                ->map(fn ($e) => [
                    'id'           => $e->id,
                    'event_number' => $e->event_number,
                    'slug'         => $e->slug,
                    'url'          => route('events.show', ['slug' => $e->slug]),
                    'name'         => $e->name,
                    'customer'     => $e->customer,
                    'location'     => $e->location,
                    'status'       => $e->status,
                    'event_type'   => $e->event_type,
                    'responsible'  => $e->responsible,
                    'is_highlight' => (bool) $e->is_highlight,
                    'start_date'   => $e->start_date?->toDateString(),
                    'end_date'     => $e->end_date?->toDateString() ?? $e->start_date?->toDateString(),
                ])
                ->values()
                ->all();

            // Deterministische HSL-Farben pro Event-Typ (auch fuer Typen, die im
            // Bestand vorkommen aber nicht in den Settings sind).
            $allTypes = collect($calendarEvents)->pluck('event_type')->filter()->unique()
                ->merge($filterOptions['eventTypes'])->unique()->values();
            foreach ($allTypes as $t) {
                // Hash → Hue 0..359; Saturation/Lightness fix fuer Lesbarkeit.
                $hue = (int) (hexdec(substr(md5((string) $t), 0, 6)) % 360);
                $typeColorMap[$t] = [
                    'bg'    => 'hsl(' . $hue . ', 65%, 92%)',
                    'color' => 'hsl(' . $hue . ', 55%, 28%)',
                    'bar'   => 'hsl(' . $hue . ', 60%, 50%)',
                ];
            }
        }

        return view('events::livewire.manage', [
            'events'              => $events,
            'stats'               => $stats,
            'periodStart'         => $periodStart,
            'periodEnd'           => $periodEnd,
            'statusOptions'       => self::STATUS_OPTIONS,
            'mrFields'            => $mrFields,
            'crmCompanyAvailable' => $crmCompanyAvailable,
            'crmSlots'            => $crmSlots,
            'customerLabels'      => $customerLabels,
            'revenueByEvent'      => $revenueByEvent,
            'calendarEvents'      => $calendarEvents,
            'filterOptions'       => $filterOptions,
            'typeColorMap'        => $typeColorMap,
        ])->layout('platform::layouts.app');
    }
}
