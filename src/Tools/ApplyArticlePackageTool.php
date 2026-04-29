<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\ArticlePackageApplicator;
use Platform\Events\Tools\Concerns\RecalculatesQuoteItem;
use Platform\Events\Tools\Concerns\ResolvesQuoteItem;

/**
 * Wendet eine Artikel-Vorlage (ArticlePackage) auf einen QuoteItem-Vorgang an
 * und legt die Positions-Zeilen an. Spiegelt UI-Action applyPackage().
 */
class ApplyArticlePackageTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuoteItem;
    use RecalculatesQuoteItem;

    public function getName(): string
    {
        return 'events.quotes.APPLY_PACKAGE';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/{id}/apply-package - Wendet eine Artikel-Vorlage auf einen Angebots-Vorgang an. '
            . 'Pflicht: quote_item_id|quote_item_uuid + package_id|package_uuid. Recalc der Vorgang-Summen automatisch.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteItemSelectorSchema(), [
                'package_id'   => ['type' => 'integer', 'description' => 'ID der ArticlePackage.'],
                'package_uuid' => ['type' => 'string',  'description' => 'UUID der ArticlePackage.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $item = $this->resolveQuoteItem($arguments, $context);
            if ($item instanceof ToolResult) {
                return $item;
            }

            $package = null;
            if (!empty($arguments['package_id'])) {
                $package = ArticlePackage::where('team_id', $item->team_id)->find((int) $arguments['package_id']);
            } elseif (!empty($arguments['package_uuid'])) {
                $package = ArticlePackage::where('team_id', $item->team_id)->where('uuid', $arguments['package_uuid'])->first();
            }
            if (!$package) {
                return ToolResult::error('VALIDATION_ERROR', 'package_id oder package_uuid ist erforderlich (und muss zum Team gehoeren).');
            }

            $created = ArticlePackageApplicator::apply($package, $item);
            $this->recalcQuoteItem($item);

            $event = $item->eventDay?->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Vorlage \"{$package->name}\" via Tool eingefuegt ({$created->count()} Positionen)");
            }

            return ToolResult::success([
                'quote_item_id'   => $item->id,
                'package_id'      => $package->id,
                'positions_added' => $created->count(),
                'message'         => "Vorlage «{$package->name}» eingefuegt ({$created->count()} Positionen).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'package', 'apply'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates', 'updates'],
        ];
    }
}
