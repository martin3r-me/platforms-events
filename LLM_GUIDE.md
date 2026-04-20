# Events Module - LLM Guide

## Overview
- **Namespace**: `Platform\Events`
- **Module Key**: `events`
- **Service Provider**: `EventsServiceProvider`
- **Config**: `config/events.php`
- **Views**: `events::livewire.*`
- **Livewire Prefix**: `events.*`

## Architecture
- **ServiceProvider** registriert Config, Modul, Routes, Views, Livewire, Migrations und AI-Tools
- **Models** in `src/Models/` (Event, EventDay, Booking, ScheduleItem, EventNote)
- **Livewire Components** in `src/Livewire/` (Dashboard, Manage, Detail, Sidebar)
- **Views** in `resources/views/livewire/`
- **Routes** in `routes/web.php`
- **Migrations** in `database/migrations/`
- **AI-Tools** in `src/Tools/`

## Models

### `Platform\Events\Models\Event` (Tabelle `events_events`)
- UUID + `team_id` + `user_id` + SoftDeletes
- `event_number` (unique, Format `VA#YYYY-MMx`) – pro Team inkrementiert
- Slug-Accessor: `$event->slug` = `event_number` ohne `#` (Route-Binding)
- Casts: `start_date`/`end_date`/`follow_up_date`/`inquiry_date`/`forwarding_date` = date; `status_changed_at` = datetime; `mr_data` = array; `forwarded` = boolean
- Relations: `days()`, `bookings()`, `scheduleItems()`, `notes()`, `user()`, `team()`
- Hook `updating`: bei Status-Wechsel wird `status_changed_at` gesetzt
- `resolveFromSlug(slug, teamId)` – findet Event anhand `event_number` mit oder ohne `#`

### `Platform\Events\Models\EventDay` (Tabelle `events_event_days`)
- Felder: `label`, `datum` (date), `day_of_week`, `von`, `bis`, `pers_von`, `pers_bis`, `day_status`, `color`, `sort_order`
- Cascade-Delete über `event_id`

### `Platform\Events\Models\Booking` (Tabelle `events_bookings`)
- **Wichtig:** `location_id` (FK auf `locations_locations.id`, nullable) bevorzugt vor `raum` (Legacy-String)
- Accessor `$booking->display_room` liefert zuerst `location->kuerzel` (wenn eager-loaded), sonst `raum`
- Relation `location()` → `Platform\Locations\Models\Location`

### `Platform\Events\Models\ScheduleItem` (Tabelle `events_schedule_items`)
- Felder: `datum`, `von`, `bis`, `beschreibung`, `raum`, `bemerkung`, `linked` (boolean), `sort_order`

### `Platform\Events\Models\EventNote` (Tabelle `events_event_notes`)
- `type`-Enum: `liefertext` | `absprache` | `vereinbarung`
- Konstanten `EventNote::TYPE_*`

## Livewire Components & Routes

| Component | Alias | Route Name | URL (mit Prefix) |
| --------- | ----- | ---------- | ---------------- |
| `Dashboard` | `events.dashboard` | `events.dashboard` | `/events` |
| `Manage`    | `events.manage`    | `events.manage`    | `/events/liste` |
| `Detail`    | `events.detail`    | `events.show`      | `/events/va/{slug}` |
| `Sidebar`   | `events.sidebar`   | –                  | – |

`Detail` hat eine `#[Url]`-persistente Tab-Property `activeTab` mit Werten `basis|tage|buchungen|ablauf|notizen|mr`.

## Important Patterns

- Team-Scope: `$user->currentTeam` – immer `->where('team_id', $team->id)`
- UUIDs für alle Models (via `UuidV7::generate()` im `booted()`-Creating-Hook)
- Layout: `->layout('platform::layouts.app')`
- Views: `events::livewire.<name>`
- UI-Komponenten: `x-ui-page`, `x-ui-panel`, `x-ui-button`, `x-ui-dashboard-tile`, `x-ui-page-navbar`, `x-ui-page-container`, `x-ui-page-sidebar`, `x-ui-modal`, `x-ui-segmented-toggle`
- Filter persistieren über `#[Url(as: ..., except: ...)]`
- Modal-Pattern: `public bool $showXxxModal = false` + `openXxxCreate()/openXxxEdit($uuid)/closeXxxModal()/saveXxx()/deleteXxx($uuid)` mit `$this->reset([...])`

## Management Report

`mr_data` ist ein freies JSON-Feld am Event. Die Default-Feld-Definitionen stehen in `Detail::mrDefaults()` (12 Felder in 4 Gruppen: Logistik & Personal, Produktion, Rechnungen, Controlling). Die UI rendert Select-Inputs, jeder Change-Event ruft `setMrField(key, value)` auf und persistiert atomisch.

Konfigurierbarkeit (analog `MrFieldConfig` aus dem Alt-System) ist bewusst ausgelagert – kommt als spätere Ausbaustufe.

## Cross-Modul-Kopplung

- `Booking->location()` referenziert `Platform\Locations\Models\Location` direkt (Events setzt Locations-Modul voraus).
- Das Locations-Modul weiß nichts von Events. Die `Occupancy`-View im Locations-Modul erwartet pro Buchung ein Array `{title, optionsrang}` – diese Integration wird später über einen Contract/Listener im Events-Modul bereitgestellt.

## AI-Tools (ToolRegistry)

Alle Tools werden in `EventsServiceProvider::registerTools()` registriert (try/catch, no-op wenn platform-core fehlt). Implementierungen in `src/Tools/`.

### Events (Single + Bulk)

| Tool-Name                       | Klasse                     | Zweck                                      |
| ------------------------------- | -------------------------- | ------------------------------------------ |
| `events.events.GET`             | `ListEventsTool`           | Events des aktuellen Teams listen          |
| `events.event.GET`              | `GetEventTool`             | Details inkl. optionaler include_* Arrays  |
| `events.events.POST`            | `CreateEventTool`          | Event anlegen (+ optional EventDays)       |
| `events.events.PATCH`           | `UpdateEventTool`          | Event aktualisieren                        |
| `events.events.DELETE`          | `DeleteEventTool`          | Event löschen (Soft Delete)                |
| `events.events.bulk.POST`       | `BulkCreateEventsTool`     | Mehrere Events anlegen (atomic default)    |
| `events.events.bulk.PATCH`      | `BulkUpdateEventsTool`     | Mehrere Events aktualisieren (2 Modi)      |
| `events.events.bulk.DELETE`     | `BulkDeleteEventsTool`     | Mehrere Events löschen                     |

### Event-Tage

| Tool-Name                  | Klasse                  |
| -------------------------- | ----------------------- |
| `events.days.GET`          | `ListEventDaysTool`     |
| `events.day.GET`           | `GetEventDayTool`       |
| `events.days.POST`         | `CreateEventDayTool`    |
| `events.days.PATCH`        | `UpdateEventDayTool`    |
| `events.days.DELETE`       | `DeleteEventDayTool`    |

### Buchungen

| Tool-Name                  | Klasse                  |
| -------------------------- | ----------------------- |
| `events.bookings.GET`      | `ListBookingsTool`      |
| `events.booking.GET`       | `GetBookingTool`        |
| `events.bookings.POST`     | `CreateBookingTool`     |
| `events.bookings.PATCH`    | `UpdateBookingTool`     |
| `events.bookings.DELETE`   | `DeleteBookingTool`     |

Bei Create/Update: wenn `location_id` gesetzt ist, wird geprüft ob die Location zum gleichen Team gehört wie das Event.

### Ablaufplan

| Tool-Name                        | Klasse                       |
| -------------------------------- | ---------------------------- |
| `events.schedule-items.GET`      | `ListScheduleItemsTool`      |
| `events.schedule-item.GET`       | `GetScheduleItemTool`        |
| `events.schedule-items.POST`     | `CreateScheduleItemTool`     |
| `events.schedule-items.PATCH`    | `UpdateScheduleItemTool`     |
| `events.schedule-items.DELETE`   | `DeleteScheduleItemTool`     |

### Notizen

| Tool-Name                  | Klasse                   |
| -------------------------- | ------------------------ |
| `events.notes.GET`         | `ListEventNotesTool`     |
| `events.note.GET`          | `GetEventNoteTool`       |
| `events.notes.POST`        | `CreateEventNoteTool`    |
| `events.notes.PATCH`       | `UpdateEventNoteTool`    |
| `events.notes.DELETE`      | `DeleteEventNoteTool`    |

### Konventionen

- **Event-Selector:** Sub-Entity-Create/List nutzen `event_id` ODER `event_uuid` ODER `event_number` (mit/ohne `#`). Siehe Trait `Platform\Events\Tools\Concerns\ResolvesEvent`.
- **Identifikation (Get/Update/Delete):** `{entity}_id` ODER `uuid`.
- **Team-Scope:** `team_id` aus Argumenten oder `$context->team->id`; Zugriff via `$context->user->teams()->where('teams.id', $teamId)->exists()`.
- **Return-Payload:** flaches Payload mit `id`, `uuid`, entity-spezifischen Feldern, `team_id`, `message`.
- **Error-Codes:** `AUTH_ERROR`, `ACCESS_DENIED`, `VALIDATION_ERROR`, `EVENT_NOT_FOUND`, `DAY_NOT_FOUND`, `BOOKING_NOT_FOUND`, `SCHEDULE_NOT_FOUND`, `NOTE_NOT_FOUND`, `LOCATION_NOT_FOUND`, `MISSING_TEAM`, `EXECUTION_ERROR`, `BULK_VALIDATION_ERROR`, `INVALID_ARGUMENT`.
- **List-Tools:** nutzen `HasStandardGetOperations` (`filters`, `sort`, `limit`, `offset`, `search`).
- **Bulk-Tools:** `atomic=true` (default) wickelt alles in einer DB-Transaktion ab; bei `false` werden Teil-Erfolge geliefert.
