<div class="space-y-4 max-w-[960px]" x-data="{ pfMode: 'kitchen' }">
    {{-- Header + Mode Toggle --}}
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div class="flex items-center gap-2.5">
            <div class="w-[3px] h-4 bg-blue-600 rounded-sm"></div>
            <h2 class="text-[0.9rem] font-bold text-[var(--ui-secondary)]">Projekt Function</h2>
        </div>

        <div class="flex bg-slate-100 rounded-md p-0.5 gap-0.5">
            <button type="button" @click="pfMode = 'kitchen'"
                    :class="pfMode === 'kitchen'
                        ? 'bg-white text-[var(--ui-secondary)] font-semibold shadow-sm'
                        : 'bg-transparent text-slate-500 font-medium'"
                    class="flex items-center gap-1.5 px-3 py-1 rounded text-[0.65rem] transition">
                @svg('heroicon-o-home', 'w-3 h-3')
                Küche
            </button>
            <button type="button" @click="pfMode = 'manager'"
                    :class="pfMode === 'manager'
                        ? 'bg-white text-[var(--ui-secondary)] font-semibold shadow-sm'
                        : 'bg-transparent text-slate-500 font-medium'"
                    class="flex items-center gap-1.5 px-3 py-1 rounded text-[0.65rem] transition">
                @svg('heroicon-o-user', 'w-3 h-3')
                Projektleiter
            </button>
        </div>
    </div>

    {{-- Mode description --}}
    <p class="text-[0.72rem] text-slate-600 leading-relaxed mb-5">
        <span x-show="pfMode === 'kitchen'">Küchenversion — Alle Veranstaltungsdetails, Ablaufplan, Speisen und Getränke pro Tag. <strong>Ohne Preise.</strong></span>
        <span x-show="pfMode === 'manager'" x-cloak>Projektleiter-Version — Vollständige Übersicht <strong>mit EK, VK und Gesamtpreisen</strong> pro Position.</span>
    </p>

    {{-- Action buttons --}}
    <div class="flex gap-2.5 mb-6 flex-wrap">
        <a :href="'{{ route('events.projekt-function.pdf', ['event' => $event->slug]) }}?mode=' + pfMode"
           target="_blank"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-[0.72rem] font-bold transition">
            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
            PDF herunterladen
        </a>
        <button type="button" x-data="{ copied: false }"
                @click="navigator.clipboard.writeText('{{ route('events.projekt-function.pdf', ['event' => $event->slug]) }}?mode=' + pfMode).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[0.72rem] font-bold border border-slate-200 transition">
            @svg('heroicon-o-link', 'w-4 h-4')
            <span x-text="copied ? 'Kopiert!' : 'Link kopieren'"></span>
        </button>
        <a :href="'{{ route('events.projekt-function.pdf', ['event' => $event->slug]) }}?preview=1&mode=' + pfMode"
           target="_blank"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-white hover:bg-slate-50 text-slate-600 text-[0.72rem] font-bold border border-slate-200 transition">
            @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
            Vorschau öffnen
        </a>
    </div>

    {{-- Preview --}}
    <div class="bg-slate-50 border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-4 py-2.5 bg-white border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-eye', 'w-3.5 h-3.5 text-slate-400')
                <span class="text-[0.68rem] font-semibold text-slate-500">Vorschau</span>
                <span x-show="pfMode === 'kitchen'" class="text-[0.55rem] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 font-semibold">Küche</span>
                <span x-show="pfMode === 'manager'" x-cloak class="text-[0.55rem] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">Projektleiter</span>
            </div>
        </div>
        <iframe :src="'{{ route('events.projekt-function.pdf', ['event' => $event->slug]) }}?preview=1&mode=' + pfMode"
                class="w-full h-[600px] border-0 bg-white"
                loading="lazy"></iframe>
    </div>
</div>
