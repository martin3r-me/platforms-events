<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\SettingsService;

/**
 * Liefert die konfigurierten Text-Bausteine eines Teams ohne Event-Kontext.
 *
 * Text-Bausteine sind die wiederverwendbaren Positions-Gruppen, die als
 * Text-Zeilen ohne Preis in Quote-/OrderPositionen verwendet werden — z. B.
 * „Headline", „Trenntext", „Speisentexte", „Getraenketext" oder eigene
 * Bausteine wie „Interne Bemerkung". Pro Baustein liefert das Tool den
 * `name` (= Wert fuer `gruppe` beim Anlegen einer Position), Farb-Codes
 * (`bg`/`text`) und das `show_in_quote`-Flag.
 *
 * Das LLM kann das Tool aufrufen, bevor es eine Position mit
 * `events.quote-positions.CREATE` oder `events.order-positions.CREATE`
 * anlegt — und so wissen, welche Gruppen-Namen verfuegbar sind und welche
 * davon Kunden-sichtbar (`show_in_quote = true`) bzw. nur intern sind.
 */
class GetBausteineTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.settings.bausteine.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/settings/bausteine - Liefert die Text-Bausteine eines Teams '
            . '(wiederverwendbare Positions-Gruppen ohne Preis, z.B. „Headline", „Trenntext", '
            . '„Speisentexte", „Interne Bemerkung"). Default: aktuelles Team aus Kontext. '
            . 'Response enthaelt bausteine[] mit {name, bg, text, show_in_quote} sowie '
            . 'allowed_names[], allowed_names_visible_in_quote[] und allowed_names_internal_only[]. '
            . 'Diese Namen sind erlaubte Werte fuer das `gruppe`-Feld beim Erstellen einer Position '
            . 'als Text-Zeile (events.quote-positions.CREATE / events.order-positions.CREATE). '
            . 'show_in_quote=false bedeutet, dass Positionen mit dieser Gruppe NUR intern sind und '
            . 'NICHT im Angebots-PDF / in der Public-Ansicht ausgegeben werden.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? null;
            if ($teamId === 0 || $teamId === '0') {
                $teamId = null;
            }
            if ($teamId === null) {
                $teamId = $context->team?->id;
            }
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', (int) $teamId)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $bausteine = SettingsService::bausteine((int) $teamId);

            $visible = [];
            $internal = [];
            foreach ($bausteine as $b) {
                $show = (bool) ($b['show_in_quote'] ?? true);
                if ($show) {
                    $visible[] = $b['name'];
                } else {
                    $internal[] = $b['name'];
                }
            }

            return ToolResult::success([
                'team_id'                          => (int) $teamId,
                'bausteine'                        => $bausteine,
                'allowed_names'                    => array_map(static fn ($b) => $b['name'], $bausteine),
                'allowed_names_visible_in_quote'   => $visible,
                'allowed_names_internal_only'      => $internal,
                'count'                            => count($bausteine),
                'note'                             => 'Pflegen in Einstellungen → Text-Bausteine. Werte aus `name` koennen 1:1 als `gruppe` beim Erstellen einer Position verwendet werden. '
                    . 'Bausteine mit show_in_quote=false werden im Angebots-PDF und in der Public-Ansicht uebersprungen.',
                'message'                          => count($bausteine) . ' Text-Baustein(e) konfiguriert fuer Team ' . $teamId . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Text-Bausteine: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['events', 'settings', 'positions', 'bausteine'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'read',
            'idempotent'    => true,
            'side_effects'  => [],
        ];
    }
}
