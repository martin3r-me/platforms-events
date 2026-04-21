<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\FeedbackEntry;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListFeedbackEntriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.feedback-entries.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/feedback/entries - Listet eingegangene Feedback-Einträge mit Bewertungen und Kommentaren.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => array_merge($this->eventSelectorSchema(), [
                'link_id' => ['type' => 'integer', 'description' => 'Optional: nur Einträge dieses Links.'],
            ])]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $query = FeedbackEntry::where('event_id', $event->id)->with('link:id,label,audience');
            if (!empty($arguments['link_id'])) $query->where('link_id', (int) $arguments['link_id']);
            $this->applyStandardSort($query, $arguments, ['created_at'], 'created_at', 'desc');
            $this->applyStandardPagination($query, $arguments);

            $entries = $query->get()->map(fn (FeedbackEntry $e) => [
                'id' => $e->id, 'name' => $e->name, 'comment' => $e->comment,
                'rating_overall' => $e->rating_overall,
                'rating_location' => $e->rating_location,
                'rating_catering' => $e->rating_catering,
                'rating_organization' => $e->rating_organization,
                'link_id' => $e->link_id, 'link_label' => $e->link?->label,
                'audience' => $e->link?->audience,
                'created_at' => $e->created_at?->format('Y-m-d H:i'),
            ])->toArray();

            $avg = [
                'overall' => $entries ? round(collect($entries)->avg('rating_overall'), 1) : null,
                'location' => $entries ? round(collect($entries)->avg('rating_location'), 1) : null,
                'catering' => $entries ? round(collect($entries)->avg('rating_catering'), 1) : null,
                'organization' => $entries ? round(collect($entries)->avg('rating_organization'), 1) : null,
            ];

            return ToolResult::success([
                'entries' => $entries, 'count' => count($entries),
                'averages' => $avg, 'event_id' => $event->id,
                'message' => count($entries) . ' Feedback-Eintrag/-Einträge.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'query', 'tags' => ['events', 'feedback', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true];
    }
}
