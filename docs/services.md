# Service-Klassen im Events-Modul

Gemeinsame Geschaeftslogik ausserhalb der Livewire-Komponenten wohnt in
`src/Services/`. Ziel: Duplikate vermeiden, Testbarkeit, wiederverwendbar in
AI-Tools, API-Controllern und Kommandos.

## Bereits vorhanden

| Service                  | Zweck                                                                                    |
| ------------------------ | ---------------------------------------------------------------------------------------- |
| `ActivityLogger`         | Schreibt Event-Aktivitaeten ins Activity-Log.                                            |
| `PdfService`             | Kapselt DomPDF-Rendering (download / stream).                                            |
| `SettingsService`        | Setting-Werte pro Team lesen/schreiben (Bausteine, Status-Listen etc.).                  |
| `ContractRenderer`       | Rendert Vertraege: Markdown/HTML + Platzhalter + Asset-URL-Aufloesung (PDF + Web).       |
| `ProjektFunctionData`    | Baut das Datenarray fuer die Projekt-Function-PDF.                                       |
| `ArticleSearchService`   | Team-gefilterte, performante Artikel-Suche mit Prefix-Priorisierung.                     |
| `PositionCalculator`     | Auto-Berechnungen fuer Quote-/Order-Positionen (Stunden-Diff, Gesamt, Preis-Rueckw.).    |

## Noch zu extrahieren

Diese Logik liegt aktuell in Livewire-Komponenten und sollte bei naechster
Gelegenheit in Services gezogen werden:

### QuoteOrderConverter (aktuell in `Livewire/Detail/Quotes`)

Extrahiert aus `Quotes::convertQuoteItemToOrder`, `::syncQuoteItemToOrder`,
`::convertAllQuoteItemsOfDayToOrder`, `::convertAllQuoteItemsToOrder`,
`::syncOrderItemSummary`.

Geplant:

```php
QuoteOrderConverter::convertItem(QuoteItem $item): OrderItem
QuoteOrderConverter::syncItem(QuoteItem $item): ?OrderItem
QuoteOrderConverter::convertAllForDay(Event $event, int $dayId): Collection
QuoteOrderConverter::convertAllForEvent(Event $event): Collection
```

### ArticlePackageApplicator (aktuell in `Livewire/Detail/Quotes::applyPackage`)

Extrahiert den Flow: ArticlePackage -> neue QuotePositions fuer einen
QuoteItem, inklusive Artikel-Fallback (wenn `article_id` gesetzt).

Geplant:

```php
ArticlePackageApplicator::apply(ArticlePackage $package, QuoteItem $target): Collection // erzeugte QuotePositions
```

Optional auch fuer OrderItem, wenn wir Pakete auch in Bestellungen einfuegen
wollen.

### ContactPickerResolver (aktuell in `Livewire/Detail::render`)

Der CRM-Contact-Slot-Block (Fallback auf Veranstalter-Firma, Labels, URL,
hasCompany-Flag) laeuft aktuell in der render()-Methode. Koennte als
Service sauber extrahiert werden, um auch fuer andere Module/Entitaeten
verwendbar zu sein.

## Konvention

- Services sind statisch oder via Constructor-Injection wenn sie State/Deps
  brauchen. Statisch ist OK fuer stateless Utilities (z.B.
  `PositionCalculator`).
- Keine Validierung im Service; das macht die Livewire-Komponente oder ein
  Request-Objekt.
- Keine UI-Nachrichten (`session()->flash` etc.) im Service; der Aufrufer
  entscheidet was dem User angezeigt wird.
- Fehlerhafte Inputs koennen entweder `null` liefern oder eine Exception
  werfen – konsistent pro Service dokumentieren.
