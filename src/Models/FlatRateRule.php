<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Pauschal-Kalkulations-Regel, team-scoped. Scope via scope_typs
 *  (QuoteItem.typ-Liste) + optional scope_event_types. Formel als
 *  Symfony-ExpressionLanguage-Body; Output landet als eine QuotePosition.
 */
class FlatRateRule extends Model
{
    use SoftDeletes;

    protected $table = 'events_flat_rate_rules';

    protected $fillable = [
        'uuid', 'team_id', 'user_id',
        'name', 'description',
        'scope_typs', 'scope_event_types',
        'formula',
        'output_name', 'output_gruppe', 'output_mwst', 'output_procurement_type',
        'priority', 'is_active',
        'last_error', 'last_error_at',
    ];

    protected $casts = [
        'uuid'              => 'string',
        'scope_typs'        => 'array',
        'scope_event_types' => 'array',
        'priority'          => 'integer',
        'is_active'         => 'boolean',
        'last_error_at'     => 'datetime',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(FlatRateApplication::class, 'rule_id');
    }

    /**
     * True, wenn die Regel auf den gegebenen QuoteItem-Typ + Event-Typ greift.
     */
    public function matches(string $vorgangsTyp, ?string $eventType): bool
    {
        $typs = array_map(fn ($t) => mb_strtolower(trim((string) $t)), $this->scope_typs ?? []);
        if (!in_array(mb_strtolower(trim($vorgangsTyp)), $typs, true)) {
            return false;
        }

        $eventTypes = array_filter(array_map(fn ($t) => trim((string) $t), $this->scope_event_types ?? []));
        if (empty($eventTypes)) return true;

        return in_array((string) $eventType, $eventTypes, true);
    }
}
