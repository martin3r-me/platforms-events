<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Einstellung als Key/Value-Paar, team-scoped.
 */
class Setting extends Model
{
    use SoftDeletes;

    protected $table = 'events_settings';

    protected $fillable = ['uuid', 'user_id', 'team_id', 'key', 'value'];

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
        });
    }

    public static function getFor(?int $teamId, string $key, mixed $default = null): mixed
    {
        $query = static::where('key', $key);
        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }
        return $query->value('value') ?? $default;
    }

    public static function setFor(?int $teamId, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['team_id' => $teamId, 'key' => $key],
            ['value' => $value]
        );
    }
}
