<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class InvoiceItem extends Model
{
    use SoftDeletes;

    protected $table = 'events_invoice_items';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'invoice_id', 'article_id',
        'gruppe', 'name', 'description',
        'quantity', 'quantity2', 'gebinde',
        'unit_price', 'mwst_rate', 'total',
        'sort_order',
    ];

    protected $casts = [
        'uuid'       => 'string',
        'quantity'   => 'decimal:2',
        'quantity2'  => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total'      => 'decimal:2',
        'mwst_rate'  => 'integer',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
