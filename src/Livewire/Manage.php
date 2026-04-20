<?php

namespace Platform\Events\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventDay;

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

    // Create-Modal
    public bool $showCreateModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public ?string $customer = null;

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
        $this->reset(['name', 'customer', 'start_date', 'end_date']);
        $this->status = 'Option';
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $data = $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        // event_number per Team generieren: VA#YYYY-MMx
        $prefix = 'VA#' . now()->year . '-' . now()->format('m');
        $last = Event::where('team_id', $team->id)
            ->where('event_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(event_number) DESC, event_number DESC')
            ->value('event_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        $event = Event::create([
            'team_id'            => $team->id,
            'user_id'            => $user->id,
            'event_number'       => $prefix . $next,
            'name'               => $data['name'],
            'organizer_for_whom' => $data['name'],
            'customer'           => $data['customer'] ?? null,
            'start_date'         => $data['start_date'],
            'end_date'           => $data['end_date'] ?? null,
            'status'             => $data['status'] ?? 'Option',
            'status_changed_at'  => now(),
        ]);

        // EventDays für Datumsbereich anlegen (max. 365 Tage)
        $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        try {
            $start = Carbon::parse($data['start_date']);
            $end = !empty($data['end_date']) ? Carbon::parse($data['end_date']) : $start->copy();
            $sort = 0;
            $maxDays = min((int) $start->diffInDays($end) + 1, 365);

            for ($dt = $start->copy(); $dt->lte($end) && $sort < $maxDays; $dt->addDay()) {
                EventDay::create([
                    'team_id'     => $team->id,
                    'user_id'     => $user->id,
                    'event_id'    => $event->id,
                    'label'       => $dt->format('d.m.Y'),
                    'datum'       => $dt->format('Y-m-d'),
                    'day_of_week' => $weekdays[$dt->dayOfWeek],
                    'day_status'  => $data['status'] ?? 'Option',
                    'color'       => '#6366f1',
                    'sort_order'  => $sort++,
                ]);
            }
        } catch (\Throwable $e) {
            // Startdatum ungültig – Event bleibt ohne Tage
        }

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

        $today = now()->toDateString();

        $events = $query
            ->orderByRaw("CASE WHEN start_date >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('start_date')
            ->paginate(50);

        $stats = [
            'total'    => Event::where('team_id', $team->id)->count(),
            'upcoming' => Event::where('team_id', $team->id)
                ->where(function ($q) use ($today) {
                    $q->where('end_date', '>=', $today)->orWhereNull('end_date');
                })->count(),
            'past'     => Event::where('team_id', $team->id)->where('end_date', '<', $today)->count(),
        ];

        return view('events::livewire.manage', [
            'events'         => $events,
            'stats'          => $stats,
            'periodStart'    => $periodStart,
            'periodEnd'      => $periodEnd,
            'statusOptions'  => self::STATUS_OPTIONS,
        ])->layout('platform::layouts.app');
    }
}
