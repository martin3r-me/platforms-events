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
    protected const KEY_BEVERAGE_MODES  = 'beverage_modes';
    protected const KEY_ORDER_NUMBER_SCHEMA = 'order_number_schema';
    protected const KEY_ATTACH_FLOOR_PLANS_DEFAULT = 'attach_floor_plans_default';

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
            self::KEY_BEVERAGE_MODES  => [
                ['name' => 'Verbrauch',   'hide_unit_price' => false, 'hide_total_price' => false],
                ['name' => 'Alternativ',  'hide_unit_price' => false, 'hide_total_price' => false],
                ['name' => 'Auf Anfrage', 'hide_unit_price' => true,  'hide_total_price' => true],
                ['name' => 'In Pauschale','hide_unit_price' => true,  'hide_total_price' => true],
            ],
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
    /**
     * Liefert die Getraenke-Modi als reine Namen-Liste (String-Array).
     * Fuer abwaertskompatible Konsumenten wie die Modus-Dropdowns im Editor.
     */
    public static function beverageModes(?int $teamId): array
    {
        return array_map(
            static fn (array $m) => $m['name'],
            self::beverageModesFull($teamId)
        );
    }

    /**
     * Liefert die Getraenke-Modi als Vollobjekte
     *   [{ name: string, hide_unit_price: bool, hide_total_price: bool }, ...].
     *
     * Alte String-Eintraege werden beim Lesen normalisiert. Modi, deren Name
     * 'anfrage' enthaelt, bekommen beide Hide-Flags implizit gesetzt (damit
     * vorhandene „Auf Anfrage"-Modi ohne Migration weiterhin korrekt blenden).
     */
    public static function beverageModesFull(?int $teamId): array
    {
        $raw = self::getArray($teamId, self::KEY_BEVERAGE_MODES, self::defaults()[self::KEY_BEVERAGE_MODES]);
        $out = [];
        foreach ($raw as $entry) {
            $normalized = self::normalizeBeverageMode($entry);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }
        return $out;
    }

    /**
     * Wandelt einen Eintrag (String oder Teil-Objekt) in das volle Schema um.
     * Liefert null bei leerem Namen.
     */
    public static function normalizeBeverageMode(mixed $entry): ?array
    {
        if (is_string($entry)) {
            $name = trim($entry);
            if ($name === '') return null;
            $onRequest = self::isOnRequestBeverageMode($name);
            return [
                'name'             => $name,
                'hide_unit_price'  => $onRequest,
                'hide_total_price' => $onRequest,
            ];
        }
        if (is_array($entry)) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') return null;
            return [
                'name'             => $name,
                'hide_unit_price'  => (bool) ($entry['hide_unit_price']  ?? false),
                'hide_total_price' => (bool) ($entry['hide_total_price'] ?? false),
            ];
        }
        return null;
    }

    /**
     * Liefert die Hide-Flags fuer einen konkreten Modus-Namen.
     *   hide_unit_price       → Einzelpreis-Spalte im Angebot leer lassen
     *   hide_total_price      → Gesamtpreis-Spalte im Angebot leer lassen
     *   exclude_from_totals   → wenn beide Hide-Flags gesetzt sind, fliesst
     *                           die Position nicht in die Summen ein (sonst
     *                           gibt es Doppelzaehlung mit einer Pauschale).
     */
    public static function beverageModeFlags(?int $teamId, ?string $mode): array
    {
        $off = ['hide_unit_price' => false, 'hide_total_price' => false, 'exclude_from_totals' => false];
        if ($mode === null || trim($mode) === '') {
            return $off;
        }
        $needle = mb_strtolower(trim($mode));
        foreach (self::beverageModesFull($teamId) as $m) {
            if (mb_strtolower($m['name']) === $needle) {
                $hideUnit  = (bool) $m['hide_unit_price'];
                $hideTotal = (bool) $m['hide_total_price'];
                return [
                    'hide_unit_price'     => $hideUnit,
                    'hide_total_price'    => $hideTotal,
                    'exclude_from_totals' => $hideUnit && $hideTotal,
                ];
            }
        }
        // Modus existiert nicht (mehr) in Settings → Fallback ueber den
        // 'anfrage'-Substring, damit Bestandsdaten weiterhin korrekt blenden.
        if (self::isOnRequestBeverageMode($mode)) {
            return ['hide_unit_price' => true, 'hide_total_price' => true, 'exclude_from_totals' => true];
        }
        return $off;
    }
    public static function bausteine(?int $teamId): array        { return self::getArray($teamId, self::KEY_POSITION_BAUSTEINE, self::defaults()[self::KEY_POSITION_BAUSTEINE]); }

    public static function orderNumberSchema(?int $teamId): string
    {
        $raw = \Platform\Events\Models\Setting::getFor($teamId, self::KEY_ORDER_NUMBER_SCHEMA);
        return is_string($raw) && $raw !== '' ? $raw : \Platform\Events\Services\OrderNumberBuilder::DEFAULT_SCHEMA;
    }

    public static function setOrderNumberSchema(?int $teamId, string $schema): void
    {
        \Platform\Events\Models\Setting::setFor($teamId, self::KEY_ORDER_NUMBER_SCHEMA, trim($schema));
    }

    /**
     * Ob Raum-Grundrisse standardmaessig ans Angebot angehaengt werden sollen.
     * Default: false — der Projektleiter muss es bewusst aktivieren.
     */
    public static function attachFloorPlansDefault(?int $teamId): bool
    {
        $raw = Setting::getFor($teamId, self::KEY_ATTACH_FLOOR_PLANS_DEFAULT);
        if ($raw === null) {
            return false;
        }
        return in_array((string) $raw, ['1', 'true', 'on', 'yes'], true);
    }

    public static function setAttachFloorPlansDefault(?int $teamId, bool $value): void
    {
        Setting::setFor($teamId, self::KEY_ATTACH_FLOOR_PLANS_DEFAULT, $value ? '1' : '0');
    }

    public static function setCostCenters(?int $teamId, array $items): void       { self::setArray($teamId, self::KEY_COST_CENTERS, array_values(array_filter(array_map('trim', $items)))); }
    public static function setCostCarriers(?int $teamId, array $items): void      { self::setArray($teamId, self::KEY_COST_CARRIERS, array_values(array_filter(array_map('trim', $items)))); }
    public static function setQuoteStatuses(?int $teamId, array $items): void     { self::setArray($teamId, self::KEY_QUOTE_STATUSES, array_values(array_filter(array_map('trim', $items)))); }
    public static function setOrderStatuses(?int $teamId, array $items): void     { self::setArray($teamId, self::KEY_ORDER_STATUSES, array_values(array_filter(array_map('trim', $items)))); }
    public static function setEventTypes(?int $teamId, array $items): void        { self::setArray($teamId, self::KEY_EVENT_TYPES, array_values(array_filter(array_map('trim', $items)))); }
    public static function setBestuhlungOptions(?int $teamId, array $items): void { self::setArray($teamId, self::KEY_BESTUHLUNG, array_values(array_filter(array_map('trim', $items)))); }
    public static function setScheduleDescriptions(?int $teamId, array $items): void { self::setArray($teamId, self::KEY_SCHEDULE_DESCRIPTIONS, array_values(array_filter(array_map('trim', $items)))); }
    public static function setDayTypes(?int $teamId, array $items): void          { self::setArray($teamId, self::KEY_DAY_TYPES, array_values(array_filter(array_map('trim', $items)))); }
    /**
     * Akzeptiert sowohl Legacy-String-Arrays als auch Voll-Objekte
     *   [{ name, hide_unit_price, hide_total_price }, ...].
     * Speichert intern immer im Voll-Format, damit die Flags persistent sind.
     */
    public static function setBeverageModes(?int $teamId, array $items): void
    {
        $normalized = [];
        foreach ($items as $entry) {
            $m = self::normalizeBeverageMode($entry);
            if ($m !== null) {
                $normalized[] = $m;
            }
        }
        self::setArray($teamId, self::KEY_BEVERAGE_MODES, $normalized);
    }
    public static function setBausteine(?int $teamId, array $items): void         { self::setArray($teamId, self::KEY_POSITION_BAUSTEINE, $items); }

    /**
     * Erkennt einen "auf Anfrage"-Modus an einem freitextlichen Modus-String.
     * Wir matchen tolerant (lowercase + 'anfrage'-Substring), damit auch
     * "Preis auf Anfrage", "auf_anfrage" o. ae. greifen.
     */
    public static function isOnRequestBeverageMode(?string $mode): bool
    {
        if ($mode === null || $mode === '') return false;
        return str_contains(mb_strtolower(trim($mode)), 'anfrage');
    }
}
