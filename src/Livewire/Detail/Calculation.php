<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\QuoteItem;

class Calculation extends Component
{
    public int $eventId;

    public array $openKat = [];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function toggleKat(string $key): void
    {
        $this->openKat[$key] = !($this->openKat[$key] ?? false);
    }

    public function render()
    {
        $event = Event::with(['days' => fn ($q) => $q->orderBy('sort_order')])->findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);

        $dayIds = $event->days->pluck('id');
        $quoteItems = QuoteItem::whereIn('event_day_id', $dayIds)->get();
        $orderItems = OrderItem::whereIn('event_day_id', $dayIds)->get();
        $daysById = $event->days->keyBy('id');

        $colorMap = [
            'speisen'   => ['label' => 'Speisen',   'color' => '#16a34a', 'bg' => '#f0fdf4'],
            'getränke'  => ['label' => 'Getränke',  'color' => '#2563eb', 'bg' => '#eff6ff'],
            'personal'  => ['label' => 'Personal',  'color' => '#7c3aed', 'bg' => '#f5f3ff'],
            'equipment' => ['label' => 'Equipment', 'color' => '#d97706', 'bg' => '#fffbeb'],
            'technik'   => ['label' => 'Technik',   'color' => '#0891b2', 'bg' => '#ecfeff'],
            'sonstiges' => ['label' => 'Sonstiges', 'color' => '#475569', 'bg' => '#f1f5f9'],
        ];
        $fallbackColors = ['#16a34a', '#2563eb', '#7c3aed', '#d97706', '#0891b2', '#dc2626', '#475569'];

        $typs = collect($quoteItems->pluck('typ'))
            ->merge($orderItems->pluck('typ'))
            ->filter()
            ->unique()
            ->values();

        $kategorien = [];
        $fbIdx = 0;
        foreach ($typs as $typ) {
            $key = strtolower($typ);
            $meta = $colorMap[$key] ?? null;
            if (!$meta) {
                $color = $fallbackColors[$fbIdx++ % count($fallbackColors)];
                $meta = ['label' => $typ, 'color' => $color, 'bg' => $color . '0F'];
            }

            $rows = [];
            foreach ($event->days as $day) {
                $qi = $quoteItems->first(fn ($i) => $i->event_day_id === $day->id && strcasecmp($i->typ, $typ) === 0);
                $oi = $orderItems->first(fn ($i) => $i->event_day_id === $day->id && strcasecmp($i->typ, $typ) === 0);
                $rev = $qi ? (float) $qi->umsatz : 0.0;
                $cost = $oi ? (float) $oi->einkauf : 0.0;
                if ($rev === 0.0 && $cost === 0.0) continue;
                $rows[] = [
                    'day_label' => $day->label ?? ($day->day_of_week . ' ' . $day->datum?->format('d.m.')),
                    'datum'     => $day->datum?->format('d.m.Y') ?? '—',
                    'articles'  => $qi?->artikel ?? 0,
                    'positionen'=> $qi?->positionen ?? 0,
                    'revenue'   => $rev,
                    'cost'      => $cost,
                    'margin'    => $rev - $cost,
                    'marginPct' => $rev > 0 ? (($rev - $cost) / $rev) * 100 : 0,
                ];
            }
            $umsatz = array_sum(array_column($rows, 'revenue'));
            $ek = array_sum(array_column($rows, 'cost'));
            $db = $umsatz - $ek;
            $kategorien[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'bg'     => $meta['bg'],
                'open'   => $this->openKat[$key] ?? ($key === strtolower((string) $typs->first())),
                'rows'   => $rows,
                'umsatz' => $umsatz,
                'ek'     => $ek,
                'db'     => $db,
                'dbPct'  => $umsatz > 0 ? ($db / $umsatz) * 100 : 0,
            ];
        }

        $gesamtUmsatz = array_sum(array_column($kategorien, 'umsatz'));
        $gesamtEk     = array_sum(array_column($kategorien, 'ek'));
        $gesamtDb     = $gesamtUmsatz - $gesamtEk;
        $gesamtDbPct  = $gesamtUmsatz > 0 ? ($gesamtDb / $gesamtUmsatz) * 100 : 0;

        return view('events::livewire.detail.calculation', [
            'event'        => $event,
            'kategorien'   => $kategorien,
            'gesamtUmsatz' => $gesamtUmsatz,
            'gesamtEk'     => $gesamtEk,
            'gesamtDb'     => $gesamtDb,
            'gesamtDbPct'  => $gesamtDbPct,
        ]);
    }
}
