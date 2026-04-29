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
        return 'POST /events/quote-items/{id}/apply-location-pricing - Bucht Pricings + Add-ons einer Location auf einen Vorgang. '
            . 'Pflicht: quote_item + location_id + selection (pricing_ids[] und/oder addon_selections[{addon_id, qty}]).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteItemSelectorSchema(), [
                'location_id' => ['type' => 'integer', 'description' => 'ID der Location.'],
                'selection'   => [
                    'type' => 'object',
                    'description' => 'Auswahl: { pricing_ids: [int...], addon_selections: [{addon_id:int, qty:number}, ...] }',
                ],
            ]),
            'required' => ['location_id', 'selection'],
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

            $selection = $arguments['selection'] ?? [];
            if (!is_array($selection)) {
                return ToolResult::error('VALIDATION_ERROR', 'selection muss ein Objekt sein.');
            }

            $result = LocationPricingApplicator::apply($item, $location, $selection);
            $this->recalcQuoteItem($item);

            $event = $item->eventDay?->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Location-Preise \"{$location->name}\" via Tool eingebucht");
            }

            return ToolResult::success([
                'quote_item_id'      => $item->id,
                'location_id'        => $location->id,
                'positions_created'  => count($result['positions'] ?? []),
                'application_id'     => $result['application']->id ?? null,
                'warnings'           => $result['warnings'] ?? [],
                'message'            => "Location-Preise von «{$location->name}» eingebucht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
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
