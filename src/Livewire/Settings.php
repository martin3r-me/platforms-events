<?php

namespace Platform\Events\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Events\Livewire\Concerns\HasContractImageUpload;
use Platform\Events\Models\DocumentTemplate;
use Platform\Events\Models\FlatRateRule;
use Platform\Events\Models\MrFieldConfig;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Services\FlatRateApplicator;
use Platform\Events\Services\FlatRateEngine;
use Platform\Events\Services\PositionValidator;
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
    public array $scheduleDescriptions = [];
    public array $dayTypes        = [];
    public array $beverageModes   = [];
    public array $bausteine       = [];
    public string $orderNumberSchema = '';
    public bool $attachFloorPlansDefault = false;
    public int $quoteDefaultValidityDays = 14;

    public string $newCostCenter  = '';
    public string $newCostCarrier = '';
    public string $newQuoteStatus = '';
    public string $newOrderStatus = '';
    public string $newEventType   = '';
    public string $newBestuhlung  = '';
    public string $newScheduleDescription = '';
    public string $newDayType     = '';
    public string $newBeverageMode = '';
    public array $newBaustein     = ['name' => '', 'bg' => '#f8fafc', 'text' => '#64748b'];

    // ========== Management-Report-Felder ==========
    public bool $mrModal = false;
    public ?int $mrEditingId = null;
    public array $mrForm = [
        'group_label' => '',
        'label'       => '',
        'options'     => [], // [['label' => '...', 'color' => 'red|yellow|green|gray'], ...]
        'is_active'   => true,
    ];
    public string $mrNewOptionLabel = '';

    /** Erlaubte Farben fuer MR-Optionen (passt zur Detail-Cockpit-Anzeige). */
    public const MR_OPTION_COLORS = ['red', 'yellow', 'green', 'gray'];

    // ========== Pauschal-Kalkulations-Regeln ==========
    public bool $flatRateModal = false;
    public ?int $flatRateEditingId = null;
    public array $flatRateForm = [
        'name'                    => '',
        'description'             => '',
        'scope_typs'              => '',
        'scope_event_types'       => '',
        'formula'                 => '',
        'output_name'             => '',
        'output_gruppe'           => '',
        'output_mwst'             => '19%',
        'output_procurement_type' => '',
        'priority'                => 100,
        'is_active'               => true,
    ];
    public ?int $flatRateDryRunItemId = null;
    public ?array $flatRateDryRunResult = null;

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
    public $tplHtmlFile = null;

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
        $this->bestuhlungOptions     = SettingsService::bestuhlungOptions($teamId);
        $this->scheduleDescriptions  = SettingsService::scheduleDescriptions($teamId);
        $this->dayTypes              = SettingsService::dayTypes($teamId);
        $this->beverageModes         = SettingsService::beverageModesFull($teamId);
        $this->bausteine             = SettingsService::bausteine($teamId);
        $this->orderNumberSchema     = SettingsService::orderNumberSchema($teamId);
        $this->attachFloorPlansDefault = SettingsService::attachFloorPlansDefault($teamId);
        $this->quoteDefaultValidityDays = SettingsService::quoteDefaultValidityDays($teamId);
    }

    public function updatedAttachFloorPlansDefault(bool $value): void
    {
        SettingsService::setAttachFloorPlansDefault($this->teamId(), $value);
    }

    public function updatedQuoteDefaultValidityDays(int|string $value): void
    {
        $days = max(1, (int) $value);
        $this->quoteDefaultValidityDays = $days;
        SettingsService::setQuoteDefaultValidityDays($this->teamId(), $days);
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
    public function addScheduleDescription(): void { $this->pushSimple('scheduleDescriptions', 'newScheduleDescription'); SettingsService::setScheduleDescriptions($this->teamId(), $this->scheduleDescriptions); }
    public function removeScheduleDescription(int $i): void { $this->removeAt('scheduleDescriptions', $i); SettingsService::setScheduleDescriptions($this->teamId(), $this->scheduleDescriptions); }
    public function addDayType(): void          { $this->pushSimple('dayTypes', 'newDayType'); SettingsService::setDayTypes($this->teamId(), $this->dayTypes); }
    public function removeDayType(int $i): void { $this->removeAt('dayTypes', $i); SettingsService::setDayTypes($this->teamId(), $this->dayTypes); }
    public function addBeverageMode(): void
    {
        $name = trim($this->newBeverageMode);
        if ($name === '') return;
        // Duplikate per Name (case-insensitive) verhindern.
        foreach ($this->beverageModes as $existing) {
            if (mb_strtolower((string) ($existing['name'] ?? '')) === mb_strtolower($name)) {
                $this->newBeverageMode = '';
                return;
            }
        }
        // „Auf Anfrage"-Substring setzt die Flags vor, damit bestehende
        // Bestaetigungslogik weiterhin greift; sonst beide off.
        $onRequest = SettingsService::isOnRequestBeverageMode($name);
        $this->beverageModes[] = [
            'name'             => $name,
            'hide_unit_price'  => $onRequest,
            'hide_total_price' => $onRequest,
        ];
        $this->newBeverageMode = '';
        SettingsService::setBeverageModes($this->teamId(), $this->beverageModes);
    }

    public function removeBeverageMode(int $i): void
    {
        $this->removeAt('beverageModes', $i);
        SettingsService::setBeverageModes($this->teamId(), $this->beverageModes);
    }

    /** Schaltet 'hide_unit_price' oder 'hide_total_price' fuer einen Modus um. */
    public function toggleBeverageModeFlag(int $i, string $flag): void
    {
        if (!in_array($flag, ['hide_unit_price', 'hide_total_price'], true)) return;
        if (!isset($this->beverageModes[$i])) return;
        $this->beverageModes[$i][$flag] = !($this->beverageModes[$i][$flag] ?? false);
        SettingsService::setBeverageModes($this->teamId(), $this->beverageModes);
    }

    /** Livewire-Property -> SettingsService::set*-Methode (fuer Reorder der Simple-Listen). */
    protected const SIMPLE_LIST_SETTERS = [
        'costCenters'          => 'setCostCenters',
        'costCarriers'         => 'setCostCarriers',
        'quoteStatuses'        => 'setQuoteStatuses',
        'orderStatuses'        => 'setOrderStatuses',
        'eventTypes'           => 'setEventTypes',
        'bestuhlungOptions'    => 'setBestuhlungOptions',
        'scheduleDescriptions' => 'setScheduleDescriptions',
        'dayTypes'             => 'setDayTypes',
        'beverageModes'        => 'setBeverageModes',
    ];

    public function moveSimpleItem(string $list, int $i, int $direction): void
    {
        if (!isset(self::SIMPLE_LIST_SETTERS[$list])) return;
        if ($direction !== -1 && $direction !== 1) return;
        $arr = $this->{$list};
        $j = $i + $direction;
        if (!isset($arr[$i]) || !isset($arr[$j])) return;
        [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        $this->{$list} = $arr;
        $setter = self::SIMPLE_LIST_SETTERS[$list];
        SettingsService::{$setter}($this->teamId(), $arr);
    }

    public function saveOrderNumberSchema(): void
    {
        SettingsService::setOrderNumberSchema($this->teamId(), $this->orderNumberSchema);
    }

    public function resetOrderNumberSchema(): void
    {
        $this->orderNumberSchema = \Platform\Events\Services\OrderNumberBuilder::DEFAULT_SCHEMA;
        SettingsService::setOrderNumberSchema($this->teamId(), $this->orderNumberSchema);
    }

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
        $this->mrNewOptionLabel = '';
        if ($id) {
            $cfg = MrFieldConfig::where('team_id', $this->teamId())->find($id);
            if (!$cfg) return;
            $this->mrForm = [
                'group_label' => $cfg->group_label,
                'label'       => $cfg->label,
                'options'     => $this->normalizeMrOptions($cfg->options ?? []),
                'is_active'   => (bool) $cfg->is_active,
            ];
        } else {
            $defaults = ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt'];
            $total = count($defaults);
            $opts = [];
            foreach ($defaults as $i => $l) {
                $opts[] = ['label' => $l, 'color' => MrFieldConfig::deriveOptionColor($i, $total, $l)];
            }
            $this->mrForm = [
                'group_label' => '',
                'label'       => '',
                'options'     => $opts,
                'is_active'   => true,
            ];
        }
        $this->mrModal = true;
    }

    /** @param  array<int,mixed>  $options */
    protected function normalizeMrOptions(array $options): array
    {
        $out = [];
        foreach ($options as $o) {
            if (is_array($o)) {
                $l = trim((string) ($o['label'] ?? ''));
                $c = in_array($o['color'] ?? '', self::MR_OPTION_COLORS, true) ? $o['color'] : 'gray';
            } else {
                $l = trim((string) $o);
                $c = 'gray';
            }
            if ($l === '') continue;
            $out[] = ['label' => $l, 'color' => $c];
        }
        return $out;
    }

    public function addMrOption(): void
    {
        $label = trim((string) $this->mrNewOptionLabel);
        if ($label === '') return;
        $i = count($this->mrForm['options']);
        $this->mrForm['options'][] = [
            'label' => $label,
            'color' => MrFieldConfig::deriveOptionColor($i, $i + 1, $label),
        ];
        $this->mrNewOptionLabel = '';
    }

    public function removeMrOption(int $i): void
    {
        if (!isset($this->mrForm['options'][$i])) return;
        unset($this->mrForm['options'][$i]);
        $this->mrForm['options'] = array_values($this->mrForm['options']);
    }

    public function setMrOptionColor(int $i, string $color): void
    {
        if (!in_array($color, self::MR_OPTION_COLORS, true)) return;
        if (!isset($this->mrForm['options'][$i])) return;
        $this->mrForm['options'][$i]['color'] = $color;
    }

    public function moveMrOption(int $i, int $direction): void
    {
        if ($direction !== -1 && $direction !== 1) return;
        $arr = $this->mrForm['options'];
        $j = $i + $direction;
        if (!isset($arr[$i]) || !isset($arr[$j])) return;
        [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        $this->mrForm['options'] = $arr;
    }

    public function saveMrField(): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;

        $label = trim((string) $this->mrForm['label']);
        $group = trim((string) $this->mrForm['group_label']);
        if ($label === '' || $group === '') return;

        $options = $this->normalizeMrOptions($this->mrForm['options'] ?? []);

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

    // ========== Pauschal-Regeln ==========

    public function openFlatRateModal(?int $id = null): void
    {
        $teamId = $this->teamId();
        $this->flatRateEditingId  = $id;
        $this->flatRateDryRunItemId = null;
        $this->flatRateDryRunResult = null;

        if ($id) {
            $rule = FlatRateRule::where('team_id', $teamId)->find($id);
            if (!$rule) return;
            $this->flatRateForm = [
                'name'                    => $rule->name,
                'description'             => (string) $rule->description,
                'scope_typs'              => implode(', ', (array) $rule->scope_typs),
                'scope_event_types'       => implode(', ', (array) $rule->scope_event_types),
                'formula'                 => (string) $rule->formula,
                'output_name'             => $rule->output_name,
                'output_gruppe'           => $rule->output_gruppe,
                'output_mwst'             => $rule->output_mwst ?: '19%',
                'output_procurement_type' => (string) $rule->output_procurement_type,
                'priority'                => (int) $rule->priority,
                'is_active'               => (bool) $rule->is_active,
            ];
        } else {
            $this->flatRateForm = [
                'name'                    => '',
                'description'             => '',
                'scope_typs'              => '',
                'scope_event_types'       => '',
                'formula'                 => 'day.pers_avg * 20',
                'output_name'             => 'Pauschale',
                'output_gruppe'           => '',
                'output_mwst'             => '19%',
                'output_procurement_type' => '',
                'priority'                => 100,
                'is_active'               => true,
            ];
        }
        $this->flatRateModal = true;
    }

    public function saveFlatRate(): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;

        $name  = trim((string) $this->flatRateForm['name']);
        $typs  = self::splitCsv((string) $this->flatRateForm['scope_typs']);
        $gruppe = trim((string) $this->flatRateForm['output_gruppe']);

        if ($name === '' || empty($typs) || $gruppe === '') {
            session()->flash('flatRateError', 'Name, mindestens ein Scope-Typ und Output-Gruppe sind Pflicht.');
            return;
        }

        $allowed = PositionValidator::allowedGruppen($teamId);
        if (!in_array($gruppe, $allowed, true)) {
            session()->flash('flatRateError', 'Output-Gruppe "' . $gruppe . '" existiert nicht im Artikelstamm.');
            return;
        }

        $payload = [
            'name'                    => $name,
            'description'             => trim((string) $this->flatRateForm['description']) ?: null,
            'scope_typs'              => $typs,
            'scope_event_types'       => self::splitCsv((string) $this->flatRateForm['scope_event_types']) ?: null,
            'formula'                 => trim((string) $this->flatRateForm['formula']),
            'output_name'             => trim((string) $this->flatRateForm['output_name']) ?: 'Pauschale',
            'output_gruppe'           => $gruppe,
            'output_mwst'             => $this->flatRateForm['output_mwst'] ?: '19%',
            'output_procurement_type' => trim((string) $this->flatRateForm['output_procurement_type']) ?: null,
            'priority'                => (int) ($this->flatRateForm['priority'] ?? 100),
            'is_active'               => (bool) $this->flatRateForm['is_active'],
        ];

        if ($this->flatRateEditingId) {
            $rule = FlatRateRule::where('team_id', $teamId)->find($this->flatRateEditingId);
            if ($rule) $rule->update($payload);
        } else {
            FlatRateRule::create(array_merge($payload, [
                'team_id' => $teamId,
                'user_id' => Auth::id(),
            ]));
        }

        $this->flatRateModal = false;
        $this->flatRateEditingId = null;
    }

    public function deleteFlatRate(int $id): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;
        FlatRateRule::where('team_id', $teamId)->where('id', $id)->delete();
    }

    public function toggleFlatRateActive(int $id): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;
        $rule = FlatRateRule::where('team_id', $teamId)->find($id);
        if ($rule) $rule->update(['is_active' => !$rule->is_active]);
    }

    public function runFlatRateDryRun(): void
    {
        $teamId = $this->teamId();
        if (!$teamId || !$this->flatRateDryRunItemId) {
            $this->flatRateDryRunResult = null;
            return;
        }

        // Dummy-Regel aus dem aktuellen Formular bauen (kein Persist).
        $rule = new FlatRateRule([
            'formula'         => (string) $this->flatRateForm['formula'],
            'output_gruppe'   => (string) $this->flatRateForm['output_gruppe'],
            'output_name'     => (string) ($this->flatRateForm['output_name'] ?: 'Pauschale'),
            'output_mwst'     => (string) ($this->flatRateForm['output_mwst'] ?: '19%'),
        ]);
        $rule->team_id = $teamId;

        $item = QuoteItem::whereHas('eventDay', fn ($q) => $q->whereHas('event', fn ($q2) => $q2->where('team_id', $teamId)))
            ->find($this->flatRateDryRunItemId);
        if (!$item) {
            $this->flatRateDryRunResult = ['ok' => false, 'error' => 'Vorgang nicht gefunden.', 'value' => null, 'context' => []];
            return;
        }

        $this->flatRateDryRunResult = FlatRateApplicator::dryRun($rule, $item);
    }

    protected static function splitCsv(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $raw)), fn ($v) => $v !== ''));
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
                'html_content' => $this->ensureHtml($tpl->html_content),
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

        // TinyMCE explizit auf den neuen Inhalt setzen (wire:ignore friert den
        // initial-Wert ein, der Event sorgt fuer Refresh bei jedem Oeffnen).
        $this->dispatch('tinymce-set-content',
            uid: 'tiny-template',
            content: $this->tplForm['html_content']
        );
    }

    public function updatedTplHtmlFile(): void
    {
        if (!$this->tplHtmlFile) return;
        try {
            $path = $this->tplHtmlFile->getRealPath();
            $raw = @file_get_contents($path);
            if ($raw === false) {
                throw new \RuntimeException('Datei konnte nicht gelesen werden.');
            }

            // Nur den <body>-Inhalt uebernehmen, falls ein komplettes HTML-Dokument
            if (preg_match('#<body\b[^>]*>(.*?)</body>#is', $raw, $m)) {
                $raw = $m[1];
            }

            // Scripts/Styles entfernen
            $raw = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $raw);
            $raw = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $raw);

            $this->tplForm['html_content'] = trim($raw);

            $this->dispatch('tinymce-set-content',
                uid: 'tiny-template',
                content: \Platform\Events\Services\ContractRenderer::resolveForEditor($this->tplForm['html_content'])
            );
        } catch (\Throwable $e) {
            session()->flash('tplHtmlFileError', 'HTML-Datei konnte nicht geladen werden: ' . $e->getMessage());
        } finally {
            $this->tplHtmlFile = null;
        }
    }

    /**
     * Liefert HTML fuer den TinyMCE-Editor. Wenn der gespeicherte Inhalt noch
     * Markdown/Plaintext ist (alte Vorlagen vor der TinyMCE-Umstellung), wird
     * einmalig Markdown -> HTML konvertiert. Wird der Text gespeichert, liegt
     * er danach dauerhaft als HTML vor. events-asset://-Schemes werden zu
     * frischen signierten URLs aufgeloest, damit TinyMCE Bilder anzeigt.
     */
    protected function ensureHtml(?string $content): string
    {
        $content = (string) $content;
        if ($content === '') return '';
        $html = preg_match('/<\/?[a-z][a-z0-9]*\b[^>]*>/i', $content)
            ? $content
            : \Platform\Events\Services\ContractRenderer::markdownToHtml($content);
        return \Platform\Events\Services\ContractRenderer::resolveForEditor($html);
    }

    public function saveTemplate(): void
    {
        $teamId = $this->teamId();
        if (!$teamId) return;
        $label = trim((string) $this->tplForm['label']);
        if ($label === '') return;

        $slug = trim((string) $this->tplForm['slug']);
        if ($slug === '') $slug = Str::slug($label);

        // HTML direkt speichern (TinyMCE-Output). Signed-Asset-URLs ins
        // events-asset://-Scheme normalisieren, damit der Text zukunftssicher
        // gespeichert ist (Signatur laeuft sonst nach 24h ab).
        $content = (string) $this->tplForm['html_content'];
        $content = \Platform\Events\Services\ContractRenderer::normalizeAssetUrls($content);

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

        $flatRateRules = $teamId
            ? FlatRateRule::where('team_id', $teamId)->orderBy('priority')->orderBy('name')->get()
            : collect();

        // Vorgaenge fuer den Dry-Run-Picker – nur wenn Modal offen und relevant
        $flatRateDryRunItems = collect();
        if ($this->flatRateModal && $teamId) {
            $scopeTyps = self::splitCsv((string) $this->flatRateForm['scope_typs']);
            $q = QuoteItem::whereHas('eventDay.event', fn ($q) => $q->where('team_id', $teamId))
                ->with(['eventDay.event:id,name,event_number']);
            if (!empty($scopeTyps)) {
                $q->whereIn('typ', $scopeTyps);
            }
            $flatRateDryRunItems = $q->orderByDesc('id')->limit(30)->get();
        }

        $flatRateCatalog      = FlatRateEngine::catalog();
        $flatRateAllowedTypes = ['Speisen','Getränke','Personal','Equipment','Technik','Bar','Buffet','Geschirr','Sonstiges'];
        $flatRateAllowedGruppen = PositionValidator::allowedGruppen($teamId);

        return view('events::livewire.settings', [
            'mrFields'               => $mrFields,
            'templates'              => $templates,
            'flatRateRules'          => $flatRateRules,
            'flatRateDryRunItems'    => $flatRateDryRunItems,
            'flatRateCatalog'        => $flatRateCatalog,
            'flatRateAllowedTypes'   => $flatRateAllowedTypes,
            'flatRateAllowedGruppen' => $flatRateAllowedGruppen,
        ])->layout('platform::layouts.app');
    }
}
