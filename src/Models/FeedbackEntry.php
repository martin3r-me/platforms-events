<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine abgegebene Feedback-Antwort ueber einen FeedbackLink (Ratings 1-5 + Kommentar).
 */
class FeedbackEntry extends Model
{
    protected $table = 'events_feedback_entries';

    protected $fillable = [
        'uuid', 'team_id',
        'feedback_link_id', 'event_id', 'name',
        'rating_overall', 'rating_location', 'rating_catering', 'rating_organization',
        'comment', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'uuid'                => 'string',
        'rating_overall'      => 'integer',
        'rating_location'     => 'integer',
        'rating_catering'     => 'integer',
        'rating_organization' => 'integer',
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

    public function link(): BelongsTo
    {
        return $this->belongsTo(FeedbackLink::class, 'feedback_link_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
