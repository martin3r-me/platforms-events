<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine Bestell-Position (Typ-Gruppe) pro Event-Tag. Enthaelt Einkaufswert, Lieferant, Artikel-/Positionen-Counts und einen Status.
 */
class OrderItem extends Model
{
    use SoftDeletes;

    protected $table = 'events_order_items';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_day_id',
        'typ', 'status', 'price_mode',
        'artikel', 'positionen', 'einkauf', 'lieferant', 'sort_order',
    ];

    protected $casts = [
        'uuid'       => 'string',
        'artikel'    => 'integer',
        'positionen' => 'integer',
        'einkauf'    => 'decimal:2',
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

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function posList(): HasMany
    {
        return $this->hasMany(OrderPosition::class)->orderBy('sort_order');
    }
}
