<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class PickItem extends Model
{
    use SoftDeletes;

    protected $table = 'events_pick_items';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'pick_list_id', 'article_id',
        'name', 'quantity', 'gebinde', 'gruppe', 'lagerort',
        'status', 'picked_by', 'picked_at', 'sort_order',
    ];

    protected $casts = [
        'uuid'      => 'string',
        'picked_at' => 'datetime',
        'quantity'  => 'integer',
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

    public function pickList(): BelongsTo
    {
        return $this->belongsTo(PickList::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
