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

        // default_pax (oder pax) ist KEIN Event-Feld – wir extrahieren es vor dem
        // Event::create und propagieren es spaeter auf die generierten Tage.
        $defaultPax = null;
        foreach (['default_pax', 'pax', 'pers', 'pers_von'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                $defaultPax = $data[$k];
                unset($data[$k]);
                break;
            }
        }
        // Restliche pax-Aliase einfach abraeumen, damit sie nicht in Event::create landen.
        foreach (['default_pax', 'pax', 'pers', 'pers_von', 'pers_bis'] as $k) {
            unset($data[$k]);
        }

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

        // Eingang (inquiry) automatisch auf Erstellzeitpunkt, bleibt editierbar.
        $payload['inquiry_date'] = $payload['inquiry_date'] ?? now()->toDateString();
        $payload['inquiry_time'] = $payload['inquiry_time'] ?? now()->format('H:i');

        $event = Event::create($payload);

        // Kostentraeger mit Event-Nummer vorbelegen, wenn nichts anderes gesetzt wurde.
        if (empty($event->cost_carrier) && !empty($event->event_number)) {
            $event->cost_carrier = $event->event_number;
            $event->save();
        }

        if ($createDays && !empty($payload['start_date'])) {
            self::createDaysForRange(
                $event,
                $user->id,
                $teamId,
                $payload['start_date'],
                $payload['end_date'] ?? null,
                $status,
                $defaultPax,
            );
        }

        // Rechnungsdatum (invoice_date_type haelt im UI-Schema das konkrete Datum
        // aus dem EventDay-Dropdown) standardmaessig auf den ersten Tag setzen.
        // Bleibt manuell editierbar – nur Default fuer den initialen Anlage-Schritt.
        if (empty($event->invoice_date_type)) {
            $firstDayDate = self::firstDayDateString($event);
            if ($firstDayDate !== null) {
                $event->invoice_date_type = $firstDayDate;
                $event->save();
            }
        }

        return $event;
    }

    /**
     * Liefert das Y-m-d-Datum des ersten EventDays bzw. start_date als Fallback.
     */
    public static function firstDayDateString(Event $event): ?string
    {
        $firstDay = $event->days()->orderBy('sort_order')->orderBy('datum')->first();
        if ($firstDay && $firstDay->datum) {
            return $firstDay->datum instanceof Carbon
                ? $firstDay->datum->format('Y-m-d')
                : (string) $firstDay->datum;
        }
        if ($event->start_date) {
            return $event->start_date instanceof Carbon
                ? $event->start_date->format('Y-m-d')
                : (string) $event->start_date;
        }
        return null;
    }

    /**
     * Naechste Event-Nummer fuer das Team im Format VA#YYYY-MMx.
     * Bezieht auch soft-deleted Events ein, damit die DB-Unique-Constraint
     * auf event_number (die deleted_at nicht kennt) nicht verletzt wird.
     */
    public static function nextEventNumber(int $teamId): string
    {
        $prefix = 'VA#' . now()->year . '-' . now()->format('m');
        $last = Event::withTrashed()
            ->where('team_id', $teamId)
            ->where('event_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(event_number) DESC, event_number DESC')
            ->value('event_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . $next;
    }

    /**
     * Erzeugt EventDays fuer jeden Tag im Zeitraum (max. MAX_DAYS).
     * Wenn $defaultPax gesetzt ist, wird der Wert in pers_von UND pers_bis
     * an jedem neu angelegten Tag eingetragen.
     */
    public static function createDaysForRange(Event $event, int $userId, int $teamId, string $startDate, ?string $endDate, string $status, mixed $defaultPax = null): void
    {
        $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $pax = ($defaultPax === null || $defaultPax === '') ? null : (string) $defaultPax;

        try {
            $start = Carbon::parse($startDate);
            $end   = !empty($endDate) ? Carbon::parse($endDate) : $start->copy();
            $maxDays = min((int) $start->diffInDays($end) + 1, self::MAX_DAYS);
            $sort = 0;

            // Bereits belegte Daten pro Event einmal einsammeln, um Duplikate zu vermeiden.
            $existing = EventDay::where('event_id', $event->id)->pluck('datum')
                ->map(fn ($d) => $d instanceof \Carbon\Carbon ? $d->format('Y-m-d') : (string) $d)
                ->all();

            for ($dt = $start->copy(); $dt->lte($end) && $sort < $maxDays; $dt->addDay()) {
                $datum = $dt->format('Y-m-d');
                if (in_array($datum, $existing, true)) {
                    continue;
                }
                $existing[] = $datum;

                EventDay::create([
                    'team_id'     => $teamId,
                    'user_id'     => $userId,
                    'event_id'    => $event->id,
                    'label'       => $dt->format('d.m.Y'),
                    'datum'       => $datum,
                    'day_of_week' => $weekdays[$dt->dayOfWeek],
                    'day_status'  => $status,
                    'color'       => '#6366f1',
                    'pers_von'    => $pax,
                    'pers_bis'    => $pax,
                    'sort_order'  => $sort++,
                ]);
            }
        } catch (\Throwable $e) {
            // Startdatum ungueltig – Event bleibt ohne Tage
        }
    }
}
