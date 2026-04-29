<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Events\Models\Event;

/**
 * Loest Foreign-Keys eines Events zu kompakten Objekten auf
 * (CRM-Companies, Contacts, eigene Locations).
 *
 * Soft-Dependency auf platform-crm/platforms-locations: wenn ein Modell
 * nicht gefunden wird, wird das Feld einfach weggelassen statt zu fehlen.
 */
trait HydratesEventReferences
{
    /**
     * Liefert die Referenz-Bloecke eines Events als kompakte Objekte
     * ({id, name, ...}). Fehlende oder nicht aufloesbare Refs sind null.
     *
     * @return array<string, ?array>
     */
    protected function hydrateEventReferences(Event $event): array
    {
        return [
            'customer_company'         => $this->hydrateCrmCompany($event->crm_company_id),
            'orderer_company_ref'      => $this->hydrateCrmCompany($event->orderer_crm_company_id),
            'invoice_company'          => $this->hydrateCrmCompany($event->invoice_crm_company_id),
            'delivery_company'         => $this->hydrateCrmCompany($event->delivery_address_crm_company_id),
            'organizer_contact_ref'    => $this->hydrateCrmContact($event->organizer_crm_contact_id),
            'organizer_onsite_contact' => $this->hydrateCrmContact($event->organizer_onsite_crm_contact_id),
            'orderer_contact_ref'      => $this->hydrateCrmContact($event->orderer_crm_contact_id),
            'invoice_contact_ref'      => $this->hydrateCrmContact($event->invoice_crm_contact_id),
            'delivery_location'        => $this->hydrateLocation($event->delivery_location_id),
        ];
    }

    protected function hydrateCrmCompany(?int $id): ?array
    {
        if (!$id) return null;
        $cls = '\\Platform\\Crm\\Models\\CrmCompany';
        if (!class_exists($cls)) return null;

        try {
            $row = $cls::find($id);
            if (!$row) return null;
            $name = $row->name ?: ($row->legal_name ?: $row->trading_name);
            return [
                'id'   => (int) $row->id,
                'name' => $name ? (string) $name : ('Unternehmen #' . $id),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function hydrateCrmContact(?int $id): ?array
    {
        if (!$id) return null;
        $cls = '\\Platform\\Crm\\Models\\CrmContact';
        if (!class_exists($cls)) return null;

        try {
            $row = $cls::find($id);
            if (!$row) return null;
            $first = (string) ($row->first_name ?? '');
            $last  = (string) ($row->last_name ?? '');
            $full  = trim($first . ' ' . $last);
            return [
                'id'   => (int) $row->id,
                'name' => $full !== '' ? $full : ('Kontakt #' . $id),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function hydrateLocation(?int $id): ?array
    {
        if (!$id) return null;
        $cls = '\\Platform\\Locations\\Models\\Location';
        if (!class_exists($cls)) return null;

        try {
            $row = $cls::find($id);
            if (!$row) return null;
            return [
                'id'      => (int) $row->id,
                'name'    => (string) $row->name,
                'kuerzel' => $row->kuerzel,
                'gruppe'  => $row->gruppe,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
