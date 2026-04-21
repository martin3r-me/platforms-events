@props([
    'field'       => 'event.responsible',
    'users'       => [],
    'current'     => '',
    'placeholder' => '— Mitglied wählen —',
    'clearable'   => true,
])

{{--
    Suchbarer User-Picker fuer Team-Mitglieder.
    Client-seitige Filterung (skaliert fuer 80+ User) via Alpine.
    Speichert den ausgewaehlten Namen in das Livewire-Property unter $field.
--}}
<div
    x-data="{
        open: false,
        search: '',
        selected: @js((string) $current),
        users: @js($users),
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.users.slice(0, 80);
            return this.users.filter(u =>
                (u.name || '').toLowerCase().includes(q) ||
                (u.email || '').toLowerCase().includes(q)
            ).slice(0, 80);
        },
        toggle() {
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
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative w-full"
>
    <button type="button" @click="toggle()"
            class="w-full flex items-center justify-between gap-2 border border-[var(--ui-border)] rounded-md px-2.5 py-1.5 bg-white hover:border-[var(--ui-primary)]/40 text-[0.7rem] text-left transition">
        <span class="flex items-center gap-1.5 min-w-0">
            @svg('heroicon-o-user', 'w-3 h-3 text-slate-400 flex-shrink-0')
            <span x-show="selected" x-text="selected" class="truncate text-[var(--ui-secondary)] font-medium"></span>
            <span x-show="!selected" class="text-slate-400">{{ $placeholder }}</span>
        </span>
        <span class="flex items-center gap-1 flex-shrink-0">
            @if($clearable)
                <button type="button" x-show="selected" @click.stop="clear()" x-cloak
                        class="p-0.5 text-slate-400 hover:text-red-500" title="Auswahl löschen">
                    @svg('heroicon-o-x-mark', 'w-2.5 h-2.5')
                </button>
            @endif
            <svg class="w-2.5 h-2.5 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="absolute z-40 mt-1 w-full bg-white border border-[var(--ui-border)] rounded-md shadow-lg overflow-hidden">
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
</div>
