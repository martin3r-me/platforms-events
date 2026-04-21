@props([
    'model'       => '',   {{-- z.B. 'inlineBookings.uuid.location_id' oder 'newBookingInline.location_id' --}}
    'locations'   => [],   {{-- Collection von Location-Modellen mit id, kuerzel, name --}}
    'current'     => '',
    'placeholder' => '— frei —',
    'compact'     => false,
])

@php
    $locList = collect($locations)->map(fn ($l) => [
        'value' => (string) $l->id,
        'short' => (string) ($l->kuerzel ?? $l->name ?? ''),
        'label' => trim(($l->kuerzel ? $l->kuerzel . ' — ' : '') . ($l->name ?? '')),
    ])->values()->all();
    $currentStr = $current !== null && $current !== '' ? (string) $current : '';
@endphp

<div x-data="{
        open: false,
        search: '',
        val: @js($currentStr),
        pos: { top: 0, left: 0, width: 0 },
        locations: @js($locList),
        get currentShort() {
            const r = this.locations.find(x => x.value === this.val);
            return r?.short || '';
        },
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.locations;
            return this.locations.filter(r =>
                (r.short || '').toLowerCase().includes(q) ||
                (r.label || '').toLowerCase().includes(q)
            );
        },
        recalc() {
            const btn = this.$refs.trigger;
            if (!btn) return;
            const rect = btn.getBoundingClientRect();
            this.pos = {
                top: rect.bottom + window.scrollY + 2,
                left: rect.left + window.scrollX,
                width: Math.max(240, rect.width),
            };
        },
        toggle() {
            if (!this.open) {
                this.recalc();
                this.search = '';
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
            this.open = !this.open;
        },
        pick(v) {
            this.val = v;
            $wire.set('{{ $model }}', v);
            this.open = false;
        },
        clear() {
            this.val = '';
            $wire.set('{{ $model }}', '');
            this.open = false;
        }
     }"
     x-init="$watch(() => $wire.get('{{ $model }}'), v => val = v == null ? '' : String(v))"
     @keydown.escape.window="open = false"
     @resize.window="recalc()"
     @scroll.window.passive="open = false"
     class="w-full relative">
    @if($compact)
        <button type="button" x-ref="trigger" @click="toggle()"
                class="w-full flex items-center justify-between gap-1 border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs bg-transparent focus:bg-white cursor-pointer">
            <span x-show="val" x-text="currentShort" class="truncate font-mono"></span>
            <span x-show="!val" class="text-slate-400">{{ $placeholder }}</span>
            <svg class="w-2.5 h-2.5 text-slate-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    @else
        <button type="button" x-ref="trigger" @click="toggle()"
                class="w-full flex items-center justify-between gap-1 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs bg-white hover:border-[var(--ui-primary)]/40 cursor-pointer">
            <span x-show="val" x-text="currentShort" class="truncate font-mono"></span>
            <span x-show="!val" class="text-slate-400">{{ $placeholder }}</span>
            <svg class="w-2.5 h-2.5 text-slate-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    @endif

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
                    <input x-ref="searchInput" type="text" x-model="search"
                           placeholder="Raum suchen …"
                           @click.stop
                           @keydown.enter.prevent="filtered.length > 0 && pick(filtered[0].value)"
                           class="flex-1 bg-transparent border-0 outline-none text-[0.7rem] placeholder:text-slate-400">
                    <button type="button" x-show="search" @click.stop="search = ''"
                            class="p-0.5 text-slate-400 hover:text-slate-600">
                        @svg('heroicon-o-x-mark', 'w-2.5 h-2.5')
                    </button>
                </div>
            </div>

            <div class="max-h-64 overflow-y-auto py-1">
                <template x-for="r in filtered" :key="r.value">
                    <button type="button" @click="pick(r.value)"
                            :class="r.value === val ? 'bg-blue-50' : ''"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-slate-50 transition">
                        <span class="text-[0.65rem] font-mono font-bold text-[var(--ui-secondary)] min-w-[40px]" x-text="r.short"></span>
                        <span class="flex-1 text-[0.65rem] text-slate-500 truncate"
                              x-text="r.label.startsWith(r.short + ' — ') ? r.label.substring(r.short.length + 3) : r.label"></span>
                        <template x-if="r.value === val">
                            <span class="text-blue-600 flex-shrink-0">@svg('heroicon-o-check', 'w-3 h-3')</span>
                        </template>
                    </button>
                </template>
                <div x-show="filtered.length === 0" class="px-3 py-3 text-center text-[0.65rem] text-slate-400">
                    <template x-if="search">
                        <span>Keine Treffer für „<span x-text="search"></span>"</span>
                    </template>
                    <template x-if="!search">
                        <span>Noch keine Räume angelegt</span>
                    </template>
                </div>
                <button type="button" @click="clear()" x-show="val"
                        class="w-full flex items-center gap-1 px-2.5 py-1.5 text-left border-t border-slate-100 text-[0.62rem] text-red-500 hover:bg-red-50">
                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                    Auswahl löschen
                </button>
            </div>
        </div>
    </template>
</div>
