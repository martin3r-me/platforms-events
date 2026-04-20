<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Unterschrift zu einem Event, role = left | right. Enthaelt Base64-PNG, SHA-256 Hash des Dokumenten-Snapshots, IP + User-Agent.
 */
class DocumentSignature extends Model
{
    use SoftDeletes;

    protected $table = 'events_document_signatures';

    protected $fillable = [
        'uuid', 'team_id',
        'event_id', 'user_id', 'role',
        'signature_image', 'document_hash',
        'ip_address', 'user_agent', 'signed_at',
    ];

    protected $casts = [
        'uuid'      => 'string',
        'signed_at' => 'datetime',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }
}
