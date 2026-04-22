@props([
    'field'       => 'event.responsible',
    'users'       => [],
    'current'     => '',
    'placeholder' => '— Mitglied wählen —',
    'clearable'   => true,
])

{{--
    Suchbarer User-Picker fuer Team-Mitglieder. Dropdown per x-teleport an <body>,
    damit Stacking-Context umschliessender Panels (backdrop-blur) ihn nicht verdeckt.
--}}
<div
    x-data="{
        open: false,
        search: '',
        selected: @js((string) $current),
        users: @js($users),
        pos: { top: 0, left: 0, width: 0 },
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.users.slice(0, 80);
            return this.users.filter(u =>
                (u.name || '').toLowerCase().includes(q) ||
                (u.email || '').toLowerCase().includes(q)
            ).slice(0, 80);
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
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
        },
        pick(u) {
            this.selected = u.name;
            $wire.set('{{ $field }}', u.name);
            this.open = false;
            this.search = '';
        },
        clear() {
            this.selected = '';
            $wire.set('{{ $field }}', '');
            this.open = false;
        }
    }"
    x-init="$watch(() => $wire.get('{{ $field }}'), v => selected = v || '')"
    @keydown.escape.window="open = false"
    @resize.window="recalc()"
    @scroll.window.passive="open = false"
    class="w-full"
>
    <div x-ref="trigger"
         class="w-full flex items-center gap-1 border border-[var(--ui-border)] rounded-md bg-white px-2 py-1 hover:border-[var(--ui-primary)]/40 transition">
        <button type="button" @click="toggle()"
                class="flex-1 flex items-center gap-1.5 min-w-0 text-left bg-transparent border-0 cursor-pointer">
            @svg('heroicon-o-user', 'w-3 h-3 text-slate-400 flex-shrink-0')
            <span x-show="selected" x-text="selected" class="truncate text-[0.7rem] font-medium text-[var(--ui-secondary)]"></span>
            <span x-show="!selected" class="text-[0.7rem] text-slate-400">{{ $placeholder }}</span>
        </button>
        @if($clearable)
            <button type="button" x-show="selected" @click.stop="clear()" x-cloak
                    class="p-0.5 text-slate-400 hover:text-red-500 flex-shrink-0 bg-transparent border-0 cursor-pointer" title="Auswahl löschen">
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
                    <input type="text" x-model="search" x-ref="searchInput"
                           placeholder="Suchen nach Name oder E-Mail …"
                           class="flex-1 bg-transparent border-0 outline-none text-[0.7rem] placeholder:text-slate-400">
                    <span class="text-[0.55rem] text-slate-400 font-mono" x-text="filtered.length + '/' + users.length"></span>
                </div>
            </div>

            <div class="max-h-60 overflow-y-auto py-1">
                <template x-for="u in filtered" :key="u.id">
                    <button type="button" @click="pick(u)"
                            :class="u.name === selected ? 'bg-blue-50' : ''"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-slate-50 transition">
                        <div class="w-5 h-5 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center flex-shrink-0 text-[0.55rem] font-bold"
                             x-text="(u.name || '?').split(' ').map(p => p[0]).join('').slice(0,2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[0.7rem] font-medium text-[var(--ui-secondary)] truncate" x-text="u.name"></p>
                            <p class="text-[0.55rem] text-slate-400 font-mono truncate" x-text="u.email"></p>
                        </div>
                        <span x-show="u.name === selected" class="text-blue-600 flex-shrink-0">
                            @svg('heroicon-o-check', 'w-3 h-3')
                        </span>
                    </button>
                </template>
                <div x-show="filtered.length === 0" class="px-3 py-3 text-center">
                    <p class="text-[0.65rem] text-slate-400 m-0">Keine Treffer</p>
                </div>
            </div>
        </div>
    </template>
</div>
