@props([
    'slot'          => 'organizer',
    'available'     => false,
    'contacts'      => [],
    'currentId'     => null,
    'currentLabel'  => null,
    'hasCompany'    => false,
    'fallbackField' => null,
    'placeholder'   => '— Kontakt wählen —',
])

{{--
    CRM-Kontakt-Picker. Dropdown wird per x-teleport an <body> geheftet, damit
    Stacking-Context umschliessender Panels (backdrop-blur) es nicht ueberdeckt.
--}}
@if(!$available || !$hasCompany)
    <div class="flex items-center gap-1.5">
        @if($fallbackField)
            <input wire:model.blur="event.{{ $fallbackField }}" type="text"
                   placeholder="{{ $placeholder }}"
                   class="flex-1 border border-[var(--ui-border)] rounded-md px-2 py-1 text-[0.7rem] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
        @endif
        @if(!$hasCompany)
            <span class="text-[0.55rem] text-[var(--ui-muted)] italic" title="Erst Firma auswählen, dann stehen CRM-Kontakte zur Verfügung.">
                @svg('heroicon-o-information-circle', 'w-3 h-3 inline')
            </span>
        @endif
    </div>
@else
    <div x-data="{
            open: false,
            search: '',
            pos: { top: 0, left: 0, width: 0 },
            contacts: @js(array_values($contacts)),
            get filtered() {
                const q = this.search.trim().toLowerCase();
                if (!q) return this.contacts;
                return this.contacts.filter(c =>
                    (c.name || '').toLowerCase().includes(q) ||
                    (c.email || '').toLowerCase().includes(q) ||
                    (c.position || '').toLowerCase().includes(q)
                );
            },
            recalc() {
                const btn = this.$refs.trigger;
                if (!btn) return;
                const r = btn.getBoundingClientRect();
                this.pos = {
                    top: r.bottom + window.scrollY + 4,
                    left: r.left + window.scrollX,
                    width: r.width,
                };
            },
            toggle() {
                if (!this.open) this.recalc();
                this.open = !this.open;
                if (this.open) {
                    this.search = '';
                    this.$nextTick(() => document.getElementById('crm-contact-search-{{ $slot }}')?.focus());
                }
            }
         }"
         @keydown.escape.window="open = false"
         @resize.window="recalc()"
         @scroll.window.passive="open = false"
         class="w-full">
        <div class="w-full flex items-center gap-1 border border-[var(--ui-border)] rounded-md bg-white px-2 py-1 hover:border-[var(--ui-primary)]/40 transition"
             x-ref="trigger">
            <button type="button" @click="toggle()"
                    class="flex-1 flex items-center gap-1.5 min-w-0 text-left bg-transparent border-0 cursor-pointer">
                @svg('heroicon-o-user', 'w-3 h-3 text-slate-400 flex-shrink-0')
                @if($currentLabel)
                    <span class="truncate text-[0.7rem] font-medium text-[var(--ui-secondary)]">{{ $currentLabel }}</span>
                @else
                    <span class="text-[0.7rem] text-slate-400">{{ $placeholder }}</span>
                @endif
            </button>
            @if($currentId)
                <button type="button" wire:click="clearCrmContact('{{ $slot }}')"
                        class="p-0.5 text-slate-400 hover:text-red-500 flex-shrink-0" title="Auswahl löschen">
                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                </button>
            @endif
            <button type="button" @click="toggle()"
                    class="p-0.5 text-slate-400 flex-shrink-0 bg-transparent border-0 cursor-pointer">
                <svg class="w-2.5 h-2.5 transition-transform" :class="open ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>

        <template x-teleport="body">
            <div x-show="open" x-cloak
                 @click.outside="open = false"
                 :style="'position:absolute; top:' + pos.top + 'px; left:' + pos.left + 'px; width:' + pos.width + 'px; z-index:9999;'"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="bg-white border border-[var(--ui-border)] rounded-md shadow-xl overflow-hidden">
                <div class="p-2 border-b border-slate-100 bg-slate-50">
                    <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded px-2 py-1">
                        @svg('heroicon-o-magnifying-glass', 'w-3 h-3 text-slate-400 flex-shrink-0')
                        <input type="text" x-model="search" id="crm-contact-search-{{ $slot }}"
                               placeholder="Kontakt suchen …"
                               class="flex-1 bg-transparent border-0 outline-none text-[0.7rem] placeholder:text-slate-400">
                        <span class="text-[0.55rem] text-slate-400 font-mono" x-text="filtered.length + '/' + contacts.length"></span>
                    </div>
                </div>

                <div class="max-h-64 overflow-y-auto py-1">
                    <template x-for="c in filtered" :key="c.id">
                        <button type="button"
                                @click="$wire.pickCrmContact('{{ $slot }}', c.id, c.name); open = false"
                                :class="c.id === {{ $currentId ? (int) $currentId : 0 }} ? 'bg-blue-50' : ''"
                                class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-slate-50 transition">
                            <div class="w-5 h-5 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center flex-shrink-0 text-[0.55rem] font-bold"
                                 x-text="(c.name || '?').split(' ').map(p => p[0]).join('').slice(0,2).toUpperCase()"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1">
                                    <span class="text-[0.7rem] font-medium text-[var(--ui-secondary)] truncate" x-text="c.name"></span>
                                    <template x-if="c.is_primary">
                                        <span class="text-[0.52rem] font-bold px-1 py-0 rounded-full bg-amber-100 text-amber-700">primär</span>
                                    </template>
                                </div>
                                <p class="text-[0.55rem] text-slate-400 truncate">
                                    <span x-show="c.position" x-text="c.position"></span>
                                    <template x-if="c.position && c.email">
                                        <span class="mx-1">·</span>
                                    </template>
                                    <span x-show="c.email" x-text="c.email" class="font-mono"></span>
                                </p>
                            </div>
                            <template x-if="c.id === {{ $currentId ? (int) $currentId : 0 }}">
                                <span class="text-blue-600">@svg('heroicon-o-check', 'w-3 h-3')</span>
                            </template>
                        </button>
                    </template>
                    <div x-show="filtered.length === 0" class="px-3 py-3 text-center">
                        <p class="text-[0.65rem] text-slate-400 m-0" x-text="search ? 'Keine Treffer' : 'Keine Kontakte bei dieser Firma'"></p>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
