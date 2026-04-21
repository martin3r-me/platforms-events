<div class="space-y-4 max-w-[960px]">
    @php
        $statusMeta = [
            'draft'    => ['bg' => '#fef3c7', 'color' => '#d97706', 'label' => 'Entwurf'],
            'sent'     => ['bg' => '#dbeafe', 'color' => '#2563eb', 'label' => 'Versendet'],
            'signed'   => ['bg' => '#dcfce7', 'color' => '#16a34a', 'label' => 'Unterschrieben'],
            'rejected' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'label' => 'Abgelehnt'],
        ];
        // Typ-Labels stammen ausschliesslich aus aktiven Dokumentvorlagen.
        $typeLabels = [];
        foreach ($templates as $tpl) {
            if ($tpl->slug) $typeLabels[$tpl->slug] = $tpl->label;
        }
        // Legacy-Fallbacks fuer bereits existierende Vertraege ohne Vorlage
        $legacyLabels = [
            'nutzungsvertrag'     => 'Nutzungsvertrag',
            'optionsbestaetigung' => 'Optionsbestätigung',
        ];

        // Group contracts by root-parent for version history
        $versionGroups = [];
        foreach ($contracts as $c) {
            $rootId = $c->parent_id ?? $c->id;
            $versionGroups[$rootId][] = $c;
        }
        $currentByGroup = [];
        foreach ($versionGroups as $rootId => $list) {
            usort($list, fn($a, $b) => ($b->version ?? 1) <=> ($a->version ?? 1));
            $currentByGroup[$rootId] = $list[0] ?? null;
        }
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2.5">
            <div class="w-[3px] h-4 bg-purple-600 rounded-sm"></div>
            <h2 class="text-[0.9rem] font-bold text-[var(--ui-secondary)]">Verträge</h2>
        </div>
        <div class="relative" x-data="{ dropOpen: false }">
            <button type="button" @click="dropOpen = !dropOpen"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 border-0 rounded-md bg-purple-600 hover:bg-purple-700 text-white text-[0.68rem] font-bold cursor-pointer">
                @svg('heroicon-o-plus', 'w-3 h-3')
                Neues Dokument
                @svg('heroicon-o-chevron-down', 'w-2.5 h-2.5 ml-0.5')
            </button>
            <div x-show="dropOpen" @click.outside="dropOpen = false" x-cloak
                 class="absolute right-0 top-[calc(100%+4px)] z-[100] bg-white border border-slate-200 rounded-lg p-1 shadow-lg min-w-[260px]">
                @forelse($templates as $tpl)
                    <button type="button" wire:click="createContract('{{ $tpl->slug }}')" @click="dropOpen = false"
                            class="flex items-center gap-2 w-full px-2.5 py-2 border-0 rounded bg-white hover:bg-slate-50 cursor-pointer text-left text-[0.65rem] text-slate-700 transition">
                        <div class="w-2 h-2 rounded-full flex-shrink-0" style="background: {{ $tpl->color ?: '#7c3aed' }};"></div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold truncate">{{ $tpl->label }}</div>
                            @if($tpl->description)
                                <div class="text-[0.55rem] text-slate-400 truncate">{{ $tpl->description }}</div>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="px-3 py-3 text-center text-[0.6rem] text-slate-400">
                        Noch keine Dokumentvorlagen angelegt.<br>
                        <a href="{{ route('events.settings') }}?tab=templates" class="text-purple-600 font-semibold no-underline hover:underline">In Einstellungen anlegen</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if($contracts->isEmpty())
        <div class="bg-white border border-[var(--ui-border)] rounded-xl p-10 text-center">
            @svg('heroicon-o-document-text', 'w-10 h-10 text-slate-300 mx-auto mb-3')
            <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">Noch keine Verträge erstellt.</p>
        </div>
    @else
        {{-- Current contract cards per group --}}
        <div class="space-y-2">
            @foreach($currentByGroup as $rootId => $ct)
                @php
                    $s = $statusMeta[$ct->status] ?? $statusMeta['draft'];
                    $versionHistory = collect($versionGroups[$rootId])->reject(fn($x) => $x->id === $ct->id)->sortBy('version')->values();
                @endphp
                <div>
                    <div class="bg-white border border-[var(--ui-border)] rounded-xl px-4 py-3.5"
                         x-data="{ historyOpen: false }">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-[0.58rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap"
                                  style="background: {{ $s['bg'] }}; color: {{ $s['color'] }};">{{ $s['label'] }}</span>
                            <span class="text-[0.56rem] font-bold px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-600 border border-purple-200 flex-shrink-0">v{{ $ct->version ?? 1 }}</span>
                            @if($ct->is_current === false)
                                <span class="text-[0.54rem] font-semibold text-[var(--ui-muted)] italic flex-shrink-0">Alte Version</span>
                            @endif

                            <div class="flex-1 min-w-0">
                                <p class="text-[0.72rem] font-semibold text-[var(--ui-secondary)] m-0">
                                    {{ $typeLabels[$ct->type] ?? ($legacyLabels[$ct->type] ?? $ct->type) }} Nr. {{ $event->event_number }}
                                </p>
                                <p class="text-[0.62rem] text-[var(--ui-muted)] mt-0.5">
                                    Erstellt am {{ $ct->created_at->format('d.m.Y') }}
                                    @if($ct->sent_at) · Versendet {{ $ct->sent_at->format('d.m.Y') }} @endif
                                </p>
                            </div>

                            <div class="flex gap-1.5 flex-wrap">
                                @if($ct->status === 'draft')
                                    <button wire:click="selectContract({{ $ct->id }}); $wire.openEdit()"
                                            class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold cursor-pointer">
                                        Editor
                                    </button>
                                @endif
                                <a href="{{ route('events.contract.pdf', ['event' => $event->slug, 'contractId' => $ct->id]) }}" target="_blank"
                                   class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold no-underline">
                                    PDF
                                </a>
                                <button type="button"
                                        x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText('{{ route('events.public.contract', ['token' => $ct->token]) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        :class="copied ? 'bg-green-50 border-green-200 text-green-600' : 'bg-white border-slate-200 text-slate-600'"
                                        class="px-2.5 py-1 border rounded-md hover:bg-slate-50 text-[0.62rem] font-semibold cursor-pointer">
                                    <span x-text="copied ? 'Kopiert' : 'Link'"></span>
                                </button>
                                @if($ct->status === 'draft')
                                    <button wire:click="selectContract({{ $ct->id }}); $wire.setStatus('sent')"
                                            class="px-2.5 py-1 border-0 rounded-md bg-purple-600 hover:bg-purple-700 text-white text-[0.62rem] font-bold cursor-pointer">
                                        Versenden
                                    </button>
                                @endif
                                @if(in_array($ct->status, ['sent', 'signed', 'rejected']) && $ct->is_current)
                                    <button wire:click="selectContract({{ $ct->id }}); $wire.newVersion()"
                                            wire:confirm="Neue Version anlegen?"
                                            class="px-2.5 py-1 border border-purple-600 rounded-md bg-white hover:bg-purple-50 text-purple-600 text-[0.62rem] font-semibold cursor-pointer">
                                        Neue Version
                                    </button>
                                @endif
                                <button wire:click="deleteContract({{ $ct->id }})" wire:confirm="Vertrag wirklich löschen?"
                                        class="px-2.5 py-1 border border-red-200 rounded-md bg-red-50 hover:bg-red-100 text-red-500 text-[0.62rem] font-semibold cursor-pointer">
                                    Löschen
                                </button>
                            </div>
                        </div>
                    </div>

                    @if($versionHistory->isNotEmpty())
                        <div class="mt-0.5" x-data="{ historyOpen: false }">
                            <button @click="historyOpen = !historyOpen"
                                    class="flex items-center gap-1 px-2.5 py-0.5 bg-transparent border-0 cursor-pointer text-[0.58rem] font-semibold text-[var(--ui-muted)] hover:text-slate-500 transition">
                                <svg class="w-2 h-2 transition-transform" :class="historyOpen ? 'rotate-90' : ''"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span>{{ $versionHistory->count() }} weitere Version{{ $versionHistory->count() > 1 ? 'en' : '' }}</span>
                            </button>
                            <div x-show="historyOpen" x-cloak class="ml-3.5 border-l-2 border-purple-200 pl-2.5">
                                @foreach($versionHistory as $hct)
                                    @php $hs = $statusMeta[$hct->status] ?? $statusMeta['draft']; @endphp
                                    <div class="flex items-center gap-2 py-0.5 text-[0.58rem] text-slate-500">
                                        <span class="font-bold text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded-full text-[0.52rem]">v{{ $hct->version ?? 1 }}</span>
                                        <span class="font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap text-[0.56rem]"
                                              style="background: {{ $hs['bg'] }}; color: {{ $hs['color'] }};">{{ $hs['label'] }}</span>
                                        <span>{{ $hct->created_at->format('d.m.Y') }}</span>
                                        <a href="{{ route('events.contract.pdf', ['event' => $event->slug, 'contractId' => $hct->id]) }}" target="_blank"
                                           class="text-purple-600 no-underline font-semibold">PDF</a>
                                        <a href="{{ route('events.public.contract', ['token' => $hct->token]) }}" target="_blank"
                                           class="text-green-700 no-underline font-semibold">Ansehen</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Editor Modal (kept as Livewire-modal fallback) --}}
    <x-ui-modal wire:model="showEditModal" size="xl" :hideFooter="true">
        <x-slot name="header">Vertrag bearbeiten</x-slot>
        <form wire:submit.prevent="saveContent" class="space-y-4">
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Typ</label>
                <select wire:model="contractType" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    @foreach($typeLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                    @php $currentKey = $contractType; @endphp
                    @if($currentKey && !isset($typeLabels[$currentKey]))
                        <option value="{{ $currentKey }}">{{ $currentKey }}</option>
                    @endif
                </select>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)]">Text (Markdown)</label>
                    <label class="flex items-center gap-1 px-2 py-0.5 border border-slate-200 rounded bg-white hover:bg-slate-50 text-[0.6rem] font-semibold text-slate-600 cursor-pointer">
                        @svg('heroicon-o-photo', 'w-3 h-3')
                        Bild einfügen
                        <input type="file" wire:model="contractImage" accept="image/*" class="hidden">
                    </label>
                </div>
                <div wire:loading wire:target="contractImage" class="text-[0.6rem] text-slate-500 mb-1">Bild wird hochgeladen …</div>
                @if(session('contractImageError'))
                    <div class="text-[0.6rem] text-red-500 mb-1">{{ session('contractImageError') }}</div>
                @endif
                <textarea wire:model="contractText" rows="20"
                          class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono"></textarea>
                <p class="text-[0.58rem] text-[var(--ui-muted)] mt-1">
                    Markdown wird im PDF &amp; der öffentlichen Ansicht formatiert. Platzhalter wie <code class="text-purple-600">{EVENT_NUMBER}</code>, <code class="text-purple-600">{CUSTOMER_COMPANY}</code> etc. werden beim Rendern mit Event-Daten ersetzt.
                </p>

                <div class="mt-2" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                            class="flex items-center gap-1 text-[0.62rem] font-semibold text-purple-600 hover:text-purple-700 border-0 bg-transparent p-0 cursor-pointer">
                        <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        Verfügbare Platzhalter
                    </button>
                    <div x-show="open" x-cloak class="grid grid-cols-2 gap-1 mt-2 p-2 bg-slate-50 rounded border border-slate-100">
                        @foreach(\Platform\Events\Services\ContractRenderer::availablePlaceholders() as $key => $desc)
                            <div class="flex items-center gap-1.5 text-[0.6rem]">
                                <code class="px-1 py-0.5 bg-white border border-slate-200 rounded text-purple-600 font-mono">{{ $key }}</code>
                                <span class="text-slate-500 truncate">{{ $desc }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('showEditModal', false)">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Speichern</x-ui-button>
            </div>
        </form>
    </x-ui-modal>
</div>
