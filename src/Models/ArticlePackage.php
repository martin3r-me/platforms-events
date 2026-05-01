<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Artikelpaket – gebuendelte Artikel fuer wiederkehrende Buchungen.
 */
class ArticlePackage extends Model
{
    use SoftDeletes;

    protected $table = 'events_article_packages';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'name', 'description', 'color', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'uuid'      => 'string',
        'is_active' => 'boolean',
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

    public function items(): HasMany
    {
        return $this->hasMany(ArticlePackageItem::class, 'package_id')->orderBy('sort_order');
    }
}
