<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Events\Models\MrFieldConfig;

/**
 * Validiert mr_data-Eingaben strikt gegen die Team-Konfiguration in
 * MrFieldConfig (Settings). Akzeptiert Keys sowohl als Label
 * ("Speisenform") als auch als kanonische ID ("mrf_42") und normalisiert
 * intern auf die mrf_<id>-Form, mit der die UI persistiert.
 *
 * Ergebnis:
 *   ['ok' => true,  'normalized' => array<string,mixed>]
 *   ['ok' => false, 'message' => string, 'allowed_keys' => [...], 'allowed_values_by_key' => [...]]
 */
trait ValidatesMrData
{
    protected function normalizeAndValidateMrData(array $mrData, int $teamId): array
    {
        $configs = MrFieldConfig::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $labelToKey   = [];
        $keyToOptions = [];
        $keyToLabel   = [];
        foreach ($configs as $cfg) {
            $key = 'mrf_' . $cfg->id;
            $labelToKey[$cfg->label] = $key;
            $keyToLabel[$key]        = $cfg->label;
            $keyToOptions[$key]      = array_values(array_map(
                fn ($o) => is_array($o) ? ($o['label'] ?? '') : (string) $o,
                $cfg->options ?? []
            ));
        }

        $normalized    = [];
        $unknownKeys   = [];
        $invalidValues = [];

        foreach ($mrData as $inputKey => $value) {
            if (isset($labelToKey[$inputKey])) {
                $canonical = $labelToKey[$inputKey];
            } elseif (isset($keyToOptions[$inputKey])) {
                $canonical = $inputKey;
            } else {
                $unknownKeys[] = $inputKey;
                continue;
            }

            if ($value === null || $value === '') {
                $normalized[$canonical] = $value;
                continue;
            }

            $allowed = $keyToOptions[$canonical];
            if (!in_array($value, $allowed, true)) {
                $invalidValues[$keyToLabel[$canonical]] = [
                    'received' => $value,
                    'allowed'  => $allowed,
                ];
                continue;
            }

            $normalized[$canonical] = $value;
        }

        if (empty($unknownKeys) && empty($invalidValues)) {
            return ['ok' => true, 'normalized' => $normalized];
        }

        $allowedByLabel = [];
        foreach ($keyToOptions as $k => $opts) {
            $allowedByLabel[$keyToLabel[$k]] = $opts;
        }

        $msgParts = [];
        if (!empty($unknownKeys)) {
            $msgParts[] = 'Unbekannte mr_data-Felder: "' . implode('", "', $unknownKeys) . '". '
                . 'Erlaubt sind nur die in Einstellungen → Management Report konfigurierten Felder: "'
                . implode('", "', array_keys($allowedByLabel)) . '".';
        }
        foreach ($invalidValues as $label => $info) {
            $allowedStr = empty($info['allowed'])
                ? '<keine Optionen konfiguriert – bitte erst in Einstellungen anlegen>'
                : '"' . implode('", "', $info['allowed']) . '"';
            $msgParts[] = "Feld '{$label}': Wert '{$info['received']}' nicht erlaubt. Erlaubte Optionen: {$allowedStr}.";
        }

        return [
            'ok'                    => false,
            'message'               => implode(' ', $msgParts),
            'allowed_keys'          => array_keys($allowedByLabel),
            'allowed_values_by_key' => $allowedByLabel,
        ];
    }
}
