<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description In-App-Benachrichtigung fuer einen User (Event-Status, Vertrag, Rechnung, Zuweisung, ...).
 */
class AppNotification extends Model
{
    protected $table = 'events_app_notifications';

    protected $fillable = ['uuid', 'team_id', 'user_id', 'type', 'title', 'body', 'link', 'icon'];

    protected $casts = [
        'uuid'    => 'string',
        'read_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}
