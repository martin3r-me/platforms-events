# Events Module

Veranstaltungs-Verwaltung für die Platform: Events mit Tagen, Raum-Buchungen, Ablaufplan, Notizen und Management-Report.

## Features

- **Event-Stammdaten** – Name, Kunde, Zeitraum, Status, Veranstalter, Besteller, Rechnung, Zuständigkeit, Unterschriften, Eingang, Wiedervorlage, Lieferung, Weiterleitung
- **Event-Tage** – Tagesstruktur mit Datum, Zeiten, Personenbereich, Tagesstatus und Farbe
- **Raum-Buchungen** – Verknüpfung zum `Locations`-Modul (FK `location_id`); Freitext-`raum` bleibt als Legacy-Fallback
- **Location-Preise einbuchen** – Button im Vorgangs-Header zieht Mietpreise (passender Tag-Typ vorausgewählt) und optionale Add-ons (Heizung etc.) der gebuchten Location als QuotePositions in den Vorgang. Idempotent über Audit-Trail `events_location_pricing_applications` (Service `LocationPricingApplicator`).
- **Ablaufplan** – Zeitleiste mit Datum, Von/Bis, Beschreibung, Raum, Bemerkung
- **Notizen** – kategorisiert als Liefertext, Absprache oder Vereinbarung
- **Management Report** – frei erweiterbares JSON mit 12 hardcoded Default-Feldern in vier Gruppen (Logistik/Produktion/Rechnungen/Controlling)
- **CRUD** via Livewire 3 + Alpine.js (Modal-basiert)
- **AI-Tools** – 28 Tools über `Platform\Core\Tools\ToolRegistry` (Events volle Breite inkl. Bulk, Sub-Entities als Single-CUD)
- **Team-Scoping** – alle Daten an `currentTeam` gebunden
- **UUIDs** – `UuidV7` auf allen Models
- **event_number** – automatisch pro Team generiert (`VA#YYYY-MMx`, z.B. `VA#2026-041`)

## Installation

```bash
composer require martin3r/platforms-events
php artisan migrate
```

Der Service-Provider `Platform\Events\EventsServiceProvider` wird automatisch via `extra.laravel.providers` in `composer.json` registriert.

**Voraussetzung:** Das `platforms-locations`-Modul muss installiert sein (FK `events_bookings.location_id` → `locations_locations.id`).

## Struktur

```
modules/platforms-events/
├── config/
│   └── events.php                 # Routing, Navigation, Sidebar
├── database/
│   └── migrations/                # 5 Tabellen mit events_-Prefix
├── resources/
│   └── views/
│       └── livewire/              # Blade Views (events::livewire.*)
├── routes/
│   └── web.php                    # Module-Routes
└── src/
    ├── Livewire/
    │   ├── Dashboard.php
    │   ├── Manage.php             # Event-Liste mit Filter und Create-Modal
    │   ├── Detail.php             # Event-Detail mit 6 Tabs
    │   └── Sidebar.php
    ├── Models/
    │   ├── Event.php
    │   ├── EventDay.php
    │   ├── Booking.php
    │   ├── ScheduleItem.php
    │   └── EventNote.php
    ├── Tools/                     # 28 AI-Tools (ToolRegistry)
    │   ├── Concerns/ResolvesEvent.php
    │   ├── List|Get|Create|Update|Delete|BulkCreate|BulkUpdate|BulkDelete EventsTool.php
    │   ├── List|Get|Create|Update|Delete EventDaysTool.php
    │   ├── List|Get|Create|Update|Delete BookingsTool.php
    │   ├── List|Get|Create|Update|Delete ScheduleItemsTool.php
    │   └── List|Get|Create|Update|Delete EventNotesTool.php
    └── EventsServiceProvider.php
```

## Routen

| Route                        | Name                  | Beschreibung                          |
| ---------------------------- | --------------------- | ------------------------------------- |
| `GET /events`                | `events.dashboard`    | Dashboard mit Counts                  |
| `GET /events/liste`          | `events.manage`       | Event-Liste mit Filter + Create-Modal |
| `GET /events/va/{slug}`      | `events.show`         | Event-Detail mit Tabs                 |

`slug` ist die `event_number` ohne `#` (z.B. `VA2026-041`). Der URL-Prefix `events` kommt aus `config/events.php` (`routing.prefix`) und lässt sich via `EVENTS_MODE`-Env umschalten.

## Datenmodell

### `Platform\Events\Models\Event`

Tabelle `events_events`. Kernfelder (Auszug – alle Felder in der Migration):

| Feld                          | Typ      | Hinweis                                                         |
| ----------------------------- | -------- | --------------------------------------------------------------- |
| `id` / `uuid`                 | pk/uuid  | UUID via `UuidV7`                                               |
| `user_id` / `team_id`         | fk       | Ersteller / Owning-Team (nullable)                              |
| `event_number`                | string   | `VA#YYYY-MMx`, eindeutig                                        |
| `name`                        | string   | Pflicht                                                         |
| `customer`, `group`, `location` | string | Freitext (customer, optionale Gruppierung, Ort als Legacy-Feld) |
| `start_date`, `end_date`      | date     | Zeitraum der Veranstaltung                                      |
| `status`                      | string   | Option / Definitiv / Vertrag / Abgeschlossen / Storno / …       |
| `status_changed_at`           | datetime | Wird beim Status-Wechsel automatisch gesetzt                    |
| `organizer_*`, `orderer_*`, `invoice_*`, `delivery_*`, `inquiry_*`, `follow_up_*`, `forwarding_*`, `forwarded` | diverse | Vollständiger Feldsatz aus dem Alt-System; CRM-Anbindung folgt |
| `responsible`, `cost_center`, `cost_carrier`, `event_type` | string | Zuständigkeit/Anlass |
| `sign_left`, `sign_right`     | string   | Unterschriften                                                  |
| `mr_data`                     | json     | Management-Report als Key/Value-Map                             |
| `timestamps`, `deleted_at`    | —        | created_at/updated_at + SoftDeletes                             |

### `Platform\Events\Models\EventDay`

Tabelle `events_event_days`. Felder: `label`, `datum`, `day_of_week`, `von`, `bis`, `pers_von`, `pers_bis`, `day_status`, `color`, `sort_order`.

### `Platform\Events\Models\Booking`

Tabelle `events_bookings`. Felder: `location_id` (FK `locations_locations.id`, nullable), `raum` (Legacy-Fallback-String), `datum`, `beginn`, `ende`, `pers`, `bestuhlung`, `optionsrang`, `absprache`, `sort_order`. Relation `location()` → `Platform\Locations\Models\Location`.

### `Platform\Events\Models\ScheduleItem`

Tabelle `events_schedule_items`. Felder: `datum`, `von`, `bis`, `beschreibung`, `raum`, `bemerkung`, `linked`, `sort_order`.

### `Platform\Events\Models\EventNote`

Tabelle `events_event_notes`. Felder: `type` (`liefertext` | `absprache` | `vereinbarung`), `text`, `user_name`.

## Konventionen

- **Layout:** `->layout('platform::layouts.app')`
- **Views:** `view('events::livewire.<name>')`
- **Livewire-Alias-Prefix:** `events.*` (automatisch via ServiceProvider)
- **Team-Zugriff:** immer `$user->currentTeam->id`, Queries mit `->where('team_id', …)` scopen
- **UI-Komponenten:** `x-ui-page`, `x-ui-panel`, `x-ui-button`, `x-ui-dashboard-tile`, `x-ui-page-navbar`, `x-ui-page-container`, `x-ui-page-sidebar`, `x-ui-modal`, `x-ui-segmented-toggle`
- **event_number** wird in `Manage` und `CreateEventTool` pro Team inkrementiert

## AI-Tools

28 Tools, automatisch in `Platform\Core\Tools\ToolRegistry` registriert.

### Events (Single + Bulk)

| Tool                          | Zweck                                                         |
| ----------------------------- | ------------------------------------------------------------- |
| `events.events.GET`           | Events des aktuellen Teams listen                             |
| `events.event.GET`            | Detail zu einem Event (optional inkl. Tage/Buchungen/Ablauf/Notizen) |
| `events.events.POST`          | Event anlegen (+ optional EventDays für Datumsbereich)        |
| `events.events.PATCH`         | Event aktualisieren                                           |
| `events.events.DELETE`        | Event löschen (Soft Delete)                                   |
| `events.events.bulk.POST`     | Mehrere Events anlegen (atomic default)                       |
| `events.events.bulk.PATCH`    | Mehrere Events aktualisieren (2 Modi: ids+data ODER updates[]) |
| `events.events.bulk.DELETE`   | Mehrere Events löschen                                        |

### Sub-Entities (je List/Get/Create/Update/Delete)

| Entity            | Tool-Prefix                   |
| ----------------- | ----------------------------- |
| Event-Tage        | `events.days.*` / `events.day.GET` |
| Raum-Buchungen    | `events.bookings.*` / `events.booking.GET` |
| Ablaufplan        | `events.schedule-items.*` / `events.schedule-item.GET` |
| Notizen           | `events.notes.*` / `events.note.GET` |

Sub-Entity-Create/List verlangen einen Event-Selector (`event_id` ODER `event_uuid` ODER `event_number`). Get/Update/Delete arbeiten direkt auf dem Sub-Datensatz (`*_id` ODER `uuid`).

Alle Schreib-Tools verlangen `team_id` (oder übernehmen das aktuelle Team aus dem `ToolContext`).

## Management Report

`mr_data` ist ein JSON-Feld am Event, das frei beschrieben werden kann. Für die UI sind 12 Default-Felder hardcoded in `Detail::mrDefaults()` (Gruppen: Logistik & Personal, Produktion, Rechnungen, Controlling). Eine Konfigurierbarkeit analog zum Alt-System (`MrFieldConfig`) wird später als Ausbaustufe ergänzt.

## Herkunft

Abgeleitet aus dem internen `event-modul`. Beim Port auf Platform-Konventionen umgestellt:

- Models `Event`/`EventDay`/`Room`/`ScheduleItem`/`EventNote` → `Event`/`EventDay`/`Booking`/`ScheduleItem`/`EventNote`
- Tabellen mit Modul-Prefix (`events_*`)
- UUID + team_id + user_id + SoftDeletes ergänzt
- `Room.raum` (String-Kürzel) → `Booking.location_id` (FK auf Locations-Modul); Legacy-`raum` bleibt als Fallback
- Classic Controller/Blade + Alpine-Fetch → Livewire 3
- MrFieldConfig/DocTemplates/Contracts/Quotes/Invoices/PickLists/Activities/Audit bleiben (zunächst) aussen vor – werden in eigenen Modulen folgen

## Lizenz

MIT
