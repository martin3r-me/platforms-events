<?php

namespace Platform\Events\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Events\Services\SettingsService;

class Settings extends Component
{
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

    public function render()
    {
        return view('events::livewire.settings')->layout('platform::layouts.app');
    }
}
