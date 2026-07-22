<?php

namespace Platform\Events\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\CrmCompanyContactsProviderInterface;
use Platform\Events\Models\DocumentTemplate;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;

/**
 * Rendert den Bestellschein (AUFTRAG) fuer einen externen Dienstleister nach
 * HTML. Die editierbare Vorlage liegt als DocumentTemplate mit slug
 * "bestellschein" (pflegbar im Einstellungen-Tab); fehlt sie, wird eine
 * Default-Vorlage (nachgebaut aus dem Beispiel-PDF) angelegt.
 *
 * Die Positions-Tabelle wird als fertiger HTML-Block ueber {POSITIONEN_TABELLE}
 * injiziert, weil einfache Platzhalter-Ersetzung keine Schleifen kann. Kopf,
 * Anrede und Fuss bleiben voll editierbar.
 */
class OrderFormRenderer
{
    public const SLUG = 'bestellschein';

    /**
     * Liefert die aktive Bestellschein-Vorlage des Teams. Legt bei Bedarf die
     * Default-Vorlage an.
     */
    public static function templateFor(?int $teamId): ?DocumentTemplate
    {
        if (!$teamId) return null;

        $tpl = DocumentTemplate::where('team_id', $teamId)
            ->where('slug', self::SLUG)
            ->orderByDesc('is_active')
            ->first();

        if ($tpl) return $tpl;

        return DocumentTemplate::create([
            'team_id'      => $teamId,
            'user_id'      => Auth::id(),
            'label'        => 'Bestellschein (Dienstleister)',
            'slug'         => self::SLUG,
            'description'  => 'AUFTRAG an externe Dienstleister aus einer Bestellung',
            'color'        => '#ea580c',
            'html_content' => self::defaultHtml(),
            'is_active'    => true,
            'sort_order'   => (int) DocumentTemplate::where('team_id', $teamId)->max('sort_order') + 1,
        ]);
    }

    public static function renderHtml(OrderItem $item, Event $event, string $mode = 'web'): string
    {
        $tpl = self::templateFor($event->team_id);
        $raw = (string) ($tpl?->html_content ?? self::defaultHtml());

        $filled = self::replacePlaceholders($raw, $item, $event);
        $filled = ContractRenderer::resolveAssetUrls($filled, $mode);

        if ($mode === 'pdf') {
            $filled = ContractRenderer::hardenAlignmentForPdf($filled);
        }

        return $filled;
    }

    /**
     * Nur Platzhalter ersetzen (z.B. fuer Editor-Preview).
     */
    public static function renderPlaceholders(string $text, OrderItem $item, Event $event): string
    {
        return self::replacePlaceholders($text, $item, $event);
    }

    /**
     * @return array<string,string>
     */
    public static function availablePlaceholders(): array
    {
        return [
            '{ORDER_NR}'           => 'Order-Nummer (aus Ordernummern-Schema)',
            '{EVENT_NAME}'         => 'Name der Veranstaltung',
            '{EVENT_NUMBER}'       => 'Veranstaltungsnummer (z.B. VA#2026-031)',
            '{EMPFAENGER_FIRMA}'   => 'Dienstleister (CRM-Firma oder Lieferant-Freitext)',
            '{EMPFAENGER_KONTAKT}' => 'Ansprechpartner beim Dienstleister (CRM)',
            '{EMPFAENGER_EMAIL}'   => 'E-Mail des Ansprechpartners (CRM)',
            '{EMPFAENGER_TEL}'     => 'Telefon des Dienstleisters (Freitext)',
            '{BESTELLER}'          => 'Besteller (angemeldeter Nutzer)',
            '{VA_PL}'              => 'VA-Projektleitung (Verantwortlich vor Ort / Verantwortlich)',
            '{VA_DATUM}'           => 'Datum des Veranstaltungstags, TT.MM.JJJJ',
            '{VA_VON}'             => 'Vorgang von (Uhrzeit)',
            '{VA_BIS}'             => 'Vorgang bis (Uhrzeit)',
            '{PAX}'                => 'Personenzahl des Tags',
            '{LIEFERADRESSE}'      => 'Lieferadresse (Location oder Firma + Hinweis)',
            '{BESTAETIGUNG_EMAIL}' => 'E-Mail fuer die Auftragsbestaetigung (angemeldeter Nutzer)',
            '{POSITIONEN_TABELLE}' => 'Positions-Tabelle (automatisch gerendert)',
            '{GESAMT}'             => 'Gesamtsumme (EK)',
            '{BEMERKUNG}'          => 'Bemerkung zur Bestellung',
            '{TODAY}'              => 'Heutiges Datum, TT.MM.JJJJ',
        ];
    }

    protected static function replacePlaceholders(string $text, OrderItem $item, Event $event): string
    {
        $map = self::buildValueMap($item, $event);

        return preg_replace_callback('/\{\{?\s*([A-Z_][A-Z0-9_]*)\s*\}?\}/', function ($m) use ($map) {
            $key = '{' . $m[1] . '}';
            return array_key_exists($key, $map) ? $map[$key] : $m[0];
        }, $text);
    }

    /**
     * @return array<string,string>
     */
    protected static function buildValueMap(OrderItem $item, Event $event): array
    {
        $day = $item->eventDay;
        $contact = self::resolveContact($item);
        $user = Auth::user();

        return [
            '{ORDER_NR}'           => OrderNumberBuilder::build($event),
            '{EVENT_NAME}'         => (string) ($event->name ?? ''),
            '{EVENT_NUMBER}'       => (string) ($event->event_number ?? ''),
            '{EMPFAENGER_FIRMA}'   => $item->recipientName(),
            '{EMPFAENGER_KONTAKT}' => (string) ($contact['name'] ?? ''),
            '{EMPFAENGER_EMAIL}'   => (string) ($contact['email'] ?? ''),
            '{EMPFAENGER_TEL}'     => (string) ($item->empfaenger_tel ?? ''),
            '{BESTELLER}'          => (string) ($user?->name ?? $event->responsible ?? ''),
            '{VA_PL}'              => (string) ($event->responsible_onsite ?: $event->responsible ?: ''),
            '{VA_DATUM}'           => self::dateString($day?->datum ?? $event->start_date),
            '{VA_VON}'             => (string) ($day?->start_time ?? ''),
            '{VA_BIS}'             => (string) ($day?->end_time ?? ''),
            '{PAX}'                => self::paxString($day),
            '{LIEFERADRESSE}'      => self::deliveryAddress($event),
            '{BESTAETIGUNG_EMAIL}' => (string) ($user?->email ?? ''),
            '{POSITIONEN_TABELLE}' => self::positionsTableHtml($item),
            '{GESAMT}'             => self::money((float) $item->posList->sum('gesamt')),
            '{BEMERKUNG}'          => nl2br(e((string) ($item->bemerkung ?? ''))),
            '{TODAY}'              => now()->format('d.m.Y'),
        ];
    }

    /**
     * Rendert die Positions-Tabelle als HTML (Pos / Anz / Artikel / Uhrzeit / EK / Gesamt).
     */
    protected static function positionsTableHtml(OrderItem $item): string
    {
        $positions = $item->posList;
        if ($positions->isEmpty()) {
            return '<p style="color:#64748b;font-style:italic;">Keine Positionen.</p>';
        }

        $rows = '';
        $i = 0;
        foreach ($positions as $pos) {
            $i++;
            $ek     = $pos->ek !== null && (float) $pos->ek != 0.0 ? self::money((float) $pos->ek) : '';
            $gesamt = $pos->gesamt !== null && (float) $pos->gesamt != 0.0 ? self::money((float) $pos->gesamt) : '';
            $uhr = trim(
                ($pos->start_time ? (string) $pos->start_time : '')
                . ($pos->end_time ? ' – ' . (string) $pos->end_time : '')
            );
            $name = e((string) ($pos->name ?? ''));
            if ($pos->inhalt) {
                $name .= '<br><span style="font-size:8pt;color:#64748b;">' . e((string) $pos->inhalt) . '</span>';
            }

            $rows .= '<tr>'
                . '<td class="num">' . $i . '.</td>'
                . '<td class="num">' . e((string) ($pos->anz ?? '')) . ' x</td>'
                . '<td>' . $name . '</td>'
                . '<td>' . e($uhr) . '</td>'
                . '<td class="num">' . $ek . '</td>'
                . '<td class="num">' . $gesamt . '</td>'
                . '</tr>';
        }

        return '<table class="positions">'
            . '<thead><tr>'
            . '<th class="num">Pos.</th>'
            . '<th class="num">Anz.</th>'
            . '<th>Artikel</th>'
            . '<th>Uhrzeit</th>'
            . '<th class="num">EK</th>'
            . '<th class="num">Gesamt</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';
    }

    /**
     * @return array{name:?string,email:?string}
     */
    protected static function resolveContact(OrderItem $item): array
    {
        if (!$item->crm_company_id || !$item->crm_contact_id) {
            return ['name' => null, 'email' => null];
        }
        try {
            if (!app()->bound(CrmCompanyContactsProviderInterface::class)) {
                return ['name' => null, 'email' => null];
            }
            $contacts = app(CrmCompanyContactsProviderInterface::class)->contacts((int) $item->crm_company_id);
            foreach ($contacts as $c) {
                if ((int) ($c['id'] ?? 0) === (int) $item->crm_contact_id) {
                    return ['name' => $c['name'] ?? null, 'email' => $c['email'] ?? null];
                }
            }
        } catch (\Throwable $e) {
            // CRM nicht verfuegbar
        }
        return ['name' => null, 'email' => null];
    }

    protected static function deliveryAddress(Event $event): string
    {
        $parts = [];
        if ($event->deliveryLocation) {
            $parts[] = (string) $event->deliveryLocation->name;
        } elseif ($event->delivery_address) {
            $parts[] = (string) $event->delivery_address;
        } elseif ($event->crm_company_id || $event->delivery_address_crm_company_id) {
            try {
                $resolver = app(\Platform\Core\Contracts\CrmCompanyResolverInterface::class);
                $name = $resolver->displayName((int) ($event->delivery_address_crm_company_id ?: $event->crm_company_id));
                if ($name) $parts[] = $name;
            } catch (\Throwable $e) {}
        }
        if ($event->delivery_note) {
            $parts[] = (string) $event->delivery_note;
        }
        return implode(', ', array_filter($parts));
    }

    protected static function paxString(?\Platform\Events\Models\EventDay $day): string
    {
        if (!$day) return '';
        $von = $day->pers_von;
        $bis = $day->pers_bis;
        if ($von && $bis && $von !== $bis) return $von . '–' . $bis;
        return (string) ($bis ?: $von ?: '');
    }

    protected static function money(float $v): string
    {
        return number_format($v, 2, ',', '.') . ' €';
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

    /**
     * Default-Vorlage, nachgebaut aus dem Beispiel-Bestellschein. DomPDF-freundlich
     * (Inline-Styles, Tabellen fuer das Kopf-Layout). Firmen-Boilerplate
     * (Rechnungsadresse, Fuss) ist bewusst als Text hinterlegt und im Editor
     * anpassbar.
     */
    public static function defaultHtml(): string
    {
        return <<<'HTML'
<h1 style="margin:0 0 2px 0;font-size:15pt;">AUFTRAG für VA {EVENT_NAME}</h1>
<p style="margin:0 0 10px 0;font-size:9pt;color:#64748b;">vom {TODAY}</p>

<table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
  <tr>
    <td style="width:55%;vertical-align:top;font-size:9pt;">
      <strong>An</strong><br>
      {EMPFAENGER_FIRMA}<br>
      {EMPFAENGER_KONTAKT}<br>
      Tel.: {EMPFAENGER_TEL}
    </td>
    <td style="width:45%;vertical-align:top;font-size:9pt;">
      <table style="width:100%;border-collapse:collapse;">
        <tr><td><strong>Order-Nr.</strong></td><td style="text-align:right;">{ORDER_NR}</td></tr>
        <tr><td><strong>Besteller</strong></td><td style="text-align:right;">{BESTELLER}</td></tr>
        <tr><td><strong>VA-PL</strong></td><td style="text-align:right;">{VA_PL}</td></tr>
        <tr><td><strong>VA-Datum</strong></td><td style="text-align:right;">{VA_DATUM}</td></tr>
      </table>
    </td>
  </tr>
</table>

<p style="font-size:9pt;margin:2px 0;"><strong>Lieferadresse</strong> {LIEFERADRESSE}</p>
<p style="font-size:9pt;margin:2px 0;"><strong>Rechnungsadresse</strong> BHG.BROICHCATERING GMBH BackOffice</p>
<p style="font-size:9pt;margin:2px 0;"><strong>Rechnungsadresse e-Mail</strong> rechnung@broichcatering.com</p>

<hr style="border:0;border-top:1px solid #e2e8f0;margin:12px 0;">

<p style="font-size:9pt;">Sehr geehrte Damen und Herren, hiermit möchten wir gerne bei Ihnen folgende Positionen beauftragen:</p>
<p style="font-size:9pt;"><strong>Vorgang zu VA</strong> am {VA_DATUM} von {VA_VON} Uhr bis {VA_BIS} Uhr &nbsp; <strong>PAX:</strong> {PAX}</p>

{POSITIONEN_TABELLE}

<table style="width:100%;border-collapse:collapse;margin-top:8px;">
  <tr>
    <td style="text-align:right;font-weight:bold;font-size:10pt;">Total: {GESAMT}</td>
  </tr>
</table>

<hr style="border:0;border-top:1px solid #e2e8f0;margin:12px 0;">

<p style="font-size:9pt;">Bitte senden Sie uns eine Auftragsbestätigung per E-Mail an {BESTAETIGUNG_EMAIL}.</p>
<p style="font-size:9pt;"><strong>Bemerkung:</strong> {BEMERKUNG}</p>
HTML;
    }
}
