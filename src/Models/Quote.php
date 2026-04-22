<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Angebot zu einem Event. Versioniert ueber parent_id + is_current. Public-Zugriff via token.
 */
class Quote extends Model
{
    use SoftDeletes;

    protected $table = 'events_quotes';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_id',
        'token', 'status', 'valid_until',
        'sent_at', 'responded_at', 'response_note',
        'last_viewed_at', 'view_count',
        'version', 'parent_id', 'is_current', 'pdf_snapshot',
        'approval_status', 'approver_id', 'approval_requested_by',
        'approval_requested_at', 'approval_decided_at', 'approval_comment',
    ];

    protected $casts = [
        'uuid'                  => 'string',
        'valid_until'           => 'date',
        'sent_at'               => 'datetime',
        'responded_at'          => 'datetime',
        'last_viewed_at'        => 'datetime',
        'is_current'            => 'boolean',
        'approval_requested_at' => 'datetime',
        'approval_decided_at'   => 'datetime',
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
            if (empty($model->token)) {
                $model->token = Str::random(48);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getRootParentId(): int
    {
        return $this->parent_id ?? $this->id;
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'approver_id');
    }

    public function approvalRequester(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'approval_requested_by');
    }
}
