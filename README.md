# Platform Events Module

Events-Modul für die Platform - Plane und verwalte Events.

## Struktur

```
events/
├── composer.json
├── config/
│   └── events.php
├── database/
│   └── migrations/
├── resources/
│   └── views/
│       └── livewire/
│           ├── dashboard.blade.php
│           ├── test.blade.php
│           └── sidebar.blade.php
├── routes/
│   └── web.php
├── src/
│   ├── EventsServiceProvider.php
│   └── Livewire/
│       ├── Dashboard.php
│       ├── Test.php
│       └── Sidebar.php
└── README.md
```

## Composer registrieren

In `composer.json` der Hauptanwendung:
```json
{
  "require": {
    "martin3r/platforms-events": "dev-main"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../platform/modules/events"
    }
  ]
}
```

Dann: `composer update`

## Routes

- `/events` - Dashboard
- `/events/test` - Test-Seite
