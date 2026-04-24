<?php

namespace Platform\Events\Services;

use Platform\Events\Models\Setting;

/**
 * Team-scoped Key/Value-Settings mit JSON-Array-Helper.
 *
 * Jeder Wert liegt als JSON-kodierter String in events_settings.value.
 * Wenn fuer ein Team nichts konfiguriert ist, wird der Default zurueckgegeben.
 */
class SettingsService
{
    protected const KEY_COST_CENTERS    = 'cost_centers';
    protected const KEY_COST_CARRIERS   = 'cost_carriers';
    protected const KEY_QUOTE_STATUSES  = 'quote_statuses';
    protected const KEY_ORDER_STATUSES  = 'order_statuses';
    protected const KEY_EVENT_TYPES     = 'event_types';
    protected const KEY_BESTUHLUNG      = 'bestuhlung_options';
    protected const KEY_POSITION_BAUSTEINE = 'position_bausteine';
    protected const KEY_SCHEDULE_DESCRIPTIONS = 'schedule_descriptions';
    protected const KEY_DAY_TYPES       = 'day_types';

    public static function defaults(): array
    {
        return [
            self::KEY_COST_CENTERS    => [],
            self::KEY_COST_CARRIERS   => [],
            self::KEY_QUOTE_STATUSES  => ['Entwurf', 'Versandt', 'Rückfrage', 'Bestätigt', 'Abgelehnt'],
            self::KEY_ORDER_STATUSES  => ['Offen', 'In Arbeit', 'Fertig', 'Ausgeliefert', 'Abgerechnet'],
            self::KEY_EVENT_TYPES     => ['Tagung', 'Gala', 'Hochzeit', 'Empfang', 'Teamevent', 'Messe', 'Sonstiges'],
            self::KEY_BESTUHLUNG      => ['Reihen', 'Bankett', 'U-Form', 'Block', 'Stehtische', 'Parlamentarisch', 'Classroom'],
            self::KEY_SCHEDULE_DESCRIPTIONS => ['Aufbau', 'Anlieferung', 'Empfang', 'Begrüßung', 'Vortrag', 'Pause', 'Dinner', 'Abbau'],
            self::KEY_DAY_TYPES       => ['Veranstaltungstag', 'Aufbautag', 'Abbautag', 'Rüsttag'],
            self::KEY_POSITION_BAUSTEINE => [
                ['name' => 'Headline',     'bg' => '#d1fae5', 'text' => '#065f46'],
                ['name' => 'Trenntext',    'bg' => '#f8fafc', 'text' => '#64748b'],
                ['name' => 'Speisentexte', 'bg' => '#fffbeb', 'text' => '#92400e'],
            ],
        ];
    }

    public static function getArray(?int $teamId, string $key, array $default = []): array
    {
        $raw = Setting::getFor($teamId, $key);
        if (!$raw) {
            return $default;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public static function setArray(?int $teamId, string $key, array $items): void
    {
        Setting::setFor($teamId, $key, json_encode(array_values($items), JSON_UNESCAPED_UNICODE));
    }

    // Typed accessors
    public static function costCenters(?int $teamId): array      { return self::getArray($teamId, self::KEY_COST_CENTERS,    self::defaults()[self::KEY_COST_CENTERS]); }
    public static function costCarriers(?int $teamId): array     { return self::getArray($teamId, self::KEY_COST_CARRIERS,   self::defaults()[self::KEY_COST_CARRIERS]); }
    public static function quoteStatuses(?int $teamId): array    { return self::getArray($teamId, self::KEY_QUOTE_STATUSES,  self::defaults()[self::KEY_QUOTE_STATUSES]); }
    public static function orderStatuses(?int $teamId): array    { return self::getArray($teamId, self::KEY_ORDER_STATUSES,  self::defaults()[self::KEY_ORDER_STATUSES]); }
    public static function eventTypes(?int $teamId): array       { return self::getArray($teamId, self::KEY_EVENT_TYPES,     self::defaults()[self::KEY_EVENT_TYPES]); }
    public static function bestuhlungOptions(?int $teamId): array{ return self::getArray($teamId, self::KEY_BESTUHLUNG,      self::defaults()[self::KEY_BESTUHLUNG]); }
    public static function scheduleDescriptions(?int $teamId): array { return self::getArray($teamId, self::KEY_SCHEDULE_DESCRIPTIONS, self::defaults()[self::KEY_SCHEDULE_DESCRIPTIONS]); }
    public static function dayTypes(?int $teamId): array         { return self::getArray($teamId, self::KEY_DAY_TYPES,       self::defaults()[self::KEY_DAY_TYPES]); }
    public static function bausteine(?int $teamId): array        { return self::getArray($teamId, self::KEY_POSITION_BAUSTEINE, self::defaults()[self::KEY_POSITION_BAUSTEINE]); }

    public static function setCostCenters(?int $teamId, array $items): void       { self::setArray($teamId, self::KEY_COST_CENTERS, array_values(array_filter(array_map('trim', $items)))); }
    public static function setCostCarriers(?int $teamId, array $items): void      { self::setArray($teamId, self::KEY_COST_CARRIERS, array_values(array_filter(array_map('trim', $items)))); }
    public static function setQuoteStatuses(?int $teamId, array $items): void     { self::setArray($teamId, self::KEY_QUOTE_STATUSES, array_values(array_filter(array_map('trim', $items)))); }
    public static function setOrderStatuses(?int $teamId, array $items): void     { self::setArray($teamId, self::KEY_ORDER_STATUSES, array_values(array_filter(array_map('trim', $items)))); }
    public static function setEventTypes(?int $teamId, array $items): void        { self::setArray($teamId, self::KEY_EVENT_TYPES, array_values(array_filter(array_map('trim', $items)))); }
    public static function setBestuhlungOptions(?int $teamId, array $items): void { self::setArray($teamId, self::KEY_BESTUHLUNG, array_values(array_filter(array_map('trim', $items)))); }
    public static function setScheduleDescriptions(?int $teamId, array $items): void { self::setArray($teamId, self::KEY_SCHEDULE_DESCRIPTIONS, array_values(array_filter(array_map('trim', $items)))); }
    public static function setDayTypes(?int $teamId, array $items): void          { self::setArray($teamId, self::KEY_DAY_TYPES, array_values(array_filter(array_map('trim', $items)))); }
    public static function setBausteine(?int $teamId, array $items): void         { self::setArray($teamId, self::KEY_POSITION_BAUSTEINE, $items); }
}
