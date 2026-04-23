<?php

namespace Platform\Events\Services;

use Carbon\Carbon;
use Platform\Core\Models\User;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventDay;

/**
 * Kapselt die Erstellung eines Events: Nummer-Vergabe, sinnvolle Defaults
 * (Verantwortlicher/Unterschrift = erstellender User) und automatische
 * EventDays fuer den Datumszeitraum.
 *
 * Explizit uebergebene Felder werden NICHT ueberschrieben – so lassen sich
 * die Defaults (z.B. bei Veranstaltungs-Abgabe) problemlos manuell setzen.
 */
class EventFactory
{
    public const MAX_DAYS = 365;

    /**
     * Erstellt ein neues Event inkl. EventDays.
     *
     * @param array{
     *     name: string,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     status?: string|null,
     *     customer?: string|null,
     *     organizer_for_whom?: string|null,
     *     responsible?: string|null,
     *     sign_left?: string|null,
     *     sign_right?: string|null,
     * }|array<string,mixed> $data Weitere fillable-Felder werden durchgereicht.
     * @param bool $createDays EventDays fuer Zeitraum anlegen
     */
    public static function create(User $user, int $teamId, array $data, bool $createDays = true): Event
    {
        $status = $data['status'] ?? 'Option';

        $payload = array_merge($data, [
            'team_id'           => $teamId,
            'user_id'           => $user->id,
            'event_number'      => self::nextEventNumber($teamId),
            'status'            => $status,
            'status_changed_at' => now(),
            'organizer_for_whom' => $data['organizer_for_whom'] ?? ($data['name'] ?? null),
        ]);

        $userName = trim((string) $user->name);
        if ($userName !== '') {
            $payload['responsible'] = $payload['responsible'] ?? $userName;
            $payload['sign_left']   = $payload['sign_left']   ?? $userName;
        }

        $event = Event::create($payload);

        if ($createDays && !empty($payload['start_date'])) {
            self::createDaysForRange($event, $user->id, $teamId, $payload['start_date'], $payload['end_date'] ?? null, $status);
        }

        return $event;
    }

    /**
     * Naechste Event-Nummer fuer das Team im Format VA#YYYY-MMx.
     */
    public static function nextEventNumber(int $teamId): string
    {
        $prefix = 'VA#' . now()->year . '-' . now()->format('m');
        $last = Event::where('team_id', $teamId)
            ->where('event_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(event_number) DESC, event_number DESC')
            ->value('event_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . $next;
    }

    /**
     * Erzeugt EventDays fuer jeden Tag im Zeitraum (max. MAX_DAYS).
     */
    public static function createDaysForRange(Event $event, int $userId, int $teamId, string $startDate, ?string $endDate, string $status): void
    {
        $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

        try {
            $start = Carbon::parse($startDate);
            $end   = !empty($endDate) ? Carbon::parse($endDate) : $start->copy();
            $maxDays = min((int) $start->diffInDays($end) + 1, self::MAX_DAYS);
            $sort = 0;

            for ($dt = $start->copy(); $dt->lte($end) && $sort < $maxDays; $dt->addDay()) {
                EventDay::create([
                    'team_id'     => $teamId,
                    'user_id'     => $userId,
                    'event_id'    => $event->id,
                    'label'       => $dt->format('d.m.Y'),
                    'datum'       => $dt->format('Y-m-d'),
                    'day_of_week' => $weekdays[$dt->dayOfWeek],
                    'day_status'  => $status,
                    'color'       => '#6366f1',
                    'sort_order'  => $sort++,
                ]);
            }
        } catch (\Throwable $e) {
            // Startdatum ungueltig – Event bleibt ohne Tage
        }
    }
}
