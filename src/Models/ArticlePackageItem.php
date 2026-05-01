<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class ArticlePackageItem extends Model
{
    use SoftDeletes;

    protected $table = 'events_article_package_items';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'package_id', 'article_id',
        'name', 'gruppe', 'quantity', 'gebinde',
        'vk', 'gesamt', 'sort_order',
    ];

    protected $casts = [
        'uuid'     => 'string',
        'quantity' => 'integer',
        'vk'       => 'decimal:2',
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(ArticlePackage::class, 'package_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(\Platform\Commerce\Models\CommerceArticle::class, 'article_id');
    }
}
