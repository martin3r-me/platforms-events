<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Events\Models\Event;
use Platform\Events\Models\MrFieldConfig;
use Platform\Events\Services\SettingsService;

/**
 * Liefert eine Map "Feldname => Erklaerung" fuer empfohlene, aber noch leere
 * Felder eines Events. Wird in Create/Update/Get-Tool-Responses als
 * empty_recommended_fields ausgespielt – damit das LLM (und Entwickler)
 * direkt sehen, welche fachlich wichtigen Luecken offen sind.
 *
 * Bewusst keine harten Pflichtfelder: nur Hinweise, kein Block.
 */
trait RecommendsMissingFields
{
    /**
     * @return array<string, string>
     */
    protected function emptyRecommendedFields(Event $event): array
    {
        $event->loadMissing(['days', 'bookings']);
        $missing = [];

        if (empty($event->event_type)) {
            $missing['event_type'] = 'Anlass der Veranstaltung (z.B. Weihnachtsfeier, Hochzeit, Tagung). Hilft bei Filterung, Pauschalen-Regeln und Reporting.';
        }
        if (empty($event->crm_company_id) && empty($event->customer)) {
            $missing['crm_company_id'] = 'Auftraggeber-Firma (FK crm_companies.id). Verknuepft das Event mit dem CRM-Datensatz.';
        }
        if (empty($event->organizer_crm_contact_id) && empty($event->organizer_contact)) {
            $missing['organizer_crm_contact_id'] = 'Hauptansprechpartner beim Veranstalter. Wichtig fuer Kommunikation und Vertraege.';
        }
        if (empty($event->orderer_crm_company_id) && empty($event->orderer_company)) {
            $missing['orderer_crm_company_id'] = 'Bestellende Firma (oft = crm_company_id). Trennt Bestellung von Auftraggeber.';
        }
        if (empty($event->invoice_crm_company_id) && empty($event->invoice_to)) {
            $missing['invoice_crm_company_id'] = 'Rechnungs-Empfaenger (FK crm_companies.id). Pflicht fuer Rechnungserstellung.';
        }
        if (empty($event->potential)) {
            $missing['potential'] = 'Wahrscheinlichkeitstext, z.B. "70% (deutliche Tendenz zur Buchung)". Treibt das Forecasting / Vertriebsboard.';
        }
        if (empty($event->cost_carrier)) {
            $missing['cost_carrier'] = 'Kostentraeger – wird beim Anlegen automatisch mit der Event-Nummer vorbelegt; manueller Wert nur bei Abweichung.';
        }
        if (empty($event->end_date)) {
            $missing['end_date'] = 'End-Datum der Veranstaltung. Ohne end_date wird das Event als 1-Tages-Event behandelt.';
        }
        if ($event->days->isEmpty()) {
            $missing['days'] = 'Keine EventDays angelegt. Setze auto_create_days=true beim POST oder ergaenze Tage einzeln per events.days.POST.';
        }
        if ($event->bookings->isEmpty()) {
            $missing['bookings'] = 'Keine Raumbuchung angelegt. Verwende events.bookings.POST mit apply_to_all_days=true fuer eine schnelle Default-Belegung.';
        }
        // Lieferung: drei Quellen – wenn alle leer, Hinweis.
        $hasDelivery = !empty($event->delivery_address)
            || !empty($event->delivery_address_crm_company_id)
            || !empty($event->delivery_location_id);
        if (!$hasDelivery) {
            $missing['delivery'] = 'Keine Lieferadress-Quelle gesetzt (delivery_address, delivery_address_crm_company_id oder delivery_location_id). Optional, aber wichtig fuer den Lieferschein.';
        }
        if (empty($event->responsible)) {
            $missing['responsible'] = 'Hauptverantwortliche/r. Wird beim Anlegen automatisch mit dem User-Namen vorbelegt – falls leer, manuell setzen.';
        }

        return $missing;
    }

    /**
     * Liefert pro relevantem Enum-/Pickliste-Feld die aktuell waehlbaren Werte
     * – inklusive Quelle (hardcoded vs. Settings) und Hinweis, ob die Liste in
     * den Einstellungen erweitert werden kann.
     *
     * @return array<string, array{values: array<int,string>, strict: bool, note: string}>
     */
    protected function recommendedFieldOptions(?int $teamId = null): array
    {
        $teamId = $teamId ?? null;
        $opts = [];

        // potential – strikt, hardcoded
        $opts['potential'] = [
            'values' => [
                '10% (unwahrscheinlich)',
                '30% (unverbindliche Anfrage)',
                '50% (Tendenz offen)',
                '70% (deutliche Tendenz zur Buchung)',
                '90% (ziemlich definitiv)',
            ],
            'strict' => true,
            'note'   => 'Hardcoded Enum. Andere Werte werden im Tool mit VALIDATION_ERROR abgelehnt.',
        ];

        // status (Event-Status) – strikt, hardcoded
        $opts['status'] = [
            'values' => ['Option', 'Definitiv', 'Vertrag', 'Abgeschlossen', 'Storno', 'Warteliste', 'Tendenz'],
            'strict' => true,
            'note'   => 'Hardcoded Workflow-Status (UI: Manage::STATUS_OPTIONS).',
        ];

        // event_type – frei aus Settings
        $opts['event_type'] = [
            'values' => SettingsService::eventTypes($teamId),
            'strict' => false,
            'note'   => 'Vorgeschlagene Werte. Liste ist in Einstellungen → Anlass-Typen frei erweiterbar; abweichende Freitext-Werte werden akzeptiert.',
        ];

        // cost_center – frei aus Settings (oft leer)
        $opts['cost_center'] = [
            'values' => SettingsService::costCenters($teamId),
            'strict' => false,
            'note'   => 'Vorgeschlagene Werte. Liste ist in Einstellungen → Kostenstellen frei erweiterbar; abweichende Freitext-Werte werden akzeptiert.',
        ];

        // cost_carrier – frei aus Settings
        $opts['cost_carrier'] = [
            'values' => SettingsService::costCarriers($teamId),
            'strict' => false,
            'note'   => 'Vorgeschlagene Werte. Liste ist in Einstellungen → Kostentraeger frei erweiterbar; abweichende Freitext-Werte werden akzeptiert.',
        ];

        // quote_price_mode – fix
        $opts['quote_price_mode'] = [
            'values' => ['netto', 'brutto'],
            'strict' => true,
            'note'   => 'Hardcoded.',
        ];

        // orderer_via – strikt, hardcoded (mit Auto-Alias-Toleranz im Tool).
        $opts['orderer_via'] = [
            'values' => ['Mail', 'Telefon', 'Web'],
            'strict' => true,
            'note'   => 'Hardcoded Enum. Wie wurde das Event bestellt / wo kam die Anfrage rein? Auto-Alias akzeptiert E-Mail/Email->Mail, Phone/Tel->Telefon, Website/Online/Formular/Kontaktformular->Web; Diagnose via aliases_applied[].',
        ];

        // mr_data – verschachtelt: pro Feld-Label die in den Settings konfigurierten Optionen.
        $mrConfigs = $teamId
            ? MrFieldConfig::where('team_id', $teamId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
            : collect();

        $mrFields = [];
        foreach ($mrConfigs as $cfg) {
            $values = array_values(array_map(
                fn ($o) => is_array($o) ? ($o['label'] ?? '') : (string) $o,
                $cfg->options ?? []
            ));
            $mrFields[$cfg->label] = [
                'mrf_key'     => 'mrf_' . $cfg->id,
                'group_label' => $cfg->group_label,
                'values'      => $values,
                'strict'      => true,
                'note'        => empty($values)
                    ? 'Noch keine Optionen konfiguriert. Pflegen in Einstellungen → Management Report.'
                    : 'STRIKT. Erweiterbar in Einstellungen → Management Report.',
            ];
        }
        $opts['mr_data'] = [
            'fields' => $mrFields,
            'strict' => true,
            'note'   => 'Keys = Feld-Label (z.B. "Speisenform") oder mrf_<id>; Werte aus den jeweils konfigurierten Optionen. Pflegen in Einstellungen → Management Report.',
        ];

        return $opts;
    }
}
