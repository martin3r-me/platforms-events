<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Dokumentenvorlage fuer Vertraege/Optionsbestaetigungen/etc. – mit Platzhaltern und HTML-Inhalt.
 */
class DocumentTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'events_document_templates';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'label', 'slug', 'description', 'color',
        'placeholders', 'html_content', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'uuid'         => 'string',
        'placeholders' => 'array',
        'is_active'    => 'boolean',
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
