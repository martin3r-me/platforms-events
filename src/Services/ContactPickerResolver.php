<?php

namespace Platform\Events\Services;

use Platform\Core\Contracts\CrmCompanyContactsProviderInterface;
use Platform\Core\Contracts\CrmContactResolverInterface;
use Platform\Events\Models\Event;

/**
 * Loest fuer eine Event-Instanz die Contact-Slot-Metadaten auf, die der
 * CRM-Contact-Picker (partials/crm-contact-picker.blade.php) braucht.
 *
 * Pro Slot liefert resolve() ein Array mit:
 *  - contacts:     Liste der CRM-Kontakte der gebundenen Firma
 *  - currentId:    aktuell gewaehlter Kontakt
 *  - currentLabel: Anzeige-Label
 *  - currentUrl:   URL zum Kontakt im CRM
 *  - hasCompany:   ob der Slot eine Firma hat (direkt oder via Veranstalter-Fallback)
 *  - fallback:     reiner Text-Wert aus dem Event (wenn kein CRM)
 *
 * Slot-Struktur identisch zu Detail::CRM_CONTACT_SLOTS.
 */
class ContactPickerResolver
{
    public function __construct(
        protected array $companySlots,         // ['organizer' => ['id' => 'crm_company_id', 'label' => 'customer'], ...]
        protected array $contactSlots          // ['organizer' => ['company_slot' => 'organizer', 'id' => 'organizer_crm_contact_id', 'label' => 'organizer_contact'], ...]
    ) {}

    /**
     * @return array{available: bool, slots: array}
     */
    public function resolve(Event $event): array
    {
        $available = app()->bound(CrmCompanyContactsProviderInterface::class);
        $contactsProvider = $available ? app(CrmCompanyContactsProviderInterface::class) : null;
        $contactResolver  = app()->bound(CrmContactResolverInterface::class)
            ? app(CrmContactResolverInterface::class)
            : null;

        $slots = [];
        foreach ($this->contactSlots as $slot => $cfg) {
            $companyCfg = $this->companySlots[$cfg['company_slot']] ?? null;
            $companyId = $companyCfg ? $event->{$companyCfg['id']} : null;

            // Fallback: wenn Slot keine Firma hat, nutze Veranstalter-Firma
            if (!$companyId && $slot !== 'organizer' && $slot !== 'organizer_onsite') {
                $orgCfg = $this->companySlots['organizer'] ?? null;
                $orgCompanyId = $orgCfg ? $event->{$orgCfg['id']} : null;
                if ($orgCompanyId) $companyId = $orgCompanyId;
            }

            $currentId = $event->{$cfg['id']};
            $contacts = [];
            $currentLabel = null;

            if ($contactsProvider && $companyId) {
                $contacts = $contactsProvider->contacts((int) $companyId);
                if ($currentId) {
                    foreach ($contacts as $c) {
                        if ((int) ($c['id'] ?? 0) === (int) $currentId) {
                            $currentLabel = $c['name'] ?? null;
                            break;
                        }
                    }
                }
            }

            $currentUrl = $currentId && $contactResolver ? $contactResolver->url((int) $currentId) : null;

            $slots[$slot] = [
                'contacts'     => $contacts,
                'currentId'    => $currentId,
                'currentLabel' => $currentLabel ?: $event->{$cfg['label']},
                'currentUrl'   => $currentUrl,
                'hasCompany'   => (bool) $companyId,
                'fallback'     => $event->{$cfg['label']},
            ];
        }

        return [
            'available' => $available,
            'slots'     => $slots,
        ];
    }
}
