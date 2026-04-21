<?php

namespace Platform\Events\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;
use Platform\Core\Contracts\CrmCompanyResolverInterface;
use Platform\Events\Models\Contract;
use Platform\Events\Models\Event;

class ContractRenderer
{
    /**
     * Rendert den Vertragstext nach HTML. Ersetzt Platzhalter mit Event-Daten
     * und laesst Markdown/GFM durch CommonMark laufen (inkl. Bildern).
     */
    public static function renderHtml(Contract $contract, Event $event, string $mode = 'web'): string
    {
        $raw = (string) ($contract->content['text'] ?? '');
        if ($raw === '') return '';

        $filled = self::replacePlaceholders($raw, $event, $contract);
        $filled = self::resolveAssetUrls($filled, $mode);
        return self::markdownToHtml($filled);
    }

    /**
     * Ersetzt events-asset://{disk}/{path}:
     * - mode='web': echte URL (temporaryUrl bei S3, ->url() sonst)
     * - mode='pdf': base64-Data-URL (damit DomPDF nicht vom Netz abhaengig ist)
     */
    public static function resolveAssetUrls(string $text, string $mode = 'web'): string
    {
        return preg_replace_callback(
            '#events-asset://([a-z0-9_-]+)/([^\s\)]+)#i',
            function ($m) use ($mode) {
                $disk = $m[1];
                $path = $m[2];
                try {
                    $storage = Storage::disk($disk);
                    if (!$storage->exists($path)) return $m[0];

                    if ($mode === 'pdf') {
                        $bytes = $storage->get($path);
                        $mime = $storage->mimeType($path) ?: self::guessMimeFromPath($path);
                        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
                    }

                    // Nur bei Disks mit nativer presigned-URL (S3) direkt verlinken.
                    // Fuer 'local' und 'public' immer ueber die signierte Route
                    // gehen, weil storage:link auf dem Server nicht garantiert ist.
                    if ($storage->providesTemporaryUrls()) {
                        return (string) $storage->temporaryUrl($path, now()->addHours(24));
                    }

                    return (string) URL::temporarySignedRoute(
                        'events.public.asset',
                        now()->addHours(24),
                        ['disk' => $disk, 'path' => $path]
                    );
                } catch (\Throwable $e) {
                    return $m[0];
                }
            },
            $text
        );
    }

    protected static function guessMimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png'          => 'image/png',
            'jpg', 'jpeg'  => 'image/jpeg',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'svg'          => 'image/svg+xml',
            default        => 'application/octet-stream',
        };
    }

    /**
     * Nur Platzhalter ersetzen, ohne Markdown zu parsen (z.B. fuer Preview im Editor).
     */
    public static function renderPlaceholders(string $text, Event $event, ?Contract $contract = null): string
    {
        return self::replacePlaceholders($text, $event, $contract);
    }

    public static function markdownToHtml(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $converter = new MarkdownConverter($environment);
        return (string) $converter->convert($markdown);
    }

    /**
     * @return array<string,string>
     */
    public static function availablePlaceholders(): array
    {
        return [
            '{EVENT_NUMBER}'       => 'Veranstaltungsnummer (z.B. VA#2026-031)',
            '{EVENT_NAME}'         => 'Name der Veranstaltung',
            '{EVENT_TYPE}'         => 'Anlass-Typ',
            '{EVENT_DATE}'         => 'Startdatum, TT.MM.JJJJ',
            '{EVENT_DATE_END}'     => 'Enddatum, TT.MM.JJJJ',
            '{EVENT_DATES}'        => 'Liste aller Veranstaltungstage',
            '{CUSTOMER_COMPANY}'   => 'Veranstalter (Firma / Kunde)',
            '{CUSTOMER_CONTACT}'   => 'Ansprechpartner Veranstalter',
            '{CUSTOMER_FOR_WHOM}'  => 'Veranstalter für (ausgeschriebener Name)',
            '{INVOICE_COMPANY}'    => 'Rechnungsempfänger-Firma',
            '{INVOICE_CONTACT}'    => 'Rechnungsempfänger-Ansprechpartner',
            '{DELIVERY_COMPANY}'   => 'Lieferadresse-Firma',
            '{DELIVERY_CONTACT}'   => 'Lieferadresse-Ansprechpartner',
            '{LOCATION}'           => 'Event-Location',
            '{ROOMS}'              => 'Gebuchte Räume (Liste)',
            '{SETUP_DATE}'         => 'Aufbau-Start (erster Event-Tag)',
            '{SETUP_TIME}'         => 'Aufbau-Startzeit (erster Booking-Eintrag)',
            '{TEARDOWN_DATE}'      => 'Abbau-Ende (letzter Event-Tag)',
            '{TEARDOWN_TIME}'      => 'Abbau-Endzeit (letzter Booking-Eintrag)',
            '{RESPONSIBLE}'        => 'Verantwortlich',
            '{COST_CENTER}'        => 'Kostenstelle',
            '{COST_CARRIER}'       => 'Kostenträger',
            '{SIGN_LEFT_NAME}'     => 'Unterschrift-Label links',
            '{SIGN_RIGHT_NAME}'    => 'Unterschrift-Label rechts',
            '{CONTRACT_DATE}'      => 'Vertragsdatum (Heute)',
            '{CONTRACT_VERSION}'   => 'Vertrags-Version',
            '{TODAY}'              => 'Heutiges Datum, TT.MM.JJJJ',
        ];
    }

    protected static function replacePlaceholders(string $text, Event $event, ?Contract $contract): string
    {
        $event->loadMissing(['days', 'bookings.location']);
        $map = self::buildValueMap($event, $contract);

        // Unterstuetze sowohl {KEY} als auch {{KEY}}-Notation
        return preg_replace_callback('/\{\{?\s*([A-Z_][A-Z0-9_]*)\s*\}?\}/', function ($m) use ($map) {
            $key = '{' . $m[1] . '}';
            return array_key_exists($key, $map) ? $map[$key] : $m[0];
        }, $text);
    }

    /**
     * @return array<string,string>
     */
    protected static function buildValueMap(Event $event, ?Contract $contract): array
    {
        $days = $event->days ?? collect();
        $bookings = $event->bookings ?? collect();

        return [
            '{EVENT_NUMBER}'      => (string) ($event->event_number ?? ''),
            '{EVENT_NAME}'        => (string) ($event->name ?? ''),
            '{EVENT_TYPE}'        => (string) ($event->event_type ?? ''),
            '{EVENT_DATE}'        => self::dateString($event->start_date),
            '{EVENT_DATE_END}'    => self::dateString($event->end_date),
            '{EVENT_DATES}'       => self::formatEventDates($days, $event),
            '{CUSTOMER_COMPANY}'  => self::companyName($event->customer, $event->crm_company_id),
            '{CUSTOMER_CONTACT}'  => (string) ($event->organizer_contact ?? ''),
            '{CUSTOMER_FOR_WHOM}' => (string) ($event->organizer_for_whom ?? ''),
            '{INVOICE_COMPANY}'   => self::companyName($event->invoice_to, $event->invoice_crm_company_id),
            '{INVOICE_CONTACT}'   => (string) ($event->invoice_contact ?? ''),
            '{DELIVERY_COMPANY}'  => self::companyName($event->delivery_supplier, $event->delivery_crm_company_id),
            '{DELIVERY_CONTACT}'  => (string) ($event->delivery_contact ?? ''),
            '{LOCATION}'          => (string) ($event->location ?? ''),
            '{ROOMS}'             => self::formatRooms($bookings),
            '{SETUP_DATE}'        => self::dateString(optional($days->first())->datum ?? $event->start_date),
            '{SETUP_TIME}'        => (string) (optional($bookings->sortBy('beginn')->first())->beginn ?? ''),
            '{TEARDOWN_DATE}'     => self::dateString(optional($days->last())->datum ?? $event->end_date),
            '{TEARDOWN_TIME}'     => (string) (optional($bookings->sortByDesc('ende')->first())->ende ?? ''),
            '{RESPONSIBLE}'       => (string) ($event->responsible ?? ''),
            '{COST_CENTER}'       => (string) ($event->cost_center ?? ''),
            '{COST_CARRIER}'      => (string) ($event->cost_carrier ?? ''),
            '{SIGN_LEFT_NAME}'    => (string) ($event->sign_left ?? ''),
            '{SIGN_RIGHT_NAME}'   => (string) ($event->sign_right ?? ''),
            '{CONTRACT_DATE}'     => $contract ? self::dateString($contract->created_at) : now()->format('d.m.Y'),
            '{CONTRACT_VERSION}'  => $contract ? 'v' . ($contract->version ?? 1) : '',
            '{TODAY}'             => now()->format('d.m.Y'),
        ];
    }

    protected static function dateString($date): string
    {
        if (!$date) return '';
        try {
            return Carbon::parse($date)->format('d.m.Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    protected static function formatEventDates(Collection $days, Event $event): string
    {
        if ($days->isEmpty()) {
            $start = self::dateString($event->start_date);
            $end   = self::dateString($event->end_date);
            if ($start && $end && $start !== $end) return "{$start} – {$end}";
            return $start;
        }

        return $days->map(function ($d) {
            $date = self::dateString($d->datum);
            return $d->day_of_week ? "{$date} ({$d->day_of_week})" : $date;
        })->implode(', ');
    }

    protected static function formatRooms(Collection $bookings): string
    {
        if ($bookings->isEmpty()) return '';

        return $bookings->map(function ($b) {
            $name = $b->location?->name ?: ($b->raum ?: '');
            $short = $b->location?->kuerzel ?: '';
            $date = self::dateString($b->datum);
            $times = trim(($b->beginn ? $b->beginn : '') . ($b->ende ? ' – ' . $b->ende : ''));
            $pers = $b->pers ? "{$b->pers} Pers." : '';
            $label = trim(($short ? $short . ' — ' : '') . $name);
            $meta = array_filter([$date, $times, $pers]);
            return '- ' . $label . ($meta ? ' · ' . implode(' · ', $meta) : '');
        })->implode("\n");
    }

    protected static function companyName(?string $fallback, ?int $crmCompanyId): string
    {
        if ($crmCompanyId) {
            try {
                $resolver = app(CrmCompanyResolverInterface::class);
                $name = $resolver->displayName($crmCompanyId);
                if ($name) return $name;
            } catch (\Throwable $e) {
                // CRM nicht verfuegbar -> Fallback
            }
        }
        return (string) ($fallback ?? '');
    }
}
