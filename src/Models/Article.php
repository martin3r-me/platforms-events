<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Artikel-Stammdatensatz. Gehoert einer Artikelgruppe an, hat EK/VK/MwSt/Erloeskonto und Bestandsdaten.
 */
class Article extends Model
{
    use SoftDeletes;

    protected $table = 'events_articles';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'article_group_id',
        'article_number', 'external_code', 'name', 'description',
        'offer_text', 'invoice_text', 'gebinde',
        'ek', 'vk', 'mwst', 'erloeskonto',
        'lagerort', 'min_bestand', 'current_bestand',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'uuid'      => 'string',
        'is_active' => 'boolean',
        'ek'        => 'decimal:2',
        'vk'        => 'decimal:2',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(ArticleGroup::class, 'article_group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Effektives Erloeskonto: Artikel > Gruppe (nach MwSt-Satz) > Default.
     */
    public function getEffectiveErloeskontoAttribute(): string
    {
        if (!empty($this->erloeskonto)) {
            return $this->erloeskonto;
        }
        if ($this->group) {
            $rate = (int) $this->mwst;
            return $rate === 7
                ? ($this->group->erloeskonto_7 ?: '8300')
                : ($this->group->erloeskonto_19 ?: '8400');
        }
        return ((int) $this->mwst) === 7 ? '8300' : '8400';
    }
}
