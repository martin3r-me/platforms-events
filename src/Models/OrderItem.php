<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine Bestell-Position (Typ-Gruppe) pro Event-Tag. Enthaelt Einkaufswert, Lieferant, Artikel-/Positionen-Counts und einen Status.
 */
class OrderItem extends Model
{
    use SoftDeletes;

    protected $table = 'events_order_items';

    protected $fillable = [
        'uuid', 'user_id', 'team_id', 'event_day_id',
        'typ', 'status', 'price_mode',
        'artikel', 'positionen', 'einkauf', 'lieferant', 'sort_order',
        // Bestellschein-Empfaenger (externer Dienstleister)
        'crm_company_id', 'crm_contact_id', 'empfaenger_tel', 'bemerkung', 'order_form_mode',
    ];

    protected $casts = [
        'uuid'           => 'string',
        'artikel'        => 'integer',
        'positionen'     => 'integer',
        'einkauf'        => 'decimal:2',
        'sort_order'     => 'integer',
        'crm_company_id' => 'integer',
        'crm_contact_id' => 'integer',
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

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function posList(): HasMany
    {
        return $this->hasMany(OrderPosition::class)->orderBy('sort_order');
    }

    /**
     * Anzeigename des Empfaengers: CRM-Firmenname (falls verknuepft und
     * CRM verfuegbar), sonst der Freitext-Lieferant.
     */
    public function recipientName(): string
    {
        if ($this->crm_company_id) {
            try {
                $resolver = app(\Platform\Core\Contracts\CrmCompanyResolverInterface::class);
                $name = $resolver->displayName((int) $this->crm_company_id);
                if ($name) return $name;
            } catch (\Throwable $e) {
                // CRM nicht verfuegbar -> Fallback
            }
        }
        return (string) ($this->lieferant ?? '');
    }

    /**
     * Ob fuer diesen Vorgang ein Bestellschein relevant ist.
     * Modus 'on'/'off' erzwingt; 'auto' (Default) leitet aus den Positionen ab:
     * mind. eine effektiv-supplier-Position (via ProcurementTypeResolver).
     *
     * @param iterable|null $positions  bereits geladene Positionen (spart Query)
     * @param array|null    $articleLookup  vorgebauter Katalog-Lookup (spart Query)
     */
    public function isOrderFormRelevant($positions = null, ?array $articleLookup = null): bool
    {
        $mode = $this->order_form_mode ?: 'auto';
        if ($mode === 'on')  return true;
        if ($mode === 'off') return false;

        $positions = $positions ?? $this->posList;
        foreach ($positions as $pos) {
            $type = \Platform\Events\Services\ProcurementTypeResolver::resolve(
                $pos->procurement_type,
                (string) ($pos->name ?? ''),
                (int) $this->team_id,
                $articleLookup
            );
            if ($type === 'supplier') return true;
        }
        return false;
    }
}
