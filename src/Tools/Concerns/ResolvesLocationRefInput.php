<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Models\Location;

/**
 * Loest Location-Identifikatoren aus Tool-Arguments auf — vier Felder:
 *   {prefix}location_id, {prefix}location_uuid, {prefix}location_kuerzel,
 *   {prefix}location_ref (generisch).
 *
 * Konflikt-Strategie: Werden mehrere Felder gleichzeitig gesendet, MUESSEN
 * sie auf dieselbe Location zeigen, sonst VALIDATION_ERROR. Kein Silent-Pick.
 *
 * Prefix wird verwendet, um z.B. "delivery_location_*" Felder fuer das
 * Delivery-Location-Field am Event zu unterstuetzen, ohne den eigentlichen
 * `location_*`-Resolver zu ueberschreiben.
 *
 * Nutzung:
 *   $res = $this->resolveLocationRefInput($arguments, $event->team_id);
 *   if ($res['error']) return $res['error'];
 *   $loc = $res['location'];           // ?Location
 *   $aliases = $res['aliases_applied']; // array<string>
 */
trait ResolvesLocationRefInput
{
    /**
     * @return array{location: ?Location, aliases_applied: array<int,string>, error: ?ToolResult, fields_seen: array<int,string>}
     */
    protected function resolveLocationRefInput(array $arguments, ?int $teamId, string $prefix = ''): array
    {
        $fId      = "{$prefix}location_id";
        $fUuid    = "{$prefix}location_uuid";
        $fKuerzel = "{$prefix}location_kuerzel";
        $fRef     = "{$prefix}location_ref";

        $fieldsSeen = [];
        $candidates = [];

        if (!empty($arguments[$fId])) {
            $fieldsSeen[] = $fId;
            $loc = Location::query()->where('id', (int) $arguments[$fId])->first();
            $candidates[] = ['field' => $fId, 'input' => (int) $arguments[$fId], 'location' => $loc, 'matched_by' => 'id'];
        }

        if (!empty($arguments[$fUuid])) {
            $fieldsSeen[] = $fUuid;
            $loc = Location::query()->where('uuid', (string) $arguments[$fUuid])->first();
            $candidates[] = ['field' => $fUuid, 'input' => (string) $arguments[$fUuid], 'location' => $loc, 'matched_by' => 'uuid'];
        }

        if (!empty($arguments[$fKuerzel])) {
            $fieldsSeen[] = $fKuerzel;
            if ($teamId === null) {
                return [
                    'location' => null, 'aliases_applied' => [], 'fields_seen' => $fieldsSeen,
                    'error' => ToolResult::error('MISSING_TEAM',
                        "Bei {$fKuerzel} ist ein team_id-Kontext erforderlich (kuerzel ist nur per Team eindeutig)."),
                ];
            }
            $loc = Location::resolveByKuerzel((string) $arguments[$fKuerzel], $teamId);
            $candidates[] = ['field' => $fKuerzel, 'input' => (string) $arguments[$fKuerzel], 'location' => $loc, 'matched_by' => 'kuerzel'];
        }

        if (!empty($arguments[$fRef])) {
            $fieldsSeen[] = $fRef;
            $ref = $arguments[$fRef];
            $needsTeam = is_string($ref)
                && !preg_match('/^\d+$/', $ref)
                && !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $ref);
            if ($needsTeam && $teamId === null) {
                return [
                    'location' => null, 'aliases_applied' => [], 'fields_seen' => $fieldsSeen,
                    'error' => ToolResult::error('MISSING_TEAM',
                        "{$fRef} als Kuerzel benoetigt einen team_id-Kontext."),
                ];
            }
            $resolved = Location::resolveRef($ref, $teamId);
            $candidates[] = [
                'field'      => $fRef,
                'input'      => is_int($ref) ? $ref : (string) $ref,
                'location'   => $resolved['location'],
                'matched_by' => $resolved['matched_by'],
            ];
        }

        if ($candidates === []) {
            return [
                'location' => null, 'aliases_applied' => [], 'fields_seen' => [],
                'error' => null,
            ];
        }

        $resolvedIds = collect($candidates)
            ->filter(fn ($c) => $c['location'] !== null)
            ->map(fn ($c) => $c['location']->id)
            ->unique()
            ->values()
            ->all();

        if ($resolvedIds === []) {
            $detail = collect($candidates)
                ->map(fn ($c) => "{$c['field']}='{$c['input']}'")
                ->implode(', ');
            $msg = "Die angegebene Location wurde nicht gefunden ({$detail}).";

            // Komfort: bei Kuerzel-aehnlichem Input die bekannten Kuerzel des Teams anhaengen.
            $hasKuerzelLikeInput = false;
            foreach ($candidates as $c) {
                if ($c['field'] === $fKuerzel) {
                    $hasKuerzelLikeInput = true;
                    break;
                }
                if ($c['field'] === $fRef) {
                    $v = $c['input'];
                    if (is_string($v)
                        && !preg_match('/^\d+$/', $v)
                        && !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $v)
                    ) {
                        $hasKuerzelLikeInput = true;
                        break;
                    }
                }
            }
            if ($hasKuerzelLikeInput && $teamId !== null) {
                $known = Location::knownKuerzel($teamId);
                if ($known !== []) {
                    $msg .= "\nBekannte Kuerzel: " . implode(', ', $known);
                }
            }

            return [
                'location' => null, 'aliases_applied' => [], 'fields_seen' => $fieldsSeen,
                'error' => ToolResult::error('LOCATION_NOT_FOUND', $msg),
            ];
        }

        if (count($resolvedIds) > 1) {
            $detail = collect($candidates)
                ->map(function ($c) {
                    $id = $c['location']?->id ?? 'null';
                    return "{$c['field']}={$c['input']}->id={$id}";
                })
                ->implode(', ');
            return [
                'location' => null, 'aliases_applied' => [], 'fields_seen' => $fieldsSeen,
                'error' => ToolResult::error('VALIDATION_ERROR',
                    "Konflikt: Location-Identifikatoren zeigen auf unterschiedliche Locations ({$detail}). Bitte konsistente Werte oder nur einen Identifikator senden."),
            ];
        }

        $unresolvedFields = collect($candidates)
            ->filter(fn ($c) => $c['location'] === null)
            ->map(fn ($c) => "{$c['field']}={$c['input']}")
            ->all();
        if ($unresolvedFields !== []) {
            return [
                'location' => null, 'aliases_applied' => [], 'fields_seen' => $fieldsSeen,
                'error' => ToolResult::error('VALIDATION_ERROR',
                    'Konflikt: Identifikatoren ' . implode(', ', $unresolvedFields) .
                    ' liefen ins Leere, andere wurden aufgeloest. Bitte konsistente Werte oder nur einen Identifikator senden.'),
            ];
        }

        $location = $candidates[0]['location'];

        $aliases = [];
        foreach ($candidates as $c) {
            if ($c['field'] === $fKuerzel) {
                $aliases[] = "{$c['field']}:'{$c['input']}'->location_id:{$location->id}";
            } elseif ($c['field'] === $fRef && $c['matched_by'] !== 'id') {
                $aliases[] = "{$c['field']}:'{$c['input']}'->location_id:{$location->id}";
            } elseif ($c['field'] === $fUuid) {
                $aliases[] = "{$c['field']}:'{$c['input']}'->location_id:{$location->id}";
            }
        }

        return [
            'location'        => $location,
            'aliases_applied' => $aliases,
            'fields_seen'     => $fieldsSeen,
            'error'           => null,
        ];
    }

    /**
     * Liste der bekannten Location-Felder fuer ignored_fields-Diff.
     *
     * @return array<int, string>
     */
    protected function locationRefInputFields(string $prefix = ''): array
    {
        return [
            "{$prefix}location_id",
            "{$prefix}location_uuid",
            "{$prefix}location_kuerzel",
            "{$prefix}location_ref",
        ];
    }
}
