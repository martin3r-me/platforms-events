<?php

namespace Platform\Events\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Platform\Events\Models\Booking;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\ScheduleItem;

/**
 * Verschiebt eine Veranstaltung als Ganzes auf ein neues Startdatum.
 *
 * Verschoben wird in einer Transaction:
 *   - Event.start_date / end_date (Differenz bleibt erhalten)
 *   - EventDays.datum + label (dd.mm.yyyy) + day_of_week (Mo/Di/...)
 *   - Bookings.datum
 *   - ScheduleItems.datum
 *
 * Uhrzeiten/Personenzahlen/Raumzuordnungen werden NICHT angefasst — die wandern
 * mit ihrem Tag mit, weil sie an EventDay/Booking/ScheduleItem haengen. Ebenso
 * bleiben QuoteItems/OrderItems unangetastet, weil sie ueber event_day_id auf
 * den verschobenen EventDay zeigen.
 */
class EventMover
{
    /** Wochentags-Kuerzel passend zum bestehenden EventFactory-Mapping. */
    private const WEEKDAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    /**
     * @return array{offset_days:int,affected_event_days:int,affected_bookings:int,affected_schedule_items:int}
     */
    public static function move(Event $event, string $newStartDate): array
    {
        if (!$event->start_date) {
            return self::empty();
        }

        try {
            $newStart = Carbon::createFromFormat('Y-m-d', $newStartDate)->startOfDay();
        } catch (\Throwable $e) {
            return self::empty();
        }

        $oldStart = $event->start_date->copy()->startOfDay();
        $offsetDays = (int) $oldStart->diffInDays($newStart, false);
        if ($offsetDays === 0) {
            return self::empty();
        }

        $eventDaysAffected     = 0;
        $bookingsAffected      = 0;
        $scheduleItemsAffected = 0;

        DB::transaction(function () use ($event, $offsetDays, &$eventDaysAffected, &$bookingsAffected, &$scheduleItemsAffected) {
            // Event selbst
            $newStart = $event->start_date->copy()->addDays($offsetDays);
            $newEnd   = $event->end_date
                ? $event->end_date->copy()->addDays($offsetDays)
                : $newStart->copy();
            $event->start_date = $newStart->toDateString();
            $event->end_date   = $newEnd->toDateString();
            $event->save();

            // EventDays — Datum, Label (d.m.Y) und Wochentag neu setzen.
            foreach (EventDay::where('event_id', $event->id)->get() as $day) {
                if (!$day->datum) continue;
                $newDatum = Carbon::parse($day->datum)->addDays($offsetDays);
                $day->datum       = $newDatum->toDateString();
                $day->day_of_week = self::WEEKDAYS[$newDatum->dayOfWeek];
                // Nur Default-Labels (Datum im d.m.Y-Format) neu generieren —
                // benutzerdefinierte Labels wie „Aufbautag" bleiben unangetastet.
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', (string) $day->label)) {
                    $day->label = $newDatum->format('d.m.Y');
                }
                $day->save();
                $eventDaysAffected++;
            }

            // Bookings — nur Datum.
            foreach (Booking::where('event_id', $event->id)->get() as $b) {
                if (!$b->datum) continue;
                $b->datum = Carbon::parse($b->datum)->addDays($offsetDays)->toDateString();
                $b->save();
                $bookingsAffected++;
            }

            // ScheduleItems — nur Datum.
            foreach (ScheduleItem::where('event_id', $event->id)->get() as $s) {
                if (!$s->datum) continue;
                $s->datum = Carbon::parse($s->datum)->addDays($offsetDays)->toDateString();
                $s->save();
                $scheduleItemsAffected++;
            }
        });

        return [
            'offset_days'             => $offsetDays,
            'affected_event_days'     => $eventDaysAffected,
            'affected_bookings'       => $bookingsAffected,
            'affected_schedule_items' => $scheduleItemsAffected,
        ];
    }

    private static function empty(): array
    {
        return [
            'offset_days'             => 0,
            'affected_event_days'     => 0,
            'affected_bookings'       => 0,
            'affected_schedule_items' => 0,
        ];
    }
}
