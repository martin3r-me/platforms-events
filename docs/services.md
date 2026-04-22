# Service-Klassen im Events-Modul

Gemeinsame Geschaeftslogik ausserhalb der Livewire-Komponenten wohnt in
`src/Services/`. Ziel: Duplikate vermeiden, Testbarkeit, wiederverwendbar in
AI-Tools, API-Controllern und Kommandos.

## Vorhanden

| Service                    | Zweck                                                                                     |
| -------------------------- | ----------------------------------------------------------------------------------------- |
| `ActivityLogger`           | Schreibt Event-Aktivitaeten ins Activity-Log.                                             |
| `PdfService`               | Kapselt DomPDF-Rendering (download / stream).                                             |
| `SettingsService`          | Setting-Werte pro Team lesen/schreiben (Bausteine, Status-Listen etc.).                   |
| `ContractRenderer`         | Rendert Vertraege: Markdown/HTML + Platzhalter + Asset-URL-Aufloesung (PDF + Web).        |
| `ProjektFunctionData`      | Baut das Datenarray fuer die Projekt-Function-PDF.                                        |
| `ArticleSearchService`     | Team-gefilterte, performante Artikel-Suche mit Prefix-Priorisierung.                      |
| `PositionCalculator`       | Auto-Berechnungen fuer Quote-/Order-Positionen (Stunden-Diff, Gesamt, Preis-Rueckw.).     |
| `QuoteOrderConverter`      | Wandelt QuoteItems in OrderItems (inkl. Typ-Dedupe) und synchronisiert Positionen.        |
| `ArticlePackageApplicator` | Fuegt ein ArticlePackage als QuotePositions an einen QuoteItem an.                        |
| `ContactPickerResolver`    | Liefert die CRM-Contact-Slot-Daten fuer den Contact-Picker (inkl. Veranstalter-Fallback). |

## Ideen fuer weitere Extraktionen

Keine konkreten Refactorings mehr offen. Wenn neue Geschaeftslogik in
Livewire-Komponenten wachsen sollte, bitte pruefen, ob sie hier rauspasst.

## Konvention

- Services sind statisch oder via Constructor-Injection, wenn sie State/Deps
  brauchen. Statisch ist OK fuer stateless Utilities (z.B.
  `PositionCalculator`, `ArticleSearchService`).
- Keine Validierung im Service; das macht die Livewire-Komponente oder ein
  Request-Objekt.
- Keine UI-Nachrichten (`session()->flash` etc.) im Service; der Aufrufer
  entscheidet, was dem User angezeigt wird.
- Fehlerhafte Inputs koennen entweder `null` liefern oder eine Exception
  werfen – konsistent pro Service dokumentieren.
