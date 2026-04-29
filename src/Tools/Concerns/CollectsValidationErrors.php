<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Core\Contracts\ToolResult;

/**
 * Sammelt Validation-Fehler statt first-fail. Tools nutzen das,
 * um in einem einzigen Round-Trip alle fehlenden/ungueltigen Felder
 * zurueckzumelden:
 *
 *   $errors = [];
 *   if (...) $errors[] = ['field' => 'typ', 'message' => 'typ ist erforderlich.'];
 *   if (!empty($errors)) return $this->validationFailure($errors);
 */
trait CollectsValidationErrors
{
    /**
     * @param array<int, array{field: string, message: string}> $errors
     */
    protected function validationFailure(array $errors, string $summary = 'Validierung fehlgeschlagen.'): ToolResult
    {
        $messages = array_map(fn ($e) => ($e['field'] ?? '?') . ': ' . ($e['message'] ?? ''), $errors);
        // Hinweis: ToolResult::error() erwartet ($message, $code) ODER ($code, $message)
        // (Heuristik in Core swappt automatisch). Wir geben ($code, $message) wie alle anderen Tools.
        return ToolResult::error('VALIDATION_ERROR', $summary . ' (' . implode('; ', $messages) . ')', [
            'errors' => array_values($errors),
        ]);
    }

    /**
     * Hilfs-Builder: Field+Message-Eintrag.
     */
    protected function validationError(string $field, string $message): array
    {
        return ['field' => $field, 'message' => $message];
    }
}
