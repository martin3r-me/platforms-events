<div class="space-y-4">
    @php
        $statusMap = [
            'draft'    => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'label' => 'Entwurf'],
            'sent'     => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700',  'label' => 'Versandt'],
            'signed'   => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'Unterzeichnet'],
            'rejected' => ['bg' => 'bg-red-50',    'text' => 'text-red-700',   'label' => 'Abgelehnt'],
        ];
        $typeOptions = [
            'nutzungsvertrag'    => 'Nutzungsvertrag',
            'optionsbestaetigung'=> 'Optionsbestätigung',
        ];
    @endphp

    <x-ui-panel>
        <div class="p-4 flex justify-between items-start gap-4 flex-wrap">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] mb-2">Verträge</h3>
                @if($contracts->isEmpty())
                    <p class="text-xs text-[var(--ui-muted)]">Kein Vertrag angelegt.</p>
                @else
                    <div class="flex gap-2 flex-wrap">
                        @foreach($contracts as $c)
                            @php $sb = $statusMap[$c->status] ?? $statusMap['draft']; @endphp
                            <button wire:click="selectContract({{ $c->id }})"
                                    class="px-3 py-1.5 rounded-md border text-xs flex items-center gap-2 transition-colors
                                           {{ $activeContract && $activeContract->id === $c->id
                                              ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]'
                                              : 'border-[var(--ui-border)] bg-white text-[var(--ui-muted)] hover:border-[var(--ui-primary)]/40' }}">
                                <span class="font-mono font-bold">v{{ $c->version }}</span>
                                <span class="text-[0.6rem]">{{ $typeOptions[$c->type] ?? $c->type }}</span>
                                <span class="text-[0.62rem] font-bold px-1.5 py-0.5 rounded-full {{ $sb['bg'] }} {{ $sb['text'] }}">{{ $sb['label'] }}</span>
                                @if($c->is_current)
                                    <span class="text-[0.6rem] font-bold text-[var(--ui-primary)]">·aktuell</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <select wire:change="createContract($event.target.value); $event.target.value=''"
                        class="border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    <option value="">+ Neuer Vertrag…</option>
                    @foreach($typeOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if($activeContract)
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="newVersion"
                                 wire:confirm="Neue Version anlegen?">
                        @svg('heroicon-o-document-duplicate', 'w-3.5 h-3.5 inline') Neue Version
                    </x-ui-button>
                @endif
            </div>
        </div>

        @if($activeContract)
            <div class="p-4 border-t border-[var(--ui-border)] flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-mono font-bold text-[var(--ui-primary)]">v{{ $activeContract->version }}</span>
                    <select wire:change="setStatus($event.target.value)"
                            class="border border-[var(--ui-border)] rounded-md px-2 py-1 text-xs bg-white">
                        @foreach($statusMap as $key => $meta)
                            <option value="{{ $key }}" @if($activeContract->status === $key) selected @endif>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <span class="text-[0.62rem] text-[var(--ui-muted)]">Token: <span class="font-mono">{{ Str::limit($activeContract->token, 12) }}</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openEdit">
                        @svg('heroicon-o-pencil', 'w-3.5 h-3.5 inline') Text bearbeiten
                    </x-ui-button>
                    <a href="{{ route('events.public.contract', ['token' => $activeContract->token]) }}" target="_blank"
                       class="text-xs text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                        @svg('heroicon-o-eye', 'w-3.5 h-3.5') Public-Ansicht
                    </a>
                    <x-ui-button variant="secondary-outline" size="sm"
                                 :href="route('events.contract.pdf', ['event' => $event->slug, 'contractId' => $activeContract->id])">
                        @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5 inline mr-1') PDF
                    </x-ui-button>
                    <x-ui-button variant="danger-outline" size="sm"
                                 wire:click="deleteContract({{ $activeContract->id }})"
                                 wire:confirm="Vertrag löschen?">
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                    </x-ui-button>
                </div>
            </div>

            <div class="p-4 border-t border-[var(--ui-border)]">
                <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Vertragstext (Preview)</p>
                <div class="bg-[var(--ui-muted-5)] rounded-md p-3 text-xs text-[var(--ui-secondary)] whitespace-pre-wrap max-h-60 overflow-y-auto">
                    {{ $activeContract->content['text'] ?? '(kein Text)' }}
                </div>
            </div>
        @endif
    </x-ui-panel>

    <x-ui-modal wire:model="showEditModal" size="xl" :hideFooter="true">
        <x-slot name="header">Vertrag bearbeiten</x-slot>
        <form wire:submit.prevent="saveContent" class="space-y-4">
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                <select wire:model="contractType" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    @foreach($typeOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Text (Markdown/Plain erlaubt)</label>
                <textarea wire:model="contractText" rows="20"
                          class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('showEditModal', false)">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
            </div>
        </form>
    </x-ui-modal>
</div>
