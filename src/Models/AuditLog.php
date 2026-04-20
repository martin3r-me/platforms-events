<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Aenderungsprotokoll zu allen Event-bezogenen Records. Enthaelt Typ, ID, Event-ID, Aktion, Aenderungen (field: [old, new]).
 */
class AuditLog extends Model
{
    protected $table = 'events_audit_log';
    public $timestamps = false;

    protected $fillable = [
        'uuid', 'team_id',
        'auditable_type', 'auditable_id', 'event_id',
        'user_id', 'user_name', 'action', 'changes', 'created_at',
    ];

    protected $casts = [
        'uuid'       => 'string',
        'changes'    => 'array',
        'created_at' => 'datetime',
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
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
