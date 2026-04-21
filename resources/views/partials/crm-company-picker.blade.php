@props([
    'available'   => false,
    'options'     => [],
    'label'       => null,
    'url'         => null,
    'currentId'   => null,
    'fallback'    => null,
    'placeholder' => '— CRM-Firma wählen —',
])

{{--
    Suchbarer CRM-Company-Picker (lose gekoppelt via CrmCompany{Options|Resolver}Interface).
    Server-seitige Suche ueber Livewire-Property $crmCompanySearch mit 300ms Debounce.
    Ohne gebundenen Contract (CRM-Modul nicht installiert) faellt der Picker auf einen
    schlichten Freitext-Input (event.customer) zurueck.
--}}
@if(!$available)
    <div class="flex items-center gap-1.5">
        <input wire:model.blur="event.customer" type="text"
               placeholder="{{ $fallback ?? 'Unternehmen' }}"
               class="flex-1 border border-[var(--ui-border)] rounded-md px-2 py-1 text-[0.7rem] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
        <span class="text-[0.55rem] text-[var(--ui-muted)] italic" title="CRM-Modul nicht installiert">
            @svg('heroicon-o-information-circle', 'w-3 h-3 inline')
        </span>
    </div>
@else
    <div x-data="{ open: false }"
         @click.outside="open = false"
         @keydown.escape.window="open = false"
         class="relative w-full">
        <div class="w-full flex items-center gap-1 border border-[var(--ui-border)] rounded-md bg-white px-2 py-1 hover:border-[var(--ui-primary)]/40 transition">
            <button type="button" @click="open = !open"
                    class="flex-1 flex items-center gap-1.5 min-w-0 text-left bg-transparent border-0 cursor-pointer">
                @svg('heroicon-o-building-office-2', 'w-3 h-3 text-slate-400 flex-shrink-0')
                @if($label)
                    <span class="truncate text-[0.7rem] font-medium text-[var(--ui-secondary)]">{{ $label }}</span>
                @else
                    <span class="text-[0.7rem] text-slate-400">{{ $placeholder }}</span>
                @endif
            </button>
            @if($url && $label)
                <a href="{{ $url }}" target="_blank" title="In CRM öffnen"
                   class="p-0.5 text-blue-500 hover:text-blue-700 flex-shrink-0">
                    @svg('heroicon-o-arrow-top-right-on-square', 'w-3 h-3')
                </a>
            @endif
            @if($currentId)
                <button type="button" wire:click="clearCrmCompany"
                        class="p-0.5 text-slate-400 hover:text-red-500 flex-shrink-0" title="Auswahl löschen">
                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                </button>
            @endif
            <button type="button" @click="open = !open"
                    class="p-0.5 text-slate-400 flex-shrink-0 bg-transparent border-0 cursor-pointer">
                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>

        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="absolute z-40 mt-1 w-full bg-white border border-[var(--ui-border)] rounded-md shadow-lg overflow-hidden">
            <div class="p-2 border-b border-slate-100 bg-slate-50">
                <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded px-2 py-1">
                    @svg('heroicon-o-magnifying-glass', 'w-3 h-3 text-slate-400 flex-shrink-0')
                    <input type="text"
                           wire:model.live.debounce.300ms="crmCompanySearch"
                           placeholder="Firma suchen …"
                           @click.stop
                           class="flex-1 bg-transparent border-0 outline-none text-[0.7rem] placeholder:text-slate-400">
                    @if(trim($crmCompanySearch ?? '') !== '')
                        <button type="button" @click.stop wire:click="$set('crmCompanySearch', '')"
                                class="p-0.5 text-slate-400 hover:text-slate-600">
                            @svg('heroicon-o-x-mark', 'w-2.5 h-2.5')
                        </button>
                    @endif
                </div>
            </div>

            <div class="max-h-64 overflow-y-auto py-1" wire:loading.class="opacity-50" wire:target="crmCompanySearch">
                @forelse($options as $opt)
                    <button type="button"
                            wire:click="pickCrmCompany({{ $opt['value'] }}, @js($opt['label']))"
                            @click="open = false"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-slate-50 transition {{ $currentId === ($opt['value'] ?? null) ? 'bg-blue-50' : '' }}">
                        @svg('heroicon-o-building-office-2', 'w-3 h-3 text-slate-400 flex-shrink-0')
                        <span class="flex-1 text-[0.7rem] text-[var(--ui-secondary)] truncate">{{ $opt['label'] }}</span>
                        @if($currentId === ($opt['value'] ?? null))
                            <span class="text-blue-600">@svg('heroicon-o-check', 'w-3 h-3')</span>
                        @endif
                    </button>
                @empty
                    <div class="px-3 py-3 text-center text-[0.65rem] text-slate-400">
                        @if(trim($crmCompanySearch ?? '') !== '')
                            Keine Treffer für „{{ $crmCompanySearch }}"
                        @else
                            Keine Firmen verfügbar
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endif
