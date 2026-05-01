<?php

namespace Platform\Events\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\ArticlePackageItem;

class Articles extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    // ========== Package Modal ==========
    public bool $showPackageModal = false;
    public ?string $editingPackageUuid = null;

    #[Validate('required|string|max:200')]
    public string $packageName = '';

    public ?string $packageDescription = null;
    public string $packageColor = '#8b5cf6';
    public bool $packageIsActive = true;

    // ========== Package Items Modal ==========
    public bool $showPackageItemsModal = false;
    public ?int $activePackageId = null;

    public array $newPackageItem = [
        'article_id' => null,
        'name'       => '',
        'gruppe'     => '',
        'quantity'   => 1,
        'gebinde'    => '',
        'vk'         => 0,
        'gesamt'     => 0,
    ];

    // ========== Package ==========

    public function openPackageCreate(): void
    {
        $this->resetPackageForm();
        $this->showPackageModal = true;
    }

    public function openPackageEdit(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        $p = ArticlePackage::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail();

        $this->editingPackageUuid = $p->uuid;
        $this->packageName        = $p->name;
        $this->packageDescription = $p->description;
        $this->packageColor       = $p->color ?: '#8b5cf6';
        $this->packageIsActive    = (bool) $p->is_active;

        $this->resetErrorBag();
        $this->showPackageModal = true;
    }

    public function closePackageModal(): void
    {
        $this->showPackageModal = false;
        $this->resetPackageForm();
    }

    protected function resetPackageForm(): void
    {
        $this->reset(['editingPackageUuid', 'packageName', 'packageDescription']);
        $this->packageColor = '#8b5cf6';
        $this->packageIsActive = true;
        $this->resetErrorBag();
    }

    public function savePackage(): void
    {
        $this->validate([
            'packageName' => 'required|string|max:200',
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        $payload = [
            'name'        => $this->packageName,
            'description' => $this->packageDescription,
            'color'       => $this->packageColor,
            'is_active'   => $this->packageIsActive,
        ];

        if ($this->editingPackageUuid) {
            ArticlePackage::where('team_id', $team->id)->where('uuid', $this->editingPackageUuid)->update($payload);
        } else {
            $payload['team_id']    = $team->id;
            $payload['user_id']    = $user->id;
            $payload['sort_order'] = (int) ArticlePackage::where('team_id', $team->id)->max('sort_order') + 1;
            ArticlePackage::create($payload);
        }

        $this->showPackageModal = false;
        $this->resetPackageForm();
    }

    public function deletePackage(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        ArticlePackage::where('team_id', $team->id)->where('uuid', $uuid)->delete();
    }

    // ========== Package Items ==========

    public function openPackageItems(string $packageUuid): void
    {
        $team = Auth::user()->currentTeam;
        $package = ArticlePackage::where('team_id', $team->id)->where('uuid', $packageUuid)->firstOrFail();
        $this->activePackageId = $package->id;
        $this->newPackageItem = [
            'article_id' => null, 'name' => '', 'gruppe' => '',
            'quantity' => 1, 'gebinde' => '', 'vk' => 0, 'gesamt' => 0,
        ];
        $this->showPackageItemsModal = true;
    }

    public function closePackageItemsModal(): void
    {
        $this->showPackageItemsModal = false;
        $this->activePackageId = null;
    }

    public function pickArticleForPackageItem(int $articleId): void
    {
        $team = Auth::user()->currentTeam;
        $article = app(\Platform\Core\Contracts\CatalogArticleResolverInterface::class)->resolve($articleId, $team->id);
        if (!$article) return;
        $this->newPackageItem['article_id'] = $article['id'];
        $this->newPackageItem['name']       = (string) $article['name'];
        $this->newPackageItem['gruppe']     = (string) ($article['category_name'] ?? $this->newPackageItem['gruppe']);
        $this->newPackageItem['gebinde']    = (string) ($article['gebinde'] ?? '');
        $this->newPackageItem['vk']         = (float) ($article['vk'] ?? 0);
    }

    public function addPackageItem(): void
    {
        if (!$this->activePackageId) {
            return;
        }
        $team = Auth::user()->currentTeam;

        $article = null;
        if (!empty($this->newPackageItem['article_id'])) {
            $article = app(\Platform\Core\Contracts\CatalogArticleResolverInterface::class)
                ->resolve((int) $this->newPackageItem['article_id'], $team->id);
        }

        $maxSort = (int) ArticlePackageItem::where('package_id', $this->activePackageId)->max('sort_order');

        ArticlePackageItem::create([
            'team_id'    => $team->id,
            'user_id'    => Auth::id(),
            'package_id' => $this->activePackageId,
            'article_id' => $article['id'] ?? null,
            'name'       => $this->newPackageItem['name'] ?: ($article['name'] ?? ''),
            'gruppe'     => $this->newPackageItem['gruppe'],
            'quantity'   => (int) $this->newPackageItem['quantity'],
            'gebinde'    => $this->newPackageItem['gebinde'] ?: ($article['gebinde'] ?? ''),
            'vk'         => (float) ($this->newPackageItem['vk'] ?: ($article['vk'] ?? 0)),
            'gesamt'     => (float) ($this->newPackageItem['gesamt'] ?: 0),
            'sort_order' => $maxSort + 1,
        ]);

        $this->newPackageItem = [
            'article_id' => null, 'name' => '', 'gruppe' => '',
            'quantity' => 1, 'gebinde' => '', 'vk' => 0, 'gesamt' => 0,
        ];
    }

    public function deletePackageItem(int $itemId): void
    {
        if (!$this->activePackageId) {
            return;
        }
        ArticlePackageItem::where('package_id', $this->activePackageId)->where('id', $itemId)->delete();
    }

    // ========== Render ==========

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $packagesQuery = ArticlePackage::where('team_id', $team->id)
            ->withCount('items');

        if ($this->search !== '') {
            $q = '%' . $this->search . '%';
            $packagesQuery->where('name', 'like', $q);
        }

        $packages = $packagesQuery->orderBy('sort_order')->orderBy('name')->get();

        $packageItems = collect();
        if ($this->activePackageId) {
            $packageItems = ArticlePackageItem::where('package_id', $this->activePackageId)
                ->with('article')
                ->orderBy('sort_order')->get();
        }

        $packageArticleMatches = $this->showPackageItemsModal
            ? \Platform\Events\Services\ArticleSearchService::search($team->id, (string) ($this->newPackageItem['name'] ?? ''))
            : collect();

        $bausteine = \Platform\Events\Services\SettingsService::bausteine($team->id);

        return view('events::livewire.articles', [
            'packages'              => $packages,
            'packageItems'          => $packageItems,
            'packageArticleMatches' => $packageArticleMatches,
            'bausteine'             => $bausteine,
        ])->layout('platform::layouts.app');
    }
}
