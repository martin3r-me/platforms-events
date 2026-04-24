# Pauschal-Kalkulator (Flat Rate)

Regelbasierter Kalkulator, der pro Vorgang (QuoteItem) eine Pauschale-
Position erzeugt. Ziel: Pauschalen (Getränke, Bar, Buffet, Geschirr,
Equipment, …) in Angeboten konsistent, nachvollziehbar und
konfigurierbar erfassen — statt Handrechnung.

Status: **MVP** produktiv (auf Angebote). V2-Roadmap am Ende des Doks.

## Architektur im Überblick

```
        ┌────────────────────────┐
        │ events_flat_rate_rules │ ← Definition: Scope, Formel, Output
        └────────────┬───────────┘
                     │
        Apply via Button (im Quote-Editor oder Kalkulations-Tab)
                     │
                     ▼
 ┌─────────────────────────────────────┐
 │ FlatRateApplicator::apply($rule,$qi)│
 │   1. Kontext bauen (event/day/item) │
 │   2. FlatRateEngine::evaluate()     │
 │   3. QuotePosition create/update    │
 │   4. FlatRateApplication (Audit)    │
 │   5. QuoteItem recalc               │
 └─────────────────────┬───────────────┘
                       │
                       ▼
      ┌───────────────────────────────┐
      │ events_flat_rate_applications │ ← Audit: snapshot, result, superseded_at
      └───────────────────────────────┘
```

Die erzeugte Pauschale ist eine **ganz normale QuotePosition** — d.h.
PDF-Rendering, Contract-Tokens, Order-Sync und Netto/Brutto-Umrechnung
laufen automatisch mit. Kein Sonderfall in den Rendering-Pfaden.

## Datenmodell

### `events_flat_rate_rules`
Regel-Definition, team-scoped, soft-deletable.

| Spalte | Typ | Zweck |
|---|---|---|
| `name` | string | UI-Label, z.B. "Getränke-Pauschale Hochzeit" |
| `description` | text, nullable | Freitext fuer den Admin |
| `scope_typs` | JSON array | QuoteItem.typ-Liste, z.B. `["Getränke","Bar"]`. Regel greift nur bei passendem Typ. |
| `scope_event_types` | JSON array, nullable | Optionale Einschraenkung auf Anlaesse. Leer = alle. |
| `formula` | longText | Symfony-ExpressionLanguage-Body. Muss numerisch zurueckkommen. |
| `output_name` | string | Name der erzeugten QuotePosition |
| `output_gruppe` | string | Artikelstamm-Gruppe (wird gegen `PositionValidator::allowedGruppen` geprueft) |
| `output_mwst` | string | z.B. `19%` |
| `output_procurement_type` | string, nullable | Optional fuer Packliste/Beschaffung |
| `priority` | smallint | Reihenfolge im PL-Picker bei mehreren Matches |
| `is_active` | bool | Regel aktiv/pausiert |
| `last_error` / `last_error_at` | text / timestamp | Letzter Formel-Fehler fuer UI-Banner |

### `events_flat_rate_applications`
Audit-Trail, append-only, mit Soft-Overwrite via `superseded_at`.

| Spalte | Typ | Zweck |
|---|---|---|
| `rule_id` | FK nullOnDelete | Regel zum Zeitpunkt des Applys |
| `quote_item_id` | FK cascadeOnDelete | Betroffener Vorgang |
| `quote_position_id` | FK nullOnDelete | Erzeugte Pauschale-Position (bleibt null wenn geloescht) |
| `input_snapshot` | JSON | Kompletter Kontext zum Applikationszeitpunkt |
| `result_value` | decimal(12,2) | Gerechneter Netto-Betrag |
| `result_breakdown` | JSON | Debug-Info (Formel + Rule-UUID) |
| `superseded_at` | timestamp, nullable | Gesetzt, wenn Re-Apply oder Remove die Anwendung abloest |

**Idempotenz**: Pro `(rule_id, quote_item_id)` existiert max. **eine**
Application mit `superseded_at IS NULL`. Re-Apply ueberschreibt die
Position statt zu duplizieren und markiert die vorige Application als
superseded.

## Engine: `FlatRateEngine`

Pfad: `src/Services/FlatRateEngine.php`
Dependency: `symfony/expression-language ^7.0`

### Whitelist-Funktionen

| Funktion | Zweck |
|---|---|
| `min(a,b,…)`, `max(a,b,…)` | Standard |
| `round(x)`, `ceil(x)`, `floor(x)`, `abs(x)` | Rundung/Betrag |
| `clamp(x, lo, hi)` | Zwischen `lo` und `hi` halten |
| `tier(x, [b1, b2, …])` | Index der Staffel, in die `x` faellt |
| `season(dateStr)` | `'winter'|'spring'|'summer'|'autumn'` |
| `weekday(dateStr)` | `'Mo'|'Di'|…` |
| `is_weekend(dateStr)` | bool |

### Sicherheit

- **Whitelist**: Nur die obigen Funktionen sind registriert. PHP-Funktionen
  wie `system`, `eval`, `file_get_contents` sind nicht aufrufbar.
- **Sandbox**: Kontext wird als skalare Werte + stdClass uebergeben — keine
  Eloquent-Objekte, kein DB-Durchgriff ueber `__get`.
- **Keine Side-Effects**: Formeln lesen nur, schreiben nie.

### Object- vs Array-Zugriff

ExpressionLanguage unterstuetzt Punkt-Notation nur auf Objekten. Die
Top-Level-Scopes (`event`, `day`, `item`, `team`, `custom`) werden daher
in `stdClass` konvertiert. Innere Maps (z.B. `item.sum_by_gruppe`)
bleiben Arrays — dort gilt Bracket-Zugriff:

```
day.pers_avg               # OK  (stdClass-Property)
item.sum_by_gruppe['Bier'] # OK  (Array-Bracket)
item.sum_by_gruppe.Bier    # FEHLER — dotnotation auf Array
```

### Verfügbare Kontext-Variablen

**event.***
- `type` — Anlass (`event.event_type`)
- `group` — Anlassgruppe (`event.group`)
- `duration_days` — Anzahl EventDays
- `season` — aus `start_date`
- `month` — 1–12

**day.***
- `duration_hours` — aus `von`/`bis`, ueber Mitternacht wird korrekt gerechnet
- `pers_min` / `pers_max` / `pers_avg` — aus `pers_von`/`pers_bis`
- `split_a` / `split_b` — 0–100
- `children` — aus `children_count`
- `adults` — `round(pers_avg) - children`
- `weekday` — `'Mo'|'Di'|…`
- `is_weekend` — bool
- `datum` — YYYY-MM-DD

**item.*** (der aktuelle Vorgang)
- `sum_ek` — EK-Summe aller Positionen
- `sum_vk_netto` — Netto-VK-Summe (respektiert `quote_price_mode`)
- `sum_gesamt` — Roh-Summe aller `gesamt`-Werte
- `sum_anz` — Summe aller `anz`-Werte
- `count` — Anzahl Positionen
- `price_mode` — `'netto' | 'brutto'`
- `typ` — Vorgangs-Typ (z.B. `'Getränke'`)
- `sum_by_gruppe['X']` — Wert-Summe pro Gruppe
- `count_by_gruppe['X']` — Positionsanzahl pro Gruppe
- `anz_by_gruppe['X']` — summierte Stueckzahlen pro Gruppe
- `ek_by_gruppe['X']` — EK-Summe pro Gruppe
- `sum_by_typ['Speisen']` — aggregierte Umsaetze aus **anderen** Vorgaengen des gleichen EventDays

## Applicator: `FlatRateApplicator`

Pfad: `src/Services/FlatRateApplicator.php`

Oeffentliche API:

- `apply(FlatRateRule, QuoteItem): array` — schreibt/ueberschreibt Position, erzeugt Application-Audit, rechnet Item-Rollup neu. Wirft `RuntimeException` bei Validierungs-/Formel-Fehlern (Caller faengt + flasht `positionError`).
- `remove(FlatRateApplication): void` — loescht Position, markiert Application als superseded, Item-Recalc. Audit bleibt erhalten.
- `dryRun(FlatRateRule, QuoteItem): array` — reine Auswertung ohne Persistierung. Liefert `['ok', 'value', 'error', 'context']`.
- `buildContext(Event, EventDay, QuoteItem): array` — statisch nutzbar (z.B. fuer Debug/Tests).

### Garantien / Verhalten
- Transactional beim Apply/Remove
- Validiert `output_gruppe` gegen `PositionValidator::allowedGruppen($teamId)` — gleiches Schutzniveau wie normale Positions-Eingabe
- Setzt `last_error` / `last_error_at` auf der Regel bei Formel-Fehlern; raeumt auf bei erfolgreichem Apply
- Beim Re-Apply wird die existierende Position **in-place** aktualisiert (Preis/Name/MwSt/Beschaffung), nicht neu angelegt → keine Duplikate

## UI

### Regel-Verwaltung (Admin)
`src/Livewire/Settings.php` + `resources/views/livewire/settings.blade.php`
— Tab `flat_rates`:

- Liste mit Name, Scope-Badges, Formel-Preview, Output-Meta, Status-Dot
- Fehler-Zeile falls Regel zuletzt scheiterte
- Modal-Editor:
  - Felder fuer Name, Scope (CSV mit datalist), Formel (Textarea)
  - Output-Config incl. Gruppe mit datalist aus Artikelstamm
  - Legende mit allen Variablen + Funktionen (aus `FlatRateEngine::catalog()`)
  - **Dry-Run-Panel**: Vorgang waehlen → Auswerten → Ergebnis + JSON-Kontext

### Einstieg 1: Quote-Positions-Editor
`resources/views/partials/quote-positions-editor.blade.php` + `src/Livewire/Detail/Quotes.php`

Action-Bar-Button **"Pauschale"** (gruen) nur sichtbar, wenn mindestens
eine Regel zum Vorgang passt. Badge-Count fuer aktive Applications.
Modal listet gefilterte Regeln mit pro-Regel-Status:

- Nicht angewendet → `Anwenden`
- Aktiv, Wert unveraendert → Badge "aktiv: 1.200 €" + `Neu berechnen`
- Aktiv, Preis manuell angepasst → amber Rahmen, Diff-Anzeige, `Neu berechnen` mit Confirm-Dialog ("manueller Wert wird ueberschrieben")

### Einstieg 2: Kalkulations-Tab (PL-Dashboard)
`src/Livewire/Detail/Calculation.php` + `resources/views/livewire/detail/calculation.blade.php`

Oberhalb der klassischen DB-Analyse ein Block **"Angewendete Pauschalen"**:

- Alle aktiven (non-superseded) Applications des Events
- Pro Zeile: Regel-Name, Typ-Badge, Datum, berechneter vs aktueller Wert, Override-Marker mit Diff
- Aufklappbarer Kontext-Snapshot (Formel + `input_snapshot`-JSON)
- Pro-Zeile-Actions **Neu berechnen** + **Entfernen** (`FlatRateApplicator::remove()`)
- Link "Regeln verwalten" zum Settings-Tab

### Override-Erkennung
`FlatRateApplication::isOverridden()`:
- Vergleich `quotePosition->preis` vs `application->result_value`
- Schwelle: 1 Cent (um Rundungs-Rauschen zu ignorieren)

Editiert der PL die Pauschale-Position direkt in der Tabelle, wird sie
automatisch als "manuell angepasst" erkannt. Re-Apply erfordert dann
einen expliziten Bestaetigungs-Dialog.

## Price-Mode-Kompatibilität

- Applicator respektiert `event.quote_price_mode`:
  - `item.sum_vk_netto` rechnet bei Brutto-Vorgaengen zurueck (`/ (1+mwst/100)`).
- Die erzeugte Pauschale wird mit `output_mwst` der Regel gespeichert — und dann von der bestehenden Positions-Totals-Logik je nach Price-Mode korrekt interpretiert.

## Was bewusst NICHT im MVP ist

Diese Punkte sind fuer **V2** geplant; der aktuelle Code macht noch
keine Annahmen, die einer dieser Erweiterungen im Weg stehen:

1. **Custom-Variablen** (Tabelle `events_flat_rate_variables`) — team-
   spezifische Faktoren ohne Code-Deploy, z.B. aus `event.mr_data`-JSON.
   Das Namespace `team.*` und `custom.*` im Kontext ist bereits
   reserviert.
2. **Stale-Detection** — Hash des `input_snapshot` vs aktueller Kontext.
   Banner "Pauschale ist nicht mehr aktuell, neu berechnen?" falls
   Event-Daten sich seit letztem Apply geaendert haben.
3. **Staffel-UI** als grafische Alternative zur Expression-Formel. Der
   Rule-Datenmodell-Entwurf (core-requirements.md Punkt 1) erwaehnt
   `formula_kind = tiered`; das ist noch nicht gebaut.
4. **Pauschalen auf Bestellungen (OrderItem)** — bisher nur Angebote.
   OrderItem hat schon `price_mode`; die Engine-Context-Struktur muesste
   nur um `order_item.*` ergaenzt und im `scope_typs` analog zum Quote-
   Flow gematcht werden.
5. **Parameter-Overrides pro Event** — Regel definiert Default-Werte,
   PL kann beim Anwenden fuer dieses eine Event ueberschreiben. Braucht
   eine zusaetzliche Spalte `rule_parameters` auf `flat_rate_rules` und
   ein `parameter_overrides` auf `flat_rate_applications`.
6. **Artikel-Kopplung** (besprochen, zurueckgestellt). Entweder:
   a) `article.flat_rate_rule_id` → Pauschal-Artikel
   b) `flat_rate_rules.represented_article_id` → Regel referenziert Artikel
   Beides war dem Admin-/PL-Mental-Model der aktuellen Runde zu
   weitgreifend; Details stehen in der Chat-Historie.
7. **Matrix-Editor** (V3): Anlass x Personen-Raster visuell pflegbar.
8. **Regel-Import/Export** (V3) als JSON, damit Teams Vorlagen teilen.

## Relevante Pfade auf einen Blick

**Neu mit dem Feature:**
- `database/migrations/2026_04_25_100000_create_events_flat_rate_tables.php`
- `src/Models/FlatRateRule.php`
- `src/Models/FlatRateApplication.php`
- `src/Services/FlatRateEngine.php`
- `src/Services/FlatRateApplicator.php`

**Geaendert:**
- `composer.json` (symfony/expression-language)
- `src/Livewire/Settings.php` (Tab `flat_rates` + CRUD + Dry-Run)
- `resources/views/livewire/settings.blade.php` (Tab + Modal)
- `src/Livewire/Detail/Quotes.php` (Apply-Button + Modal + eligible-Rules-Loading)
- `resources/views/livewire/detail/quotes.blade.php` (Apply-Modal)
- `resources/views/partials/quote-positions-editor.blade.php` (Button im Action-Bar)
- `src/Livewire/Detail/Calculation.php` (Dashboard + Re-Apply/Remove)
- `resources/views/livewire/detail/calculation.blade.php` (Pauschalen-Block)

## Verifikations-Leitfaden

Sinnvoll vor groesseren Refactorings wiederholen:

1. Settings → Pauschalen → neue Regel mit Formel `day.pers_avg * 20` anlegen.
2. Im Modal via Dry-Run gegen einen realen Vorgang pruefen — Ergebnis + Kontext-JSON erscheinen.
3. Im Angebot → Vorgang → **Pauschale** → Regel anwenden → Position erscheint in der Tabelle, QuoteItem-Umsatz-Rollup aktualisiert.
4. Kalkulations-Tab oeffnen → die Application ist oben gelistet mit Kontext-Aufklapper.
5. Preis der Pauschale-Position direkt in der Tabelle editieren (z.B. -100€).
6. Zurueck ins Modal oder in den Kalkulations-Tab: Override-Marker erscheint, Re-Apply fragt nach Bestaetigung.
7. `events_flat_rate_applications` in DB: zwei Zeilen (erste `superseded_at` gesetzt, zweite aktiv) nach Re-Apply.
