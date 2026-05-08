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

        $allowedKeysList = array_keys($allowedByLabel);
        $allowedKeysStr  = empty($allowedKeysList)
            ? '<noch keine Felder konfiguriert>'
            : implode(' | ', array_map(fn ($v) => '"' . $v . '"', $allowedKeysList));

        $msgParts = [];
        if (!empty($unknownKeys)) {
            foreach ($unknownKeys as $uk) {
                $msgParts[] = 'mr_data-Feld "' . $uk . '" ist nicht erlaubt. Erlaubt: '
                    . $allowedKeysStr . '. Erweiterbar in Einstellungen → Management Report.';
            }
        }
        foreach ($invalidValues as $label => $info) {
            $allowedStr = empty($info['allowed'])
                ? '<keine Optionen konfiguriert>'
                : implode(' | ', array_map(fn ($v) => '"' . $v . '"', $info['allowed']));
            $msgParts[] = 'mr_data["' . $label . '"] = "' . $info['received'] . '" ist nicht erlaubt. Erlaubt: '
                . $allowedStr . '. Erweiterbar in Einstellungen → Management Report.';
        }

        return [
            'ok'                    => false,
            'message'               => implode(' ', $msgParts),
            'allowed_keys'          => array_keys($allowedByLabel),
            'allowed_values_by_key' => $allowedByLabel,
        ];
    }
}
