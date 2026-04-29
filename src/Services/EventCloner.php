<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;
use Platform\Events\Models\Event;

/**
 * Klont ein Event inkl. konfigurierbarem Umfang (Days, Bookings, ScheduleItems,
 * EventNotes). Quotes/Orders werden bewusst NICHT mitkopiert – ein neues Event
 * bekommt typischerweise ein neues Angebot.
 *
 * Spiegelt die UI-Logik aus Livewire\Detail::duplicate(), nur als reusable
 * Service mit Optionen + Override-Feldern.
 */
class EventCloner
{
    /**
     * @param array{
     *     name?: string|null,
     *     status?: string|null,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     customer?: string|null,
     *     event_type?: string|null,
     *     responsible?: string|null,
     *     responsible_onsite?: string|null,
     *     crm_company_id?: int|null,
     *     orderer_crm_company_id?: int|null,
     *     orderer_crm_contact_id?: int|null,
     *     invoice_crm_company_id?: int|null,
     *     invoice_crm_contact_id?: int|null,
     *     follow_up_date?: string|null,
     *     follow_up_note?: string|null,
     * }|array<string,mixed> $overrides
     * @param array{
     *     days?: bool,
     *     bookings?: bool,
     *     schedule_items?: bool,
     *     notes?: bool,
     * }|array<string,bool> $include
     */
    public static function clone(Event $source, User $actor, array $overrides = [], array $include = []): Event
    {
        $include = array_merge([
            'days'           => true,
            'bookings'       => true,
            'schedule_items' => true,
            'notes'          => false,
        ], $include);

        return DB::transaction(function () use ($source, $actor, $overrides, $include) {
            $copy = $source->replicate(['uuid', 'event_number', 'status_changed_at']);
            $copy->event_number      = EventFactory::nextEventNumber($source->team_id);
            $copy->name              = trim((string) ($overrides['name'] ?? ($source->name . ' (Kopie)')));
            $copy->status            = (string) ($overrides['status'] ?? 'Option');
            $copy->status_changed_at = now();
            $copy->user_id           = $actor->id;

            // Override-Felder direkt durchreichen (nur wenn vorhanden, sonst Quelle).
            foreach ([
                'start_date', 'end_date', 'customer', 'event_type',
                'responsible', 'responsible_onsite',
                'follow_up_date', 'follow_up_note',
            ] as $field) {
                if (array_key_exists($field, $overrides)) {
                    $copy->{$field} = $overrides[$field];
                }
            }
            // Integer-FKs (CRM/Locations) – null setzbar
            foreach ([
                'crm_company_id',
                'organizer_crm_contact_id', 'organizer_onsite_crm_contact_id',
                'orderer_crm_company_id', 'orderer_crm_contact_id',
                'invoice_crm_company_id', 'invoice_crm_contact_id',
                'delivery_address_crm_company_id', 'delivery_location_id',
            ] as $fk) {
                if (array_key_exists($fk, $overrides)) {
                    $copy->{$fk} = $overrides[$fk] !== null && $overrides[$fk] !== ''
                        ? (int) $overrides[$fk]
                        : null;
                }
            }
            // Wiedervorlage / Eingang ist beim Klon meist nicht mehr relevant –
            // nur uebernehmen, wenn explizit angefordert.
            if (!array_key_exists('inquiry_date', $overrides)) {
                $copy->inquiry_date = null;
            }

            $copy->save();

            if ($include['days']) {
                foreach ($source->days as $day) {
                    $dayCopy = $day->replicate(['uuid']);
                    $dayCopy->event_id = $copy->id;
                    $dayCopy->user_id  = $actor->id;
                    $dayCopy->save();
                }
            }
            if ($include['bookings']) {
                foreach ($source->bookings as $booking) {
                    $bookingCopy = $booking->replicate(['uuid']);
                    $bookingCopy->event_id = $copy->id;
                    $bookingCopy->user_id  = $actor->id;
                    $bookingCopy->save();
                }
            }
            if ($include['schedule_items']) {
                foreach ($source->scheduleItems as $item) {
                    $itemCopy = $item->replicate(['uuid']);
                    $itemCopy->event_id = $copy->id;
                    $itemCopy->user_id  = $actor->id;
                    $itemCopy->save();
                }
            }
            if ($include['notes']) {
                foreach ($source->notes as $note) {
                    $noteCopy = $note->replicate(['uuid']);
                    $noteCopy->event_id = $copy->id;
                    $noteCopy->user_id  = $actor->id;
                    $noteCopy->save();
                }
            }

            return $copy->refresh();
        });
    }
}
