<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine Raumbuchung innerhalb eines Events. Bevorzugt wird location_id (FK auf Locations-Modul); das raum-Feld ist Fallback-String für Legacy-Daten. Enthält Datum, Zeiten, Personenzahl, Bestuhlung und Optionsrang.
 */
class Booking extends Model
{
    use SoftDeletes;

    protected $table = 'events_bookings';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'event_id',
        'location_id',
        'raum',
        'datum',
        'beginn',
        'ende',
        'pers',
        'bestuhlung',
        'optionsrang',
        'absprache',
        'sort_order',
    ];

    protected $casts = [
        'uuid'       => 'string',
        'sort_order' => 'integer',
    ];

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

    public function location(): BelongsTo
    {
        return $this->belongsTo(\Platform\Locations\Models\Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Anzeigename: bevorzugt Location-Kürzel, sonst raum-String.
     */
    public function getDisplayRoomAttribute(): ?string
    {
        if ($this->location_id && $this->relationLoaded('location') && $this->location) {
            return $this->location->kuerzel ?: $this->location->name;
        }
        return $this->raum;
    }
}
