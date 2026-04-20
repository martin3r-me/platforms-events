<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Dynamische Konfiguration der Management-Report-Felder (Gruppen, Label, Auswahl-Optionen).
 */
class MrFieldConfig extends Model
{
    use SoftDeletes;

    protected $table = 'events_mr_field_configs';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'group_label', 'label', 'options', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'uuid'      => 'string',
        'options'   => 'array',
        'is_active' => 'boolean',
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
}
