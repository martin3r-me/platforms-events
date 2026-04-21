<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Events\Livewire\Concerns\HasContractImageUpload;
use Platform\Events\Models\Contract;
use Platform\Events\Models\DocumentTemplate;
use Platform\Events\Models\Event;
use Platform\Events\Services\ActivityLogger;

class Contracts extends Component
{
    use WithFileUploads;
    use HasContractImageUpload;

    public int $eventId;
    public ?int $activeContractId = null;

    public bool $showEditModal = false;
    public string $contractType = 'nutzungsvertrag';
    public string $contractText = '';

    public function insertImageMarkdown(string $markdown): void
    {
        $this->contractText = ($this->contractText ?? '') . $markdown;
    }

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->activeContractId = Contract::where('event_id', $eventId)
            ->where('is_current', true)
            ->latest('version')
            ->value('id');
    }

    protected function event(): Event
    {
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) {
            abort(403);
        }
        return $event;
    }

    public function createContract(string $type = 'nutzungsvertrag'): void
    {
        $event = $this->event();
        $text = '';
        $logNote = $type;

        $tpl = DocumentTemplate::where('team_id', $event->team_id)
            ->where('is_active', true)
            ->where(function ($q) use ($type) {
                $q->where('slug', $type)->orWhere('id', is_numeric($type) ? (int) $type : 0);
            })
            ->first();

        if ($tpl) {
            $text = (string) $tpl->html_content;
            $type = $tpl->slug ?: $tpl->label;
            $logNote = "{$tpl->label} (Vorlage)";
        }

        $contract = Contract::create([
            'team_id'    => $event->team_id,
            'user_id'    => Auth::id(),
            'event_id'   => $event->id,
            'type'       => $type,
            'token'      => Str::random(48),
            'status'     => 'draft',
            'content'    => ['text' => $text],
            'version'    => 1,
            'is_current' => true,
        ]);
        $this->activeContractId = $contract->id;
        ActivityLogger::log($event, 'contract', "Vertrag #{$contract->id} (v1, {$logNote}) angelegt");
    }

    public function selectContract(int $contractId): void
    {
        if (Contract::where('event_id', $this->eventId)->where('id', $contractId)->exists()) {
            $this->activeContractId = $contractId;
        }
    }

    public function newVersion(): void
    {
        if (!$this->activeContractId) return;
        $event = $this->event();
        $current = Contract::find($this->activeContractId);
        if (!$current || $current->event_id !== $event->id) return;

        $rootId = $current->getRootParentId();
        $maxVersion = (int) Contract::where('event_id', $event->id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->max('version');

        Contract::where('event_id', $event->id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->update(['is_current' => false]);

        $newC = Contract::create([
            'team_id'    => $event->team_id,
            'user_id'    => Auth::id(),
            'event_id'   => $event->id,
            'type'       => $current->type,
            'token'      => Str::random(48),
            'status'     => 'draft',
            'content'    => $current->content,
            'version'    => $maxVersion + 1,
            'parent_id'  => $rootId,
            'is_current' => true,
        ]);

        $this->activeContractId = $newC->id;
        ActivityLogger::log($event, 'contract', "Vertrag v{$newC->version} angelegt");
    }

    public function openEdit(): void
    {
        if (!$this->activeContractId) return;
        $c = Contract::find($this->activeContractId);
        if (!$c) return;

        $this->contractType = $c->type;
        $this->contractText = $this->ensureHtmlForEditor((string) ($c->content['text'] ?? ''));
        $this->showEditModal = true;

        $this->dispatch('tinymce-set-content',
            uid: 'tiny-contract',
            content: $this->contractText
        );
    }

    protected function ensureHtmlForEditor(string $content): string
    {
        if ($content === '') return '';
        $html = preg_match('/<\/?[a-z][a-z0-9]*\b[^>]*>/i', $content)
            ? $content
            : \Platform\Events\Services\ContractRenderer::markdownToHtml($content);
        return \Platform\Events\Services\ContractRenderer::resolveForEditor($html);
    }

    public function saveContent(): void
    {
        if (!$this->activeContractId) return;
        $c = Contract::find($this->activeContractId);
        if (!$c) return;

        // Signed-Asset-URLs zu events-asset://-Scheme normalisieren (24h-
        // Signatur laeuft sonst ab).
        $text = \Platform\Events\Services\ContractRenderer::normalizeAssetUrls(
            (string) $this->contractText
        );

        $c->update([
            'type'    => $this->contractType,
            'content' => ['text' => $text],
        ]);
        $this->showEditModal = false;
    }

    public function setStatus(string $status): void
    {
        if (!$this->activeContractId) return;
        $c = Contract::find($this->activeContractId);
        if ($c && $c->event_id === $this->eventId) {
            $old = $c->status;
            $payload = ['status' => $status];
            if ($status === 'sent') $payload['sent_at'] = now();
            $c->update($payload);
            ActivityLogger::log($this->event(), 'contract', "Vertrag-Status: „{$old}“ → „{$status}“");
        }
    }

    public function deleteContract(int $contractId): void
    {
        $c = Contract::where('event_id', $this->eventId)->find($contractId);
        if ($c) {
            $c->delete();
            if ($this->activeContractId === $contractId) {
                $this->activeContractId = Contract::where('event_id', $this->eventId)
                    ->where('is_current', true)->latest('version')->value('id');
            }
        }
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);
        $contracts = Contract::where('event_id', $event->id)
            ->orderByDesc('version')
            ->get();
        $activeContract = $this->activeContractId ? Contract::find($this->activeContractId) : null;

        $templates = DocumentTemplate::where('team_id', $event->team_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return view('events::livewire.detail.contracts', [
            'event'          => $event,
            'contracts'      => $contracts,
            'activeContract' => $activeContract,
            'templates'      => $templates,
        ]);
    }
}
