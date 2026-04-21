<?php

namespace Platform\Events\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Livewire-Trait fuer den Bild-Upload im Vertrags- und Template-Editor.
 * Erwartet eine Methode `insertImageMarkdown(string $markdown)` in der Host-Komponente
 * und eine temporaere Datei-Property `$contractImage` (Livewire-TemporaryUploadedFile).
 */
trait HasContractImageUpload
{
    public $contractImage = null;
    public bool $uploadingImage = false;

    public function updatedContractImage(): void
    {
        if (!$this->contractImage) return;

        $this->uploadingImage = true;
        try {
            $user = Auth::user();
            $teamId = $user?->currentTeam?->id ?? 0;

            $ext = strtolower($this->contractImage->getClientOriginalExtension() ?: 'png');
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
                $ext = 'png';
            }

            $filename = Str::random(12) . '.' . $ext;
            $dir = "events/contract-assets/team-{$teamId}";

            $storedPath = $this->contractImage->storeAs($dir, $filename, 'public');

            if (!$storedPath || !Storage::disk('public')->exists($storedPath)) {
                throw new \RuntimeException('Datei konnte nicht gespeichert werden (schreibrechte? disk=public)');
            }

            $url = Storage::disk('public')->url($storedPath);
            $markdown = "\n\n![](" . $url . ")\n\n";

            $this->insertImageMarkdown($markdown);
        } catch (\Throwable $e) {
            Log::error('[Events] Bild-Upload fehlgeschlagen', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('contractImageError', 'Upload fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->contractImage = null;
            $this->uploadingImage = false;
        }
    }
}
