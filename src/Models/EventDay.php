<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Ein Veranstaltungstag innerhalb eines Events. Enthält Datum, Zeiten (von/bis), Personenbereich (pers_von/pers_bis), Label, Farbe und Tagesstatus.
 */
class EventDay extends Model
{
    use SoftDeletes;

    protected $table = 'events_event_days';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'event_id',
        'label',
        'day_type',
        'datum',
        'color',
        'day_of_week',
        'von',
        'bis',
        'pers_von',
        'pers_bis',
        'day_status',
        'sort_order',
    ];

    protected $casts = [
        'uuid'       => 'string',
        'datum'      => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order');
    }
}
