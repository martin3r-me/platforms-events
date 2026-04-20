<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine Notiz zu einem Event. type gibt die Kategorie an: liefertext | absprache | vereinbarung.
 */
class EventNote extends Model
{
    use SoftDeletes;

    protected $table = 'events_event_notes';

    public const TYPE_LIEFERTEXT   = 'liefertext';
    public const TYPE_ABSPRACHE    = 'absprache';
    public const TYPE_VEREINBARUNG = 'vereinbarung';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'event_id',
        'type',
        'text',
        'user_name',
    ];

    protected $casts = [
        'uuid' => 'string',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }
}
