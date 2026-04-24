<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine Angebots-Position (Typ-Gruppe) pro Event-Tag. Enthaelt Artikel-/Positionen-Counts, Umsatz, MwSt und einen Status.
 */
class QuoteItem extends Model
{
    use SoftDeletes;

    protected $table = 'events_quote_items';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_day_id',
        'typ', 'status', 'price_mode',
        'artikel', 'positionen', 'umsatz', 'mwst', 'sort_order',
    ];

    protected $casts = [
        'uuid'       => 'string',
        'artikel'    => 'integer',
        'positionen' => 'integer',
        'umsatz'     => 'decimal:2',
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
        return $this->hasMany(QuotePosition::class)->orderBy('sort_order');
    }
}
