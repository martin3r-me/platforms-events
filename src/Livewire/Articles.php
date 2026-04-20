<?php

namespace Platform\Events\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\ArticlePackageItem;

class Articles extends Component
{
    #[Url(as: 'tab', except: 'articles')]
    public string $activeTab = 'articles';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'group', except: '')]
    public string $groupFilter = '';

    // ========== Article Modal ==========
    public bool $showArticleModal = false;
    public ?string $editingArticleUuid = null;

    #[Validate('required|string|max:500')]
    public string $articleName = '';

    #[Validate('required|string|max:30')]
    public string $articleNumber = '';

    #[Validate('nullable|string|max:100')]
    public ?string $articleExternalCode = null;

    #[Validate('nullable|integer')]
    public ?int $articleGroupId = null;

    #[Validate('nullable|string')]
    public ?string $articleDescription = null;

    #[Validate('nullable|string')]
    public ?string $articleOfferText = null;

    #[Validate('nullable|string')]
    public ?string $articleInvoiceText = null;

    #[Validate('nullable|string|max:80')]
    public string $articleGebinde = '';

    #[Validate('nullable|numeric')]
    public float $articleEk = 0;

    #[Validate('nullable|numeric')]
    public float $articleVk = 0;

    #[Validate('nullable|string|max:5')]
    public string $articleMwst = '19%';

    #[Validate('nullable|string|max:20')]
    public ?string $articleErloeskonto = null;

    #[Validate('nullable|string|max:100')]
    public string $articleLagerort = '';

    public int $articleMinBestand = 0;
    public int $articleCurrentBestand = 0;
    public bool $articleIsActive = true;

    // ========== Group Modal ==========
    public bool $showGroupModal = false;
    public ?string $editingGroupUuid = null;

    #[Validate('required|string|max:100')]
    public string $groupName = '';

    public ?int $groupParentId = null;
    public string $groupColor = '#6366f1';
    public string $groupErloeskonto7 = '8300';
    public string $groupErloeskonto19 = '8400';
    public int $groupSortOrder = 0;
    public bool $groupIsActive = true;

    // ========== Package Modal ==========
    public bool $showPackageModal = false;
    public ?string $editingPackageUuid = null;

    #[Validate('required|string|max:200')]
    public string $packageName = '';

    public ?string $packageDescription = null;
    public ?int $packageArticleGroupId = null;
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

    // ========== Article ==========

    public function openArticleCreate(): void
    {
        $this->resetArticleForm();
        $this->showArticleModal = true;
    }

    public function openArticleEdit(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        $a = Article::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail();

        $this->editingArticleUuid   = $a->uuid;
        $this->articleName          = $a->name;
        $this->articleNumber        = $a->article_number;
        $this->articleExternalCode  = $a->external_code;
        $this->articleGroupId       = $a->article_group_id;
        $this->articleDescription   = $a->description;
        $this->articleOfferText     = $a->offer_text;
        $this->articleInvoiceText   = $a->invoice_text;
        $this->articleGebinde       = $a->gebinde ?? '';
        $this->articleEk            = (float) $a->ek;
        $this->articleVk            = (float) $a->vk;
        $this->articleMwst          = $a->mwst;
        $this->articleErloeskonto   = $a->erloeskonto;
        $this->articleLagerort      = $a->lagerort ?? '';
        $this->articleMinBestand    = (int) $a->min_bestand;
        $this->articleCurrentBestand = (int) $a->current_bestand;
        $this->articleIsActive      = (bool) $a->is_active;

        $this->resetErrorBag();
        $this->showArticleModal = true;
    }

    public function closeArticleModal(): void
    {
        $this->showArticleModal = false;
        $this->resetArticleForm();
    }

    protected function resetArticleForm(): void
    {
        $this->reset([
            'editingArticleUuid', 'articleName', 'articleNumber', 'articleExternalCode',
            'articleGroupId', 'articleDescription', 'articleOfferText', 'articleInvoiceText',
            'articleGebinde', 'articleEk', 'articleVk', 'articleErloeskonto',
            'articleLagerort', 'articleMinBestand', 'articleCurrentBestand',
        ]);
        $this->articleMwst = '19%';
        $this->articleIsActive = true;
        $this->resetErrorBag();
    }

    public function saveArticle(): void
    {
        $data = $this->validate([
            'articleName'          => 'required|string|max:500',
            'articleNumber'        => 'required|string|max:30',
            'articleExternalCode'  => 'nullable|string|max:100',
            'articleGroupId'       => 'nullable|integer',
            'articleDescription'   => 'nullable|string',
            'articleOfferText'     => 'nullable|string',
            'articleInvoiceText'   => 'nullable|string',
            'articleGebinde'       => 'nullable|string|max:80',
            'articleEk'            => 'nullable|numeric',
            'articleVk'            => 'nullable|numeric',
            'articleMwst'          => 'nullable|string|max:5',
            'articleErloeskonto'   => 'nullable|string|max:20',
            'articleLagerort'      => 'nullable|string|max:100',
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        $payload = [
            'article_group_id'  => $this->articleGroupId,
            'article_number'    => $this->articleNumber,
            'external_code'     => $this->articleExternalCode,
            'name'              => $this->articleName,
            'description'       => $this->articleDescription,
            'offer_text'        => $this->articleOfferText,
            'invoice_text'      => $this->articleInvoiceText,
            'gebinde'           => $this->articleGebinde,
            'ek'                => $this->articleEk,
            'vk'                => $this->articleVk,
            'mwst'              => $this->articleMwst,
            'erloeskonto'       => $this->articleErloeskonto,
            'lagerort'          => $this->articleLagerort,
            'min_bestand'       => $this->articleMinBestand,
            'current_bestand'   => $this->articleCurrentBestand,
            'is_active'         => $this->articleIsActive,
        ];

        if ($this->editingArticleUuid) {
            Article::where('team_id', $team->id)->where('uuid', $this->editingArticleUuid)->update($payload);
        } else {
            $payload['team_id']    = $team->id;
            $payload['user_id']    = $user->id;
            $payload['sort_order'] = (int) Article::where('team_id', $team->id)->max('sort_order') + 1;
            Article::create($payload);
        }

        $this->showArticleModal = false;
        $this->resetArticleForm();
    }

    public function deleteArticle(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        Article::where('team_id', $team->id)->where('uuid', $uuid)->delete();
    }

    // ========== Group ==========

    public function openGroupCreate(): void
    {
        $this->resetGroupForm();
        $this->showGroupModal = true;
    }

    public function openGroupEdit(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        $g = ArticleGroup::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail();

        $this->editingGroupUuid    = $g->uuid;
        $this->groupName           = $g->name;
        $this->groupParentId       = $g->parent_id;
        $this->groupColor          = $g->color ?: '#6366f1';
        $this->groupErloeskonto7   = $g->erloeskonto_7 ?: '8300';
        $this->groupErloeskonto19  = $g->erloeskonto_19 ?: '8400';
        $this->groupSortOrder      = (int) $g->sort_order;
        $this->groupIsActive       = (bool) $g->is_active;

        $this->resetErrorBag();
        $this->showGroupModal = true;
    }

    public function closeGroupModal(): void
    {
        $this->showGroupModal = false;
        $this->resetGroupForm();
    }

    protected function resetGroupForm(): void
    {
        $this->reset([
            'editingGroupUuid', 'groupName', 'groupParentId',
            'groupSortOrder',
        ]);
        $this->groupColor = '#6366f1';
        $this->groupErloeskonto7 = '8300';
        $this->groupErloeskonto19 = '8400';
        $this->groupIsActive = true;
        $this->resetErrorBag();
    }

    public function saveGroup(): void
    {
        $this->validate([
            'groupName'           => 'required|string|max:100',
            'groupParentId'       => 'nullable|integer',
            'groupColor'          => 'nullable|string|max:20',
            'groupErloeskonto7'   => 'nullable|string|max:20',
            'groupErloeskonto19'  => 'nullable|string|max:20',
            'groupSortOrder'      => 'integer',
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        $payload = [
            'parent_id'       => $this->groupParentId,
            'name'            => $this->groupName,
            'color'           => $this->groupColor,
            'erloeskonto_7'   => $this->groupErloeskonto7,
            'erloeskonto_19'  => $this->groupErloeskonto19,
            'sort_order'      => $this->groupSortOrder,
            'is_active'       => $this->groupIsActive,
        ];

        if ($this->editingGroupUuid) {
            ArticleGroup::where('team_id', $team->id)->where('uuid', $this->editingGroupUuid)->update($payload);
        } else {
            $payload['team_id'] = $team->id;
            $payload['user_id'] = $user->id;
            ArticleGroup::create($payload);
        }

        $this->showGroupModal = false;
        $this->resetGroupForm();
    }

    public function deleteGroup(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        ArticleGroup::where('team_id', $team->id)->where('uuid', $uuid)->delete();
    }

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

        $this->editingPackageUuid     = $p->uuid;
        $this->packageName            = $p->name;
        $this->packageDescription     = $p->description;
        $this->packageArticleGroupId  = $p->article_group_id;
        $this->packageColor           = $p->color ?: '#8b5cf6';
        $this->packageIsActive        = (bool) $p->is_active;

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
        $this->reset([
            'editingPackageUuid', 'packageName', 'packageDescription',
            'packageArticleGroupId',
        ]);
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
            'article_group_id' => $this->packageArticleGroupId,
            'name'             => $this->packageName,
            'description'      => $this->packageDescription,
            'color'            => $this->packageColor,
            'is_active'        => $this->packageIsActive,
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

    public function addPackageItem(): void
    {
        if (!$this->activePackageId) {
            return;
        }
        $team = Auth::user()->currentTeam;

        $article = null;
        if (!empty($this->newPackageItem['article_id'])) {
            $article = Article::where('team_id', $team->id)->find($this->newPackageItem['article_id']);
        }

        $maxSort = (int) ArticlePackageItem::where('package_id', $this->activePackageId)->max('sort_order');

        ArticlePackageItem::create([
            'team_id'    => $team->id,
            'user_id'    => Auth::id(),
            'package_id' => $this->activePackageId,
            'article_id' => $article?->id,
            'name'       => $this->newPackageItem['name'] ?: ($article?->name ?? ''),
            'gruppe'     => $this->newPackageItem['gruppe'],
            'quantity'   => (int) $this->newPackageItem['quantity'],
            'gebinde'    => $this->newPackageItem['gebinde'] ?: ($article?->gebinde ?? ''),
            'vk'         => (float) ($this->newPackageItem['vk'] ?: $article?->vk ?? 0),
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

        $groups = ArticleGroup::where('team_id', $team->id)
            ->orderBy('sort_order')->orderBy('name')->get();

        $articlesQuery = Article::where('team_id', $team->id)->with('group');
        if ($this->search !== '') {
            $q = '%' . $this->search . '%';
            $articlesQuery->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $q)
                    ->orWhere('article_number', 'like', $q)
                    ->orWhere('external_code', 'like', $q);
            });
        }
        if ($this->groupFilter !== '') {
            $articlesQuery->where('article_group_id', (int) $this->groupFilter);
        }
        $articles = $articlesQuery->orderBy('sort_order')->orderBy('name')->paginate(50);

        $packages = ArticlePackage::where('team_id', $team->id)
            ->with('group')
            ->withCount('items')
            ->orderBy('sort_order')->orderBy('name')->get();

        // Items des aktiven Pakets (nur wenn Modal offen)
        $packageItems = collect();
        if ($this->activePackageId) {
            $packageItems = ArticlePackageItem::where('package_id', $this->activePackageId)
                ->with('article')
                ->orderBy('sort_order')->get();
        }

        return view('events::livewire.articles', [
            'groups'       => $groups,
            'articles'     => $articles,
            'packages'     => $packages,
            'packageItems' => $packageItems,
        ])->layout('platform::layouts.app');
    }
}
