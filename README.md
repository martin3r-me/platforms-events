# platforms-events

Operative Veranstaltungs-Verwaltung der Platform: Anfrage, Angebot, Vertrag, Bestellung, Packliste, Rechnung, Abschluss, Feedback.

## Dokumentation

Die vollständige Dokumentation wird **nicht in diesem Repo** gepflegt, sondern im **office.bhgdigital** Dev-Connector:

- Package: `platforms-events` (Package-ID `8`)
- Pages: Übersicht, Architektur, Setup, API-Referenz, Datenmodell, Testing, Deployment, Changelog, Contributing, Troubleshooting

Aufruf:

- Office-UI → Dev → Packages → `platforms-events`
- MCP: `dev.docs.overview(package_id=8)` im `office.bhgdigital`-Connector

Lokale `LLM_GUIDE.md` / `README.md`-Inhalte gelten als veraltet; Single Source of Truth sind die Connector-Pages.

## Installation

```bash
composer require martin3r/platforms-events
```

Der Service-Provider `Platform\Events\EventsServiceProvider` wird automatisch via `extra.laravel.providers` in `composer.json` registriert.

## Abhängigkeiten

- `platforms-core`
- `platforms-locations`
