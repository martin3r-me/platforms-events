<?php

namespace Platform\Events\Tools\Concerns;

/**
 * Strikt-Enum + Auto-Alias fuer event.orderer_via (Eingangskanal der Anfrage).
 * Akzeptiert die kanonischen Werte "Mail" | "Telefon" | "Web" sowie haeufige
 * Schreibvarianten (E-Mail, Email, Phone, Tel, Website, Online, Formular, …).
 *
 * Resolve-Verhalten:
 *   - exakt kanonisch                → ['value' => '<canonical>', 'alias' => null]
 *   - bekannter Alias / Casing-Mismatch → ['value' => '<canonical>', 'alias' => "orderer_via:'<input>'->canonical"]
 *   - unbekannt                      → ['error' => 'orderer_via "<input>" ist nicht erlaubt. Erlaubt: "Mail" | "Telefon" | "Web".']
 *   - null / "" / nicht gesetzt      → ['skip' => true]
 */
trait ResolvesOrdererVia
{
    public const ORDERER_VIA_OPTIONS = ['Mail', 'Telefon', 'Web'];

    /** Lowercase-Input -> kanonischer Wert. Casing-Variationen der Kanon-Werte sind enthalten. */
    private const ORDERER_VIA_ALIASES = [
        // Mail
        'mail'      => 'Mail',
        'e-mail'    => 'Mail',
        'email'     => 'Mail',
        'mails'     => 'Mail',
        'mailing'   => 'Mail',
        // Telefon
        'telefon'   => 'Telefon',
        'tel'       => 'Telefon',
        'tel.'      => 'Telefon',
        'phone'     => 'Telefon',
        'anruf'     => 'Telefon',
        'fon'       => 'Telefon',
        // Web
        'web'                => 'Web',
        'website'            => 'Web',
        'webseite'           => 'Web',
        'homepage'           => 'Web',
        'online'             => 'Web',
        'formular'           => 'Web',
        'kontaktformular'    => 'Web',
        'web-formular'       => 'Web',
    ];

    /**
     * @return array{value?: string, alias?: ?string, error?: string, skip?: bool}
     */
    protected function resolveOrdererVia(?string $input): array
    {
        if ($input === null || trim($input) === '') {
            return ['skip' => true];
        }

        $raw  = $input;
        $norm = mb_strtolower(trim($input));

        // exakt kanonisch?
        if (in_array($raw, self::ORDERER_VIA_OPTIONS, true)) {
            return ['value' => $raw, 'alias' => null];
        }

        // Alias / Casing-Match?
        if (isset(self::ORDERER_VIA_ALIASES[$norm])) {
            $canonical = self::ORDERER_VIA_ALIASES[$norm];
            return [
                'value' => $canonical,
                'alias' => "orderer_via:'{$raw}'->{$canonical}",
            ];
        }

        return [
            'error' => 'orderer_via "' . $raw . '" ist nicht erlaubt. Erlaubt: "'
                . implode('" | "', self::ORDERER_VIA_OPTIONS) . '".',
        ];
    }
}
