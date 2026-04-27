<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Audit-Trail einer Location-Pricing-Einbuchung in einen
 *  QuoteItem. input_snapshot enthaelt EventDay.day_type, gewaehlte Pricing-
 *  und Addon-IDs, Warnings; created_positions verweist auf die erzeugten
 *  QuotePositions. superseded_at markiert ueberholte Anwendungen, sodass
 *  Re-Apply alte Eintraege ersetzt statt dupliziert.
 */
class LocationPricingApplication extends Model
{
    use SoftDeletes;

    protected $table = 'events_location_pricing_applications';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'quote_item_id',
        'location_id',
        'input_snapshot',
        'created_positions',
        'superseded_at',
    ];

    protected $casts = [
        'uuid'              => 'string',
        'input_snapshot'    => 'array',
        'created_positions' => 'array',
        'superseded_at'     => 'datetime',
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

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }

    /**
     * Cross-Modul-Relation auf das Locations-Modul. Optional (Location kann
     * zwischenzeitlich geloescht worden sein).
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(\Platform\Locations\Models\Location::class);
    }

    public function isActive(): bool
    {
        return $this->superseded_at === null;
    }

    /**
     * Liefert die IDs der QuotePositions, die diese Anwendung erzeugt hat.
     *
     * @return array<int, int>
     */
    public function quotePositionIds(): array
    {
        $created = is_array($this->created_positions) ? $this->created_positions : [];
        return collect($created)
            ->pluck('quote_position_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }
}
