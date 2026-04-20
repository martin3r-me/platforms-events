<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Ausgehende E-Mail als Audit-Eintrag (Typ, Empfaenger, Betreff, Status, Tracking-Token).
 */
class EmailLog extends Model
{
    protected $table = 'events_email_log';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_id',
        'type', 'to', 'cc', 'subject', 'body',
        'attachment_name', 'status', 'sent_by', 'tracking_token',
    ];

    protected $casts = [
        'uuid'      => 'string',
        'opened_at' => 'datetime',
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
            if (empty($model->tracking_token)) {
                $model->tracking_token = Str::random(48);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
