<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Packliste zu einem Event. Enthaelt PickItems mit Artikeln, Mengen, Gebinden und Pack-Status.
 */
class PickList extends Model
{
    use SoftDeletes;

    protected $table = 'events_pick_lists';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_id',
        'title', 'status', 'token', 'created_by',
    ];

    protected $casts = ['uuid' => 'string'];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
            if (empty($model->token)) {
                $model->token = Str::random(48);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickItem::class)->orderBy('sort_order');
    }
}
