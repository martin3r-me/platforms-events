# LLM Guide für Events Module

## Übersicht

- **Namespace:** `Platform\Events`
- **View-Namespace:** `events::livewire.xxx`
- **Route-Namen:** `events.xxx`
- **Config-Key:** `events`
- **Composer:** `martin3r/platforms-events`

## Architektur

```
EventsServiceProvider
├── register()          # Config laden
└── boot()              # Modul-Registrierung & Setup
    ├── PlatformCore::registerModule()
    ├── ModuleRouter::group()
    ├── loadMigrationsFrom()
    ├── loadViewsFrom()
    └── registerLivewireComponents()
```

## Livewire Component Pattern

```
Datei: src/Livewire/Dashboard.php
Alias: events.dashboard
Verwendung: <livewire:events.dashboard />
```

## Wichtige Patterns

- Team-basierte Daten: `Model::where('team_id', $team->id)->get()`
- UUIDs: UuidV7 auto-generation on creating
- Layout: `->layout('platform::layouts.app')`
- Views: `view('events::livewire.dashboard', [...])`
