<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Einzelne Angebots-Position innerhalb eines QuoteItem (Gruppe/Name/Anzahl/Uhrzeit/Preise/MwSt/Bemerkung).
 */
class QuotePosition extends Model
{
    use SoftDeletes;

    protected $table = 'events_quote_positionen';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'quote_item_id',
        'gruppe', 'name', 'anz', 'anz2',
        'uhrzeit', 'bis', 'inhalt', 'gebinde',
        'basis_ek', 'ek', 'preis', 'mwst', 'gesamt',
        'bemerkung', 'sort_order', 'procurement_type',
        'beverage_mode',
    ];

    protected $casts = [
        'uuid'     => 'string',
        'basis_ek' => 'decimal:2',
        'ek'       => 'decimal:2',
        'preis'    => 'decimal:2',
        'gesamt'   => 'decimal:2',
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
     * Effektiver Getraenke-Modus: Position-Override wenn gesetzt, sonst
     * Vorgang-Default vom QuoteItem, sonst null (kein Modus).
     */
    public function effectiveBeverageMode(): ?string
    {
        if (!empty($this->beverage_mode)) {
            return $this->beverage_mode;
        }
        // Bevorzugt geladene Relation, sonst lazy load
        $item = $this->relationLoaded('quoteItem') ? $this->quoteItem : $this->quoteItem()->first();
        $mode = $item?->beverage_mode;
        return !empty($mode) ? $mode : null;
    }

    /**
     * True, wenn die Position einen Position-eigenen Override gesetzt hat
     * (also nicht nur den Vorgang-Default erbt).
     */
    public function hasBeverageModeOverride(): bool
    {
        return !empty($this->beverage_mode);
    }
}
