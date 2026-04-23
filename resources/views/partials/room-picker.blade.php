@props([
    'model'       => '',   {{-- z.B. 'inlineSchedule.uuid.raum' oder 'newScheduleInline.raum' --}}
    'rooms'       => [],   {{-- [{value, short, label}, …] --}}
    'current'     => '',
    'disabled'    => false,
    'placeholder' => 'Raum wählen …',
    'compact'     => false, {{-- kompakter Stil fuer Tabellenzeilen --}}
])

{{--
    Raum-Picker: im geschlossenen Zustand nur Kuerzel, im Dropdown Kuerzel — Name.
    Dropdown wird an <body> teleportiert, damit der Stacking-Context von x-ui-panel
    ihn nicht verdeckt.
--}}
<div x-data="{
        open: false,
        val: @js((string) $current),
        pos: { top: 0, left: 0, width: 0 },
        rooms: @js(array_values($rooms)),
        get currentShort() {
            const r = this.rooms.find(x => x.value === this.val);
            return r?.short || this.val || '';
        },
        recalc() {
            const btn = this.$refs.trigger;
            if (!btn) return;
            const rect = btn.getBoundingClientRect();
            this.pos = {
                top: rect.bottom + window.scrollY + 2,
                left: rect.left + window.scrollX,
                width: Math.max(220, rect.width),
            };
        },
        toggle() {
            if (this.$root.dataset.disabled === 'true') return;
            if (!this.open) this.recalc();
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
     x-init="$watch(() => $wire.get('{{ $model }}'), v => val = v || '')"
     @keydown.escape.window="open = false"
     @resize.window="open = false"
     @scroll.window.passive="open = false"
     data-disabled="{{ $disabled ? 'true' : 'false' }}"
     class="w-full relative">
    @if($compact)
        <button type="button" x-ref="trigger" @click="toggle()" @disabled($disabled)
                class="w-full flex items-center justify-between gap-1 border border-transparent hover:border-[var(--ui-border)] focus:border-[var(--ui-primary)]/60 rounded px-2 py-1 text-xs bg-transparent focus:bg-white cursor-pointer {{ $disabled ? 'opacity-60 cursor-not-allowed' : '' }}">
            <span x-show="val" x-text="currentShort" class="truncate font-mono"></span>
            <span x-show="!val" class="text-slate-400">{{ $placeholder }}</span>
            <svg class="w-2.5 h-2.5 text-slate-400 transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    @else
        <button type="button" x-ref="trigger" @click="toggle()" @disabled($disabled)
                class="w-full flex items-center justify-between gap-1 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs bg-white hover:border-[var(--ui-primary)]/40 cursor-pointer {{ $disabled ? 'opacity-60 cursor-not-allowed' : '' }}">
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
            <div class="max-h-60 overflow-y-auto py-1">
                <template x-for="r in rooms" :key="r.value">
                    <button type="button" @click="pick(r.value)"
                            :class="r.value === val ? 'bg-blue-50' : ''"
                            class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-slate-50 transition">
                        <span class="text-[0.65rem] font-mono font-bold text-[var(--ui-secondary)] min-w-[40px]" x-text="r.short"></span>
                        <template x-if="r.label !== r.short">
                            <span class="text-[0.65rem] text-slate-500 truncate" x-text="r.label.substring(r.short.length + 3)"></span>
                        </template>
                        <template x-if="r.value === val">
                            <span class="ml-auto text-blue-600 flex-shrink-0">@svg('heroicon-o-check', 'w-3 h-3')</span>
                        </template>
                    </button>
                </template>
                <div x-show="rooms.length === 0" class="px-3 py-3 text-center text-[0.65rem] text-slate-400">
                    Noch keine Räume angelegt
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
