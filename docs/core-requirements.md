# Anforderungen an platforms-core / platform-crm

Sammlung offener Punkte, die im **platforms-core** (Interfaces) und im
**platform-crm** (Implementierung) ergänzt werden müssen, damit das
Events-Modul bestimmte Features sauber umsetzen kann, ohne eigene CRM-Logik
zu duplizieren.

Ziel: Events-Modul bleibt entkoppelt und greift nur über Core-Interfaces
auf CRM-Daten zu.

---

## 1. CRM-Firmenadressen abfragbar machen

### Status

**offen** — muss vom Core-/CRM-Entwickler umgesetzt werden.

Teilweise umgesetzt: der Locations-Teil (Lieferung in eigene Location via
`platforms-locations`) ist im Events-Modul bereits fertig, siehe
„Bereits umgesetzt" weiter unten.

### Hintergrund

Im Events-Modul gibt es das Panel **„Lieferung an"** (Basis-Tab). Es bietet
zwei sich gegenseitig ausschließende Auswahlen:

- **Eigene Location** (`events_events.delivery_location_id` →
  `locations_locations.id`) — funktioniert vollständig, nutzt den
  bestehenden `location-picker`-Partial.
- **Externe Lieferadresse (CRM)**
  (`events_events.delivery_address_crm_company_id` + Fallback-String
  `delivery_address`) — **hier liegt das Problem**: der Picker wählt nur
  eine CRM-Firma aus, hat aber keinen Zugriff auf deren Adressen. Wenn eine
  Firma mehrere Adressen (Hauptsitz, Lager, Filiale, Rechnungsanschrift, …)
  hat, ist nicht erkennbar oder konfigurierbar, **welche** gemeint ist. In
  PDFs/Verträgen erscheint deshalb aktuell nur der Firmenname.

Zusätzlich gibt es ein freies Bemerkungsfeld `delivery_note` für Dinge wie
„Haupteingang" / „Anlieferung über Hof" — das reicht aber nicht als Ersatz
für eine strukturierte Adresswahl.

Gewünschtes Verhalten für „Externe Lieferadresse":

- User wählt eine CRM-Firma (bereits vorhanden).
- Direkt darunter erscheint ein zweiter Picker mit allen Postadressen
  dieser Firma, analog zum Ansprechpartner-Picker („Organizer-Kontakt" o.ä.).
- Default-Auswahl ist die als `is_primary` markierte Adresse.
- Bei Wechsel der Firma wird der Adress-Picker neu befüllt; eine zuvor
  gewählte Adresse wird verworfen.

Das CRM kann die Daten laut Doku bereits liefern
(`CompanyInterface::getPostalAddresses()`), aber das **Core-Interface**
(`CrmCompanyResolverInterface`) kennt nur `displayName()` und `url()`. Es
fehlt ein modul-neutraler Weg, Adressen aus dem Events-Modul heraus zu
holen, ohne an `Platform\Crm\*` direkt zu koppeln.

### Präzedenzfall: Contact-Picker

Genau dieses Muster existiert bereits für Ansprechpartner und funktioniert
sauber entkoppelt:

- platforms-core definiert `CrmCompanyContactsProviderInterface` mit
  Methode `contacts(int $companyId): array`.
- platform-crm bindet eine konkrete Implementierung, die
  `CompanyInterface::getContacts()` auf ein neutrales Array-Format
  (`['id', 'name', 'role', 'email', …]`) mapped.
- Events-Modul nutzt es in `Platform\Events\Services\ContactPickerResolver`
  und im Blade-Partial `partials/crm-contact-picker.blade.php`.

Der Adress-Picker soll **exakt dem gleichen Schema** folgen, damit das
Events-Modul nicht wieder eigene Fallback-Logik aufbaut.

### Vorschlag

**Neues Interface in `platforms-core`** (analog zu
`CrmCompanyContactsProviderInterface`):

```php
namespace Platform\Core\Contracts;

interface CrmCompanyAddressesProviderInterface
{
    /**
     * Liefert alle Postal-Addresses einer CRM-Firma in einer
     * modul-neutralen Struktur.
     *
     * Jedes Array-Element:
     * [
     *   'id'         => int,          // PK der Adresse im CRM (für Referenz)
     *   'label'      => string,       // z.B. "Hauptsitz", "Lager Köln"
     *   'line1'      => string,       // Straße + Hausnummer
     *   'line2'      => ?string,      // Zusatz (c/o, Etage, …)
     *   'zip'        => string,
     *   'city'       => string,
     *   'country'    => ?string,      // ISO-2 oder Name
     *   'is_primary' => bool,
     *   'formatted'  => string,       // einzeilig, sofort darstellbar
     * ]
     *
     * Leeres Array wenn keine Firma oder keine Adressen vorhanden.
     */
    public function addresses(int $companyId): array;
}
```

**Implementierung in `platform-crm`:**

- Konkrete Klasse, die `CompanyInterface::getPostalAddresses()` in das
  oben definierte Format übersetzt.
- Binding im ServiceProvider: `app()->bind(CrmCompanyAddressesProviderInterface::class, …)`.
- Events-Modul prüft via `app()->bound(…)`, damit Deploys ohne CRM nicht
  crashen (genau wie beim Contact-Picker).

**Optional** — direkt an `CrmCompanyResolverInterface` anhängen: würde
alle bestehenden Implementierungen/Fakes brechen; ein separates Interface
ist sauberer und hält die Surface des Resolvers klein.

### Was im Events-Modul wartet (nach Umsetzung im Core)

1. Neuer Service `DeliveryAddressResolver` analog zu
   `ContactPickerResolver`, der für eine Event-Instanz die Adressen der
   bei `delivery_address_crm_company_id` gesetzten Firma liefert.
2. Picker-Partial `partials/crm-address-picker.blade.php` analog zum
   Contact-Picker mit Dropdown + Primary-Default + Clear-Button.
3. Neue Spalte `delivery_address_id` (nullable) in `events_events`, die
   die ID aus `CrmCompanyAddressesProviderInterface::addresses()[i]['id']`
   referenziert. Das bestehende String-Feld `delivery_address` bleibt als
   Fallback für Fälle ohne CRM (z.B. freie Angabe) und wird mit
   `formatted` gefüllt, wenn eine Adresse gewählt wurde, damit
   PDF/Contract-Renderer nichts Zusätzliches kennen müssen.
4. `ContractRenderer::{DELIVERY_COMPANY}` / `{DELIVERY_CONTACT}` und der
   Projekt-Funktions-PDF (`resources/views/pdf/projekt-function.blade.php`)
   lesen dann die formatierte Adresse statt nur den Firmennamen.

### Bereits umgesetzt im Events-Modul (Stand 2026-04-24)

- Spalte `delivery_location_id` (FK auf `locations_locations`) +
  Relation `Event::deliveryLocation()`.
- `location-picker` im Basis-Tab für „Eigene Location".
- Mutual-Exclusion zwischen `delivery_location_id` und
  `delivery_address_crm_company_id` im `Detail::updated()`-Hook.
- Freitextfeld `delivery_note` (z.B. „Haupteingang").
- Aufräumen der Altlasten: `delivery_supplier`, `delivery_crm_company_id`,
  `delivery_contact`, `delivery_crm_contact_id` sind aus DB, Model, Tools,
  PDF und ContractRenderer entfernt.

---

## 2. (Platzhalter für weitere Core-Anforderungen)

Weitere gemeinsam zu erweiternde Schnittstellen hier dokumentieren, sobald
sie auftauchen. Beispiele, die später nötig werden könnten:

- Zentrale Kosten-/Projekt-Nummern-Sequenz
- Einheitliches Team-weites Tagging
- E-Mail-Versand-Service (statt pro Modul eigene Mailer-Bindings)

Noch nichts konkret offen.
