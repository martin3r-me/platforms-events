<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Ein Event (Veranstaltung) mit Stammdaten, Zeitraum, Status, Veranstalter/Besteller/Rechnung, Management-Report. Hat Tage, Buchungen, Ablaufplan und Notizen.
 */
class Event extends Model
{
    use SoftDeletes;

    protected $table = 'events_events';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',

        'event_number', 'name', 'customer', 'crm_company_id', 'group', 'location',
        'start_date', 'end_date',
        'status', 'status_changed_at',

        // Veranstalter
        'organizer_contact', 'organizer_crm_contact_id',
        'organizer_contact_onsite', 'organizer_onsite_crm_contact_id',
        'organizer_for_whom',
        // Besteller
        'orderer_company', 'orderer_contact', 'orderer_via',
        'orderer_crm_company_id', 'orderer_crm_contact_id',
        // Rechnung
        'invoice_to', 'invoice_crm_company_id', 'invoice_contact', 'invoice_crm_contact_id', 'invoice_date_type',
        // Zuständigkeit
        'responsible', 'responsible_onsite', 'cost_center', 'cost_carrier',
        // Anlass
        'event_type',
        // Unterschriften
        'sign_left', 'sign_right',
        // Management Report
        'mr_data',
        // Schlussbericht / Nachbewertung
        'internal_rating', 'customer_satisfaction', 'rebooking_recommendation',
        // Wiedervorlage
        'follow_up_date', 'follow_up_note',
        // Lieferung
        'delivery_address', 'delivery_address_crm_company_id', 'delivery_location_id', 'delivery_note',
        // Eingang
        'inquiry_date', 'inquiry_time', 'inquiry_note', 'potential',
        // Weiterleitung
        'forwarded', 'forwarding_date', 'forwarding_time',
    ];

    protected $casts = [
        'uuid'              => 'string',
        'start_date'        => 'date:Y-m-d',
        'end_date'          => 'date:Y-m-d',
        'status_changed_at' => 'datetime',
        'mr_data'           => 'array',
        'follow_up_date'    => 'date:Y-m-d',
        'inquiry_date'      => 'date:Y-m-d',
        'forwarding_date'   => 'date:Y-m-d',
        'forwarded'         => 'boolean',
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

        static::updating(function (self $event) {
            if ($event->isDirty('status')) {
                $event->status_changed_at = now();
            }
        });

        static::created(function (self $event) {
            \Platform\Events\Services\ActivityLogger::eventCreated($event);
        });

        static::updated(function (self $event) {
            if ($event->wasChanged('status')) {
                \Platform\Events\Services\ActivityLogger::statusChanged(
                    $event,
                    $event->getOriginal('status'),
                    $event->status
                );
            }
        });
    }

    /**
     * URL-Slug: event_number ohne "#" (z.B. VA2026-031).
     */
    public function getSlugAttribute(): string
    {
        return str_replace('#', '', (string) $this->event_number);
    }

    /**
     * Auflösen eines Events aus URL-Slug oder event_number innerhalb des aktuellen Teams.
     */
    public static function resolveFromSlug(string $slug, ?int $teamId = null): ?self
    {
        $query = static::query();
        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        return $query->where(function ($q) use ($slug) {
            $q->where('event_number', $slug)
              ->orWhere('event_number', preg_replace('/^(VA)(\d)/', '$1#$2', $slug));
        })->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function deliveryLocation(): BelongsTo
    {
        return $this->belongsTo(\Platform\Locations\Models\Location::class, 'delivery_location_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(EventDay::class)->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->orderBy('sort_order');
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(ScheduleItem::class)->orderBy('sort_order');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EventNote::class)->latest();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->latest();
    }

    public function pickLists(): HasMany
    {
        return $this->hasMany(PickList::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class);
    }

    public function feedbackLinks(): HasMany
    {
        return $this->hasMany(FeedbackLink::class);
    }

    public function feedbackEntries(): HasMany
    {
        return $this->hasMany(FeedbackEntry::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Aggregierter Umsatz aus QuoteItems ueber alle Tage.
     */
    public function quoteItems(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(QuoteItem::class, EventDay::class);
    }

    /**
     * Aggregierte Bestellung aus OrderItems ueber alle Tage.
     */
    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, EventDay::class);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
