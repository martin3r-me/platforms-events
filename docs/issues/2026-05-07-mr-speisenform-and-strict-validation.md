# Issue: Speisenform-MR-Feld + strikte mr_data-Validierung in LLM-Tools

**Datum:** 2026-05-07  
**Status:** Erledigt

## Kontext

Beim Import eines Management Reports via LLM-Tool (`events.events.PATCH` /
`events.events.POST`) wurden zwei Probleme sichtbar:

1. **Speisenform fehlte** — User schickte `speisenform`/`food_form`/`sf` und
   alle Varianten wurden silent verworfen, ohne dass das Tool ein Feedback
   gab. Das Feld existierte einfach nicht in der MR-Konfiguration.
2. **Silent-Drop ohne Erklärung** — `CreateEventTool` lieferte gar keine
   `ignored_fields`-Liste; `UpdateEventTool` lieferte sie zwar, aber ohne
   Hint welche Felder eigentlich erlaubt sind. Für `mr_data` gab es keine
   Validierung — beliebige Keys/Werte wurden silently in die JSON-Spalte
   geschrieben.

## Fix

### MR-Feld

- [x] `Speisenform` als Default-MR-Feld in `MrFieldConfig::seedDefaultsFor`
      hinzugefügt (Gruppe „Produktion"). **Optionen leer** — werden vom
      Team manuell in Einstellungen → Management Report gepflegt.
- [x] Migration `2026_05_07_100000_backfill_speisenform_mr_field_config.php`
      ergänzt Speisenform für Teams, die schon eine MR-Konfiguration haben
      (idempotent: nur wenn nicht vorhanden).

### Tool-Validierung

- [x] Neues Trait `Tools/Concerns/ValidatesMrData.php` —
      normalisiert `mr_data`-Keys (Label „Speisenform" oder kanonisch
      `mrf_<id>`) und validiert Werte strikt gegen `MrFieldConfig.options`.
- [x] `UpdateEventTool` & `CreateEventTool` rufen die Validierung auf;
      unbekannte Keys oder Werte ausserhalb der konfigurierten Optionen
      → `VALIDATION_ERROR` mit erlaubter Liste.
- [x] `CreateEventTool` liefert jetzt `ignored_fields` + (bei Bedarf)
      `allowed_top_level_fields` analog zu `UpdateEventTool`.
- [x] `UpdateEventTool` ergänzt `allowed_top_level_fields` als Hint, wenn
      `ignored_fields` nicht leer ist.
- [x] Tool-Beschreibungen + Schema-Descriptions auf strikte mr_data-Regel
      angepasst, damit das LLM die Constraints upfront sieht.

### Migration auf demo.bhgdigital.de

- [x] `php artisan migrate --force` gegen demo gelaufen. demo hat aktuell
      noch keine MR-Configs → Migration ist dort no-op. Sobald ein Team
      die Event-Detail-Seite öffnet, läuft `seedDefaultsFor` lazy mit
      Speisenform inkludiert.

## Geänderte Dateien

- `src/Models/MrFieldConfig.php` — Speisenform in Defaults
- `src/Tools/Concerns/ValidatesMrData.php` — neu
- `src/Tools/UpdateEventTool.php` — Validation + allowed_top_level_fields
- `src/Tools/CreateEventTool.php` — ignored_fields + Validation
- `database/migrations/2026_05_07_100000_backfill_speisenform_mr_field_config.php` — neu

## Offen / Folge-Issues

- Optionen für Speisenform in Einstellungen → Management Report pflegen
  (z.B. Buffet, Menü, Flying Buffet, Fingerfood, …) — bewusst nicht
  hartkodiert, damit Teams das selbst gestalten können.
- Bei Bedarf: Discovery-Tool `events.mr_fields.LIST` ergänzen, das dem
  LLM upfront alle erlaubten Keys+Optionen liefert (statt nur reaktiv via
  VALIDATION_ERROR).
