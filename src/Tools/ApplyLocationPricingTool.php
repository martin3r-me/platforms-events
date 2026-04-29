<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\LocationPricingApplicator;
use Platform\Events\Tools\Concerns\RecalculatesQuoteItem;
use Platform\Events\Tools\Concerns\ResolvesQuoteItem;
use Platform\Locations\Models\Location;

/**
 * Bucht Location-Preise (Pricings + Add-ons) auf einen Vorgang.
 * Akzeptiert pricing_ids/addon_selections sowohl top-level als auch unter
 * selection (Alias). Bei fehlender Auswahl wird automatisch das passende
 * Pricing fuer den day_type des Tages vorgeschlagen.
 */
class ApplyLocationPricingTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuoteItem;
    use RecalculatesQuoteItem;

    public function getName(): string
    {
        return 'events.quotes.APPLY_LOCATION_PRICING';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/{id}/apply-location-pricing - Bucht Pricings + Add-ons einer Location auf einen Angebots-Vorgang. '
            . 'Pflicht: quote_item_id|quote_item_uuid + location_id. '
            . 'AUSWAHL der Pricings (eines davon): '
            . '(1) selection.pricing_ids: [int...] und/oder selection.addon_selections: [{addon_id:int, qty:number}, ...]; '
            . '(2) ALIAS auf Top-Level: pricing_ids: [int...] und/oder addon_selections: [{addon_id, qty}, ...]; '
            . '(3) AUTO: keinen der oben genannten Werte schicken – das Tool waehlt dann automatisch alle Pricings, '
            . 'deren day_type_label mit dem day_type des Tages uebereinstimmt (z.B. Veranstaltungstag → ASH-MIETE-VA). '
            . 'Optional: auto_suggest=false unterdrueckt die Auto-Wahl. '
            . 'Response: positions_created, application_id, applied_pricing_ids, source ("request" | "auto_suggested"), '
            . 'available_pricings (Liste aller pflegbaren Pricings der Location als Hilfe), warnings[].';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteItemSelectorSchema(), [
                'location_id' => ['type' => 'integer', 'description' => 'ID der Location.'],
                // Top-Level-Aliase – werden in selection verschoben:
                'pricing_ids' => [
                    'type' => 'array', 'items' => ['type' => 'integer'],
                    'description' => 'Alias fuer selection.pricing_ids[].',
                ],
                'addon_selections' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['addon_id' => ['type' => 'integer'], 'qty' => ['type' => 'number']]],
                    'description' => 'Alias fuer selection.addon_selections[].',
                ],
                'selection' => [
                    'type'        => 'object',
                    'description' => 'Auswahl: { pricing_ids: [int...], addon_selections: [{addon_id:int, qty:number}, ...] }',
                    'properties'  => [
                        'pricing_ids'      => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'addon_selections' => [
                            'type' => 'array',
                            'items' => ['type' => 'object', 'properties' => ['addon_id' => ['type' => 'integer'], 'qty' => ['type' => 'number']]],
                        ],
                    ],
                ],
                'auto_suggest' => [
                    'type' => 'boolean',
                    'description' => 'Default true. Wenn nichts ausgewaehlt ist, werden Pricings via day_type-Match des Tages auto-gewaehlt.',
                ],
            ]),
            'required' => ['location_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $item = $this->resolveQuoteItem($arguments, $context);
            if ($item instanceof ToolResult) {
                return $item;
            }

            $location = Location::where('team_id', $item->team_id)
                ->find((int) ($arguments['location_id'] ?? 0));
            if (!$location) {
                return ToolResult::error('VALIDATION_ERROR', 'Location nicht gefunden oder nicht im Team.');
            }

            // ----- Auswahl zusammenfuehren -----
            $aliasesApplied = [];
            $selection = is_array($arguments['selection'] ?? null) ? $arguments['selection'] : [];

            // Top-Level-Aliase in selection migrieren, sofern dort noch nicht vorhanden.
            if (!empty($arguments['pricing_ids']) && empty($selection['pricing_ids'])) {
                $selection['pricing_ids'] = $arguments['pricing_ids'];
                $aliasesApplied[] = 'pricing_ids→selection.pricing_ids';
            }
            if (!empty($arguments['addon_selections']) && empty($selection['addon_selections'])) {
                $selection['addon_selections'] = $arguments['addon_selections'];
                $aliasesApplied[] = 'addon_selections→selection.addon_selections';
            }

            $hasManualSelection = !empty($selection['pricing_ids']) || !empty($selection['addon_selections']);
            $autoSuggest = (bool) ($arguments['auto_suggest'] ?? true);

            $source = 'request';
            $autoWarnings = [];
            if (!$hasManualSelection && $autoSuggest) {
                $sug = LocationPricingApplicator::suggestSelection($item, $location);
                $autoWarnings = $sug['warnings'] ?? [];
                if (!empty($sug['suggested_pricing_ids'])) {
                    $selection['pricing_ids'] = $sug['suggested_pricing_ids'];
                    $source = 'auto_suggested';
                    $hasManualSelection = true;
                }
            }

            // available_pricings als Hilfe IMMER mitliefern (auch bei Erfolg).
            $availablePricings = $location->pricings()->orderBy('sort_order')->orderBy('day_type_label')->get()->map(fn ($p) => [
                'id'             => (int) $p->id,
                'day_type_label' => $p->day_type_label,
                'label'          => $p->label,
                'price_net'      => isset($p->price_net) ? (float) $p->price_net : null,
            ])->all();

            $known = [
                'quote_item_id', 'quote_item_uuid',
                'location_id', 'selection',
                'pricing_ids', 'addon_selections', 'auto_suggest',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            // Wenn auch nach Auto-Suggest keine Auswahl da: klar zurueckweisen.
            if (!$hasManualSelection) {
                $dayType = $item->eventDay?->day_type;
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'Keine Pricings/Add-ons ausgewaehlt und Auto-Suggest hat nichts gefunden.'
                    . ($dayType ? " (day_type des Tages: '{$dayType}')" : ''),
                    [
                        'aliases_applied'   => $aliasesApplied,
                        'ignored_fields'    => $ignored,
                        'auto_warnings'     => $autoWarnings,
                        'available_pricings'=> $availablePricings,
                        '_field_hints'      => $this->fieldHints(),
                    ],
                );
            }

            $result = LocationPricingApplicator::apply($item, $location, $selection);
            $this->recalcQuoteItem($item);

            $event = $item->eventDay?->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Location-Preise \"{$location->name}\" via Tool eingebucht ({$source})");
            }

            return ToolResult::success([
                'quote_item_id'        => $item->id,
                'location_id'          => $location->id,
                'positions_created'    => count($result['positions'] ?? []),
                'application_id'       => $result['application']->id ?? null,
                'applied_pricing_ids'  => $selection['pricing_ids'] ?? [],
                'applied_addons'       => $selection['addon_selections'] ?? [],
                'source'               => $source,
                'available_pricings'   => $availablePricings,
                'warnings'             => array_values(array_filter(array_merge($autoWarnings, $result['warnings'] ?? []))),
                'aliases_applied'      => $aliasesApplied,
                'ignored_fields'       => $ignored,
                '_field_hints'         => $this->fieldHints(),
                'message'              => $source === 'auto_suggested'
                    ? "Auto-Wahl: " . count($selection['pricing_ids'] ?? []) . " Pricing(s) von «{$location->name}» eingebucht."
                    : "Location-Preise von «{$location->name}» eingebucht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string,string>
     */
    protected function fieldHints(): array
    {
        return [
            'selection.pricing_ids'      => 'Liste IDs aus location.pricings (siehe available_pricings im Response).',
            'selection.addon_selections' => 'Liste {addon_id, qty}. addon_id aus location.addons; qty = Menge.',
            'pricing_ids'                => 'Top-Level-Alias fuer selection.pricing_ids. Wird automatisch in selection migriert.',
            'addon_selections'           => 'Top-Level-Alias fuer selection.addon_selections.',
            'auto_suggest'               => 'Default true. Wenn keine Auswahl mitkommt: das Tool waehlt alle Pricings, deren day_type_label dem day_type des verknuepften Tages entspricht.',
            'available_pricings'         => 'Read-only: Alle pflegbaren Pricings der Location – als Auswahlhilfe, falls Auto-Suggest fehlschlaegt.',
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'location', 'pricing', 'apply'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates', 'updates'],
        ];
    }
}
