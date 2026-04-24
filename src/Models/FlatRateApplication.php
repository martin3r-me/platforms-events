<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Audit-Trail einer angewendeten Pauschal-Regel. input_snapshot
 *  + result_value sind eingefroren; superseded_at markiert ueberholte
 *  Anwendungen (neues Apply ersetzt alte, statt zu duplizieren).
 */
class FlatRateApplication extends Model
{
    protected $table = 'events_flat_rate_applications';

    protected $fillable = [
        'uuid', 'team_id', 'user_id',
        'rule_id', 'quote_item_id', 'quote_position_id',
        'input_snapshot', 'result_value', 'result_breakdown',
        'superseded_at',
    ];

    protected $casts = [
        'uuid'             => 'string',
        'input_snapshot'   => 'array',
        'result_breakdown' => 'array',
        'result_value'     => 'decimal:2',
        'superseded_at'    => 'datetime',
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

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FlatRateRule::class, 'rule_id');
    }

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }

    public function quotePosition(): BelongsTo
    {
        return $this->belongsTo(QuotePosition::class);
    }

    /**
     * Aktueller Preis der verknuepften QuotePosition (oder null, wenn sie
     * geloescht wurde). Dient der Override-Erkennung.
     */
    public function currentPrice(): ?float
    {
        $pos = $this->quotePosition;
        return $pos ? (float) $pos->preis : null;
    }

    /**
     * True, wenn der PL den Preis der Pauschale-Position manuell veraendert
     * hat (Wert weicht um mehr als 1 Cent vom berechneten result_value ab).
     */
    public function isOverridden(): bool
    {
        $current = $this->currentPrice();
        if ($current === null) return false;
        return abs($current - (float) $this->result_value) > 0.01;
    }
}
