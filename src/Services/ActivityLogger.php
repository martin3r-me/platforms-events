<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Activity;
use Platform\Events\Models\Event;

/**
 * Einfache Activity-Log-Helper. Wird manuell aus Livewire-Components oder
 * Model-Hooks aufgerufen, um relevante Aktionen im Event-Log zu erfassen.
 *
 * Vollautomatisches Audit via Observer-Pattern kommt in einer spaeteren
 * Ausbaustufe – hier reicht manuelles Logging.
 */
class ActivityLogger
{
    public static function log(Event $event, string $type, string $description): Activity
    {
        $user = Auth::user();

        return Activity::create([
            'event_id'    => $event->id,
            'team_id'     => $event->team_id,
            'user_id'     => $user?->id,
            'type'        => $type,
            'description' => $description,
            'user'        => $user?->name ?? 'System',
        ]);
    }

    public static function statusChanged(Event $event, ?string $oldStatus, ?string $newStatus): Activity
    {
        return self::log($event, 'status', "Status: „{$oldStatus}“ → „{$newStatus}“");
    }

    public static function eventCreated(Event $event): Activity
    {
        return self::log($event, 'created', "Event „{$event->name}“ angelegt");
    }

    public static function fieldChanged(Event $event, string $field, $oldValue, $newValue): Activity
    {
        $old = is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue);
        $new = is_scalar($newValue) ? (string) $newValue : json_encode($newValue);
        return self::log($event, 'updated', "{$field}: „{$old}“ → „{$new}“");
    }
}
