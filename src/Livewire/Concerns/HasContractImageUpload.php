<?php

namespace Platform\Events\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

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
            $path = "events/contract-assets/team-{$teamId}/{$filename}";

            Storage::disk('public')->put($path, file_get_contents($this->contractImage->getRealPath()));

            $url = Storage::disk('public')->url($path);
            $markdown = "\n\n![](" . $url . ")\n\n";

            $this->insertImageMarkdown($markdown);
        } catch (\Throwable $e) {
            session()->flash('contractImageError', 'Upload fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->contractImage = null;
            $this->uploadingImage = false;
        }
    }
}
