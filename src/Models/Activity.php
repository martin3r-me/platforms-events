<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Aktivitaetseintrag zu einem Event (Log-Zeile: Typ, Beschreibung, User).
 */
class Activity extends Model
{
    protected $table = 'events_activities';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_id',
        'type', 'description', 'user',
    ];

    protected $casts = ['uuid' => 'string'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
