<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Oeffentlich teilbarer Feedback-Link zu einem Event (Teilnehmer/Kunde/Dienstleister). Token-basiert.
 */
class FeedbackLink extends Model
{
    use SoftDeletes;

    protected $table = 'events_feedback_links';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_id',
        'label', 'audience', 'token',
        'view_count', 'last_viewed_at', 'is_active',
    ];

    protected $casts = [
        'uuid'           => 'string',
        'last_viewed_at' => 'datetime',
        'is_active'      => 'boolean',
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

    public function entries(): HasMany
    {
        return $this->hasMany(FeedbackEntry::class);
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
        $this->update(['last_viewed_at' => now()]);
    }
}
