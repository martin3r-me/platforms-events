<?php

namespace Platform\Events\Services;

use Platform\Events\Models\Event;

/**
 * Baut die Ordernummer eines Events aus einem konfigurierbaren Schema.
 * Unterstuetzte Platzhalter:
 *   {USER_INITIALS}  - Initialen des Verantwortlichen (event.responsible)
 *   {COST_CENTER}    - event.cost_center
 *   {COST_CARRIER}   - event.cost_carrier, Fallback event.event_number
 *   {EVENT_NUMBER}   - event.event_number
 *   {YEAR}           - Jahr des start_date, sonst aktuelles Jahr
 */
class OrderNumberBuilder
{
    public const DEFAULT_SCHEMA = '{USER_INITIALS}-{COST_CENTER}-{COST_CARRIER}';

    public const PLACEHOLDERS = [
        '{USER_INITIALS}' => 'Initialen des Verantwortlichen (z.B. "CW")',
        '{COST_CENTER}'   => 'Kostenstelle',
        '{COST_CARRIER}'  => 'Kostenträger (fällt auf Event-Nummer zurück)',
        '{EVENT_NUMBER}'  => 'Event-Nummer (z.B. "VA#2026-044")',
        '{YEAR}'          => 'Jahr (aus Startdatum oder aktuelles)',
    ];

    public static function build(Event $event, ?string $schema = null): string
    {
        $schema = $schema !== null && $schema !== '' ? $schema : self::DEFAULT_SCHEMA;

        $year = $event->start_date
            ? (string) $event->start_date->format('Y')
            : (string) now()->format('Y');

        $replacements = [
            '{USER_INITIALS}' => self::initials((string) ($event->responsible ?? '')),
            '{COST_CENTER}'   => trim((string) ($event->cost_center ?? '')),
            '{COST_CARRIER}'  => trim((string) ($event->cost_carrier ?? '')) !== ''
                ? trim((string) $event->cost_carrier)
                : (string) ($event->event_number ?? ''),
            '{EVENT_NUMBER}'  => (string) ($event->event_number ?? ''),
            '{YEAR}'          => $year,
        ];

        return strtr($schema, $replacements);
    }

    public static function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';

        $parts = preg_split('/\s+/u', $name) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
        if (empty($parts)) return '';

        $first = mb_substr($parts[0], 0, 1);
        $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first . $last);
    }
}
