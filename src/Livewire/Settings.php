<?php

namespace Platform\Events\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Events\Livewire\Concerns\HasContractImageUpload;
use Platform\Events\Models\DocumentTemplate;
use Platform\Events\Models\MrFieldConfig;
use Platform\Events\Services\SettingsService;

class Settings extends Component
{
    use WithFileUploads;
    use HasContractImageUpload;

    public function insertImageMarkdown(string $markdown): void
    {
        $this->tplForm['html_content'] = (string) ($this->tplForm['html_content'] ?? '') . $markdown;
    }

    #[Url(as: 'tab', except: 'cost_centers')]
    public string $activeTab = 'cost_centers';

    public array $costCenters     = [];
    public array $costCarriers    = [];
    public array $quoteStatuses   = [];
    public array $orderStatuses   = [];
    public array $eventTypes      = [];
    public array $bestuhlungOptions = [];
    public array $bausteine       = [];

    public string $newCostCenter  = '';
    public string $newCostCarrier = '';
    public string $newQuoteStatus = '';
    public string $newOrderStatus = '';
    public string $newEventType   = '';
    public string $newBestuhlung  = '';
    public array $newBaustein     = ['name' => '', 'bg' => '#f8fafc', 'text' => '#64748b'];

    // ========== Management-Report-Felder ==========
    public bool $mrModal = false;
    public ?int $mrEditingId = null;
    public array $mrForm = [
        'group_label' => '',
        'label'       => '',
        'options'     => '', // textarea, eine option pro Zeile
        'is_active'   => true,
    ];

    // ========== Document-Templates ==========
    public bool $tplModal = false;
    public ?int $tplEditingId = null;
    public array $tplForm = [
        'label'        => '',
        'slug'         => '',
        'description'  => '',
        'color'        => '#7c3aed',
        'html_content' => '',
        'is_active'    => true,
    ];

    public function mount(): void
    {
        $this->load();
    }

    protected function teamId(): ?int
    {
        return Auth::user()->currentTeam?->id;
    }

    protected function load(): void
    {
        $teamId = $this->teamId();
        $this->costCenters       = SettingsService::costCenters($teamId);
        $this->costCarriers      = SettingsService::costCarriers($teamId);
        $this->quoteStatuses     = SettingsService::quoteStatuses($teamId);
        $this->orderStatuses     = SettingsService::orderStatuses($teamId);
        $this->eventTypes        = SettingsService::eventTypes($teamId);
        $this->bestuhlungOptions = SettingsService::bestuhlungOptions($teamId);
        $this->bausteine         = SettingsService::bausteine($teamId);
    }

    public function addCostCenter(): void      { $this->pushSimple('costCenters', 'newCostCenter'); SettingsService::setCostCenters($this->teamId(), $this->costCenters); }
    public function removeCostCenter(int $i): void { $this->removeAt('costCenters', $i); SettingsService::setCostCenters($this->teamId(), $this->costCenters); }
    public function addCostCarrier(): void     { $this->pushSimple('costCarriers', 'newCostCarrier'); SettingsService::setCostCarriers($this->teamId(), $this->costCarriers); }
    public function removeCostCarrier(int $i): void { $this->removeAt('costCarriers', $i); SettingsService::setCostCarriers($this->teamId(), $this->costCarriers); }
    public function addQuoteStatus(): void     { $this->pushSimple('quoteStatuses', 'newQuoteStatus'); SettingsService::setQuoteStatuses($this->teamId(), $this->quoteStatuses); }
    public function removeQuoteStatus(int $i): void { $this->removeAt('quoteStatuses', $i); SettingsService::setQuoteStatuses($this->teamId(), $this->quoteStatuses); }
    public function addOrderStatus(): void     { $this->pushSimple('orderStatuses', 'newOrderStatus'); SettingsService::setOrderStatuses($this->teamId(), $this->orderStatuses); }
    public function removeOrderStatus(int $i): void { $this->removeAt('orderStatuses', $i); SettingsService::setOrderStatuses($this->teamId(), $this->orderStatuses); }
    public function addEventType(): void       { $this->pushSimple('eventTypes', 'newEventType'); SettingsService::setEventTypes($this->teamId(), $this->eventTypes); }
    public function removeEventType(int $i): void { $this->removeAt('eventTypes', $i); SettingsService::setEventTypes($this->teamId(), $this->eventTypes); }
    public function addBestuhlung(): void      { $this->pushSimple('bestuhlungOptions', 'newBestuhlung'); SettingsService::setBestuhlungOptions($this->teamId(), $this->bestuhlungOptions); }
    public function removeBestuhlung(int $i): void { $this->removeAt('bestuhlungOptions', $i); SettingsService::setBestuhlungOptions($this->teamId(), $this->bestuhlungOptions); }

    public function addBaustein(): void
    {
        if (trim($this->newBaustein['name']) === '') return;
        $this->bausteine[] = [
            'name' => trim($this->newBaustein['name']),
            'bg'   => $this->newBaustein['bg'] ?: '#f8fafc',
            'text' => $this->newBaustein['text'] ?: '#64748b',
        ];
        $this->newBaustein = ['name' => '', 'bg' => '#f8fafc', 'text' => '#64748b'];
        SettingsService::setBausteine($this->teamId(), $this->bausteine);
    }

    public function removeBaustein(int $i): void
    {
        unset($this->bausteine[$i]);
        $this->bausteine = array_values($this->bausteine);
        SettingsService::setBausteine($this->teamId(), $this->bausteine);
    }

    protected function pushSimple(string $list, string $inputProp): void
    {
        $value = trim((string) $this->{$inputProp});
        if ($value === '') return;
        $arr = $this->{$list};
        if (in_array($value, $arr, true)) return;
        $arr[] = $value;
        $this->{$list} = $arr;
        $this->{$inputProp} = '';
    }

    protected function removeAt(string $list, int $i): void
    {
        $arr = $this->{$list};
        unset($arr[$i]);
        $this->{$list} = array_values($arr);
    }

    // ========== MR-Felder ==========

    public function openMrModal(?int $id = null): void
    {
        $this->mrEditingId = $id;
        if ($id) {
            $cfg = MrFieldConfig::where('team_id', $this->teamId())->find($id);
            if (!$cfg) return;
            $this->mrForm = [
                'group_label' => $cfg->group_label,
                'label'       => $cfg->label,
                'options'     => collect($cfg->options ?? [])->pluck('label')->implode("\n"),
                'is_active'   => (bool) $cfg->is_active,
            ];
        } else {
            $this->mrForm = [
                'group_label' => '',
                'label'       => '',
                'options'     => "fehlende Eingabe\nin Bearbeitung\nOK\nabgeschlossen\nnicht benötigt",
                'is_active'   => true,
            ];
        }
        $this->mrModal = true;
    }

    public function saveMrField(): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;

        $label = trim((string) $this->mrForm['label']);
        $group = trim((string) $this->mrForm['group_label']);
        if ($label === '' || $group === '') return;

        $rawOptions = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $this->mrForm['options']))));
        $total = count($rawOptions);
        $options = [];
        foreach ($rawOptions as $i => $opt) {
            $options[] = ['label' => $opt, 'color' => MrFieldConfig::deriveOptionColor($i, $total, $opt)];
        }

        if ($this->mrEditingId) {
            $cfg = MrFieldConfig::where('team_id', $teamId)->find($this->mrEditingId);
            if ($cfg) {
                $cfg->update([
                    'group_label' => $group,
                    'label'       => $label,
                    'options'     => $options,
                    'is_active'   => (bool) $this->mrForm['is_active'],
                ]);
            }
        } else {
            $maxSort = (int) MrFieldConfig::where('team_id', $teamId)->max('sort_order');
            MrFieldConfig::create([
                'team_id'     => $teamId,
                'user_id'     => Auth::id(),
                'group_label' => $group,
                'label'       => $label,
                'options'     => $options,
                'sort_order'  => $maxSort + 1,
                'is_active'   => (bool) $this->mrForm['is_active'],
            ]);
        }

        $this->mrModal = false;
        $this->mrEditingId = null;
    }

    public function deleteMrField(int $id): void
    {
        MrFieldConfig::where('team_id', $this->teamId())->where('id', $id)->delete();
    }

    public function toggleMrActive(int $id): void
    {
        $cfg = MrFieldConfig::where('team_id', $this->teamId())->find($id);
        if ($cfg) $cfg->update(['is_active' => !$cfg->is_active]);
    }

    public function moveMrUp(int $id): void
    {
        $cfg = MrFieldConfig::where('team_id', $this->teamId())->find($id);
        if (!$cfg) return;
        $prev = MrFieldConfig::where('team_id', $this->teamId())
            ->where('sort_order', '<', $cfg->sort_order)
            ->orderByDesc('sort_order')->first();
        if ($prev) {
            $a = $cfg->sort_order; $cfg->update(['sort_order' => $prev->sort_order]); $prev->update(['sort_order' => $a]);
        }
    }

    public function moveMrDown(int $id): void
    {
        $cfg = MrFieldConfig::where('team_id', $this->teamId())->find($id);
        if (!$cfg) return;
        $next = MrFieldConfig::where('team_id', $this->teamId())
            ->where('sort_order', '>', $cfg->sort_order)
            ->orderBy('sort_order')->first();
        if ($next) {
            $a = $cfg->sort_order; $cfg->update(['sort_order' => $next->sort_order]); $next->update(['sort_order' => $a]);
        }
    }

    public function resetMrDefaults(): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;
        MrFieldConfig::where('team_id', $teamId)->forceDelete();
        MrFieldConfig::seedDefaultsFor($teamId, Auth::id());
    }

    // ========== Document-Templates ==========

    public function openTplModal(?int $id = null): void
    {
        $this->tplEditingId = $id;
        if ($id) {
            $tpl = DocumentTemplate::where('team_id', $this->teamId())->find($id);
            if (!$tpl) return;
            $this->tplForm = [
                'label'        => $tpl->label,
                'slug'         => $tpl->slug,
                'description'  => $tpl->description,
                'color'        => $tpl->color ?: '#7c3aed',
                'html_content' => $tpl->html_content,
                'is_active'    => (bool) $tpl->is_active,
            ];
        } else {
            $this->tplForm = [
                'label'        => '',
                'slug'         => '',
                'description'  => '',
                'color'        => '#7c3aed',
                'html_content' => '',
                'is_active'    => true,
            ];
        }
        $this->tplModal = true;
    }

    public function convertTplToMarkdown(): void
    {
        $current = (string) ($this->tplForm['html_content'] ?? '');
        $this->tplForm['html_content'] = $this->htmlToMarkdown($current);
    }

    protected function htmlToMarkdown(string $content): string
    {
        $trim = trim($content);
        if ($trim === '') return '';
        if (!$this->looksLikeHtml($trim)) return $content;

        try {
            $converter = new \League\HTMLToMarkdown\HtmlConverter([
                'strip_tags'                 => true,
                'remove_nodes'               => 'script style',
                'hard_break'                 => true,
                'use_autolinks'              => false,
                'header_style'               => 'atx',
                'bold_style'                 => '**',
                'italic_style'               => '_',
            ]);
            return trim($converter->convert($trim));
        } catch (\Throwable $e) {
            return $content;
        }
    }

    protected function looksLikeHtml(string $s): bool
    {
        return (bool) preg_match('/<\/?[a-z][a-z0-9]*\b[^>]*>/i', $s);
    }

    public function saveTemplate(): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;
        $label = trim((string) $this->tplForm['label']);
        if ($label === '') return;

        $slug = trim((string) $this->tplForm['slug']);
        if ($slug === '') $slug = Str::slug($label);

        $content = (string) $this->tplForm['html_content'];
        if ($this->looksLikeHtml($content)) {
            $content = $this->htmlToMarkdown($content);
            $this->tplForm['html_content'] = $content;
        }

        $data = [
            'label'        => $label,
            'slug'         => $slug,
            'description'  => trim((string) $this->tplForm['description']),
            'color'        => $this->tplForm['color'] ?: '#7c3aed',
            'html_content' => $content,
            'is_active'    => (bool) $this->tplForm['is_active'],
        ];

        if ($this->tplEditingId) {
            $tpl = DocumentTemplate::where('team_id', $teamId)->find($this->tplEditingId);
            if ($tpl) $tpl->update($data);
        } else {
            $maxSort = (int) DocumentTemplate::where('team_id', $teamId)->max('sort_order');
            DocumentTemplate::create(array_merge($data, [
                'team_id'    => $teamId,
                'user_id'    => Auth::id(),
                'sort_order' => $maxSort + 1,
            ]));
        }

        $this->tplModal = false;
        $this->tplEditingId = null;
    }

    public function deleteTemplate(int $id): void
    {
        DocumentTemplate::where('team_id', $this->teamId())->where('id', $id)->delete();
    }

    public function toggleTemplateActive(int $id): void
    {
        $tpl = DocumentTemplate::where('team_id', $this->teamId())->find($id);
        if ($tpl) $tpl->update(['is_active' => !$tpl->is_active]);
    }

    public function render()
    {
        $teamId = $this->teamId();
        if ($teamId) {
            MrFieldConfig::seedDefaultsFor($teamId, Auth::id());
        }

        $mrFields = $teamId
            ? MrFieldConfig::where('team_id', $teamId)->orderBy('sort_order')->get()
            : collect();

        $templates = $teamId
            ? DocumentTemplate::where('team_id', $teamId)->orderBy('sort_order')->get()
            : collect();

        return view('events::livewire.settings', [
            'mrFields'  => $mrFields,
            'templates' => $templates,
        ])->layout('platform::layouts.app');
    }
}
