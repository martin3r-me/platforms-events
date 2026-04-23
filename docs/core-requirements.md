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

### Hintergrund
Im Events-Modul gibt es das Feld **Lieferadresse** (`events_events.delivery_address`).
Aktuell wird hier eine zweite CRM-Firma separat gewählt, obwohl die Firma
bereits als **Lieferant** (`delivery_crm_company_id`) gesetzt ist. Das ist
redundant.

Gewünschtes Verhalten im Events-Modul:

- Der User wählt bei **Lieferant** eine CRM-Firma.
- Das Feld **Lieferadresse** zeigt per Default die Hauptadresse dieser Firma.
- Hat die Firma mehrere Adressen (Hauptsitz, Lager, Filiale, …), soll per
  Dropdown zwischen ihnen gewählt werden können.
- Zusätzlich soll eine eigene **Location** (aus `platforms-locations`)
  wählbar sein — für Events in eigenen Locations.

Das CRM kann das in `CompanyInterface::getPostalAddresses()` bereits liefern,
aber das **Core-Interface** (`CrmCompanyResolverInterface`) kennt nur
`displayName()` und `url()`. Es fehlt ein Weg, Adressen aus dem Events-Modul
heraus zu holen, ohne an `Platform\Crm\*` direkt zu koppeln.

### Vorschlag

**Neues Interface in `platforms-core`:**

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
     *   'id'      => int|string,   // interne Referenz (optional)
     *   'label'   => string,       // z.B. "Hauptsitz", "Lager Köln"
     *   'line1'   => string,       // Straße + Hausnummer
     *   'line2'   => ?string,      // Zusatz (c/o, Etage, …)
     *   'zip'     => string,
     *   'city'    => string,
     *   'country' => ?string,      // ISO-2 oder Name
     *   'is_primary' => bool,
     *   'formatted'  => string,    // einzeilig, sofort darstellbar
     * ]
     *
     * Leeres Array wenn keine oder unbekannte Firma.
     */
    public function addresses(?int $companyId): array;
}
```

**Implementierung in `platform-crm`:**
Binding im ServiceProvider auf eine konkrete Klasse, die
`CompanyInterface::getPostalAddresses()` in das oben definierte Array-Format
übersetzt.

**Optional** — falls das Core-Team es lieber im bestehenden Interface haben
möchte: Methode direkt an `CrmCompanyResolverInterface` anhängen. Das würde
aber alle bestehenden Implementierungen/Fakes brechen; ein separates
Interface ist sauberer und kann via `app()->bound(...)` optional resolved
werden.

### Was im Events-Modul wartet

Sobald das Interface da ist, setzt das Events-Modul um:

1. Neuer Service `DeliveryAddressResolver`, der
   - die Adressen des bei `delivery_crm_company_id` gesetzten Lieferanten holt
   - dazu alle `Platform\Locations\Models\Location` des Teams liefert
   - beides als kombinierte Optionsliste für den Picker zurückgibt.
2. Picker-Partial `delivery-address-picker.blade.php` mit zwei Sektionen
   („Adressen von [Lieferant]" / „Eigene Locations").
3. Neues Feld `delivery_location_id` (nullable FK auf `locations_locations`)
   in `events_events`, damit Locations referenziell gespeichert werden können.
4. Bestehendes Feld `delivery_address_crm_company_id` kann entfallen — die
   CRM-Bindung steckt schon im Lieferanten.

---

## 2. (Platzhalter für weitere Core-Anforderungen)

Weitere gemeinsam zu erweiternde Schnittstellen hier dokumentieren, sobald
sie auftauchen. Beispiele, die später nötig werden könnten:

- Zentrale Kosten-/Projekt-Nummern-Sequenz
- Einheitliches Team-weites Tagging
- E-Mail-Versand-Service (statt pro Modul eigene Mailer-Bindings)

Noch nichts konkret offen.
