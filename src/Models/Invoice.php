<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Rechnung zu einem Event mit Typ (rechnung|teilrechnung|schlussrechnung|gutschrift|storno) und Versionierung. Public via token. Totals werden aus Items berechnet.
 */
class Invoice extends Model
{
    use SoftDeletes;

    protected $table = 'events_invoices';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'event_id', 'quote_id',
        'invoice_number', 'type', 'status',
        'customer_company', 'customer_contact', 'customer_address', 'customer_city',
        'invoice_date', 'due_date', 'payment_date', 'payment_reference',
        'netto', 'mwst_7', 'mwst_19', 'brutto',
        'notes', 'internal_notes',
        'cost_center', 'cost_carrier',
        'related_invoice_id',
        'pdf_snapshot', 'token',
        'version', 'parent_id', 'is_current',
        'view_count', 'last_viewed_at',
        'sent_at', 'reminded_at', 'reminder_level',
        'created_by',
    ];

    protected $casts = [
        'uuid'           => 'string',
        'invoice_date'   => 'date',
        'due_date'       => 'date',
        'payment_date'   => 'date',
        'sent_at'        => 'datetime',
        'reminded_at'    => 'datetime',
        'last_viewed_at' => 'datetime',
        'is_current'     => 'boolean',
        'netto'          => 'decimal:2',
        'mwst_7'         => 'decimal:2',
        'mwst_19'        => 'decimal:2',
        'brutto'         => 'decimal:2',
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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_invoice_id');
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

    /**
     * Totale aus Items neu berechnen.
     */
    public function recalculate(): void
    {
        $items = $this->items()->get();
        $netto7  = (float) $items->where('mwst_rate', 7)->sum('total');
        $netto19 = (float) $items->where('mwst_rate', 19)->sum('total');

        $this->update([
            'netto'   => $netto7 + $netto19,
            'mwst_7'  => round($netto7 * 0.07, 2),
            'mwst_19' => round($netto19 * 0.19, 2),
            'brutto'  => round($netto7 * 1.07 + $netto19 * 1.19, 2),
        ]);
    }
}
