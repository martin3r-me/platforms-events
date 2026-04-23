<div class="space-y-3 max-w-[860px]">
    {{-- Header --}}
    <div class="mb-2">
        <p class="text-[0.95rem] font-bold text-[var(--ui-secondary)]">Aktivitäten · Übersicht</p>
        <p class="text-[0.62rem] text-[var(--ui-muted)]">Zusammenfassung der letzten Änderungen je Bereich</p>
    </div>

    {{-- Status-Leiste --}}
    @php
        $statusDot = match ($event->status ?? '') {
            'Vertrag', 'Definitiv' => 'bg-green-500',
            'Storno'               => 'bg-red-500',
            default                => 'bg-amber-500',
        };
    @endphp
    <div class="bg-white border border-[var(--ui-border)] rounded-lg px-4 py-3 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full {{ $statusDot }} flex-shrink-0"></div>
            <span class="text-[0.72rem] font-semibold text-[var(--ui-secondary)]">{{ $event->name ?: $event->event_number }}</span>
            <span class="text-[0.62rem] text-[var(--ui-muted)]">
                @if($event->start_date && $event->end_date)
                    {{ $event->start_date->format('d.m.') }} – {{ $event->end_date->format('d.m.Y') }}
                @endif
                @if($event->location)
                    · {{ $event->location }}
                @endif
            </span>
        </div>
        <span class="text-[0.62rem] text-[var(--ui-muted)] font-mono">
            @if($lastChange)
                Letzte Änderung: {{ $lastChange->diffForHumans() }}
            @endif
        </span>
    </div>

    {{-- Bereichs-Karten Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
        @foreach($sections as $key => $section)
            @php
                $status = $sectionStatus[$key] ?? 'empty';
                $badgeClasses = match ($status) {
                    'ok'      => 'bg-green-100 text-green-800 border-green-300',
                    'pending' => 'bg-amber-100 text-amber-800 border-amber-300',
                    'error'   => 'bg-red-100 text-red-800 border-red-300',
                    default   => 'bg-slate-100 text-slate-600 border-slate-300',
                };
                $badgeLabel = match ($status) {
                    'ok'      => 'Aktuell',
                    'pending' => 'In Bearbeitung',
                    'error'   => 'Problem',
                    default   => 'Offen',
                };
                $items = $sectionActivities[$key] ?? collect();
                $count = $counts[$key] ?? 0;
            @endphp
            <div class="bg-white border border-[var(--ui-border)] rounded-lg px-4 py-3.5">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-md {{ $section['bg'] }} flex items-center justify-center">
                            @svg($section['icon'], 'w-3.5 h-3.5 '.$section['text'])
                        </div>
                        <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">{{ $section['label'] }}</span>
                    </div>
                    <span class="text-[0.58rem] font-semibold px-1.5 py-0.5 rounded-full border {{ $badgeClasses }}">{{ $badgeLabel }}</span>
                </div>
                @php $latest = $items->first(); @endphp
                <div class="flex flex-col gap-1">
                    <span class="text-[0.65rem] text-slate-700">{{ $count }} Einträge</span>
                    @if($latest)
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-[0.62rem] text-[var(--ui-muted)] truncate">{{ $latest->description }}</span>
                            <span class="text-[0.6rem] text-[var(--ui-muted)] font-mono flex-shrink-0">{{ $latest->created_at->diffForHumans(null, true) }}</span>
                        </div>
                    @else
                        <span class="text-[0.62rem] text-[var(--ui-muted)] italic">Noch keine Aktivitäten</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Chronologische Zeitleiste --}}
    <div class="bg-white border border-[var(--ui-border)] rounded-lg overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-100 flex items-center gap-1.5">
            <div class="w-[3px] h-3 bg-blue-600 rounded-sm"></div>
            <span class="text-[0.68rem] font-semibold text-[var(--ui-secondary)]">Kommunikation & Änderungen</span>
            <span class="text-[0.6rem] text-[var(--ui-muted)] ml-1">— {{ $activities->count() }} Einträge</span>
        </div>

        @if($activities->isEmpty())
            <div class="py-8 text-center text-[0.68rem] text-[var(--ui-muted)]">
                Noch keine Einträge vorhanden.
            </div>
        @else
            <div class="py-1.5">
                @foreach($activities as $a)
                    @php
                        $section = $typeToSection[$a->type] ?? 'basis';
                        $typeBadge = match ($section) {
                            'angebot'    => 'bg-purple-50 text-purple-700',
                            'basis'      => 'bg-blue-50 text-blue-700',
                            'raeume'     => 'bg-green-50 text-green-700',
                            'bestellung' => 'bg-orange-50 text-orange-700',
                            'ablauf'     => 'bg-cyan-50 text-cyan-700',
                            'feedback'   => 'bg-pink-50 text-pink-700',
                            'vertrag'    => 'bg-indigo-50 text-indigo-700',
                            'rechnung'   => 'bg-emerald-50 text-emerald-700',
                            'packliste'  => 'bg-amber-50 text-amber-700',
                            default      => 'bg-slate-100 text-slate-600',
                        };
                        $typeLabel = $sections[$section]['label'] ?? ucfirst($section);
                    @endphp
                    <div class="group flex items-start px-4 py-1.5 hover:bg-slate-50 transition">
                        <div class="w-20 flex-shrink-0 text-[0.6rem] text-[var(--ui-muted)] font-mono pt-0.5 leading-tight">
                            <div>{{ $a->created_at->format('d.m.Y') }}</div>
                            <div>{{ $a->created_at->format('H:i') }}</div>
                        </div>
                        <div class="w-px bg-slate-200 self-stretch mx-3.5 flex-shrink-0"></div>
                        <div class="flex items-start gap-2 flex-1 flex-wrap min-w-0">
                            <span class="text-[0.58rem] font-bold px-1.5 py-0.5 rounded-full whitespace-nowrap flex-shrink-0 mt-0.5 {{ $typeBadge }}">{{ $typeLabel }}</span>
                            <div class="flex-1 min-w-0">
                                <span class="text-[0.65rem] text-slate-700">{{ $a->description }}</span>
                                @if($a->user)
                                    <span class="text-[0.6rem] text-[var(--ui-muted)] ml-1.5">— {{ $a->user }}</span>
                                @endif
                            </div>
                        </div>
                        <button wire:click="delete('{{ $a->uuid }}')"
                                wire:confirm="Eintrag löschen?"
                                class="flex-shrink-0 text-[var(--ui-muted)] hover:text-red-600 p-1 opacity-0 group-hover:opacity-100 transition">
                            @svg('heroicon-o-x-mark', 'w-3 h-3')
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="px-4 py-2 border-t border-slate-100 bg-slate-50">
                <span class="text-[0.6rem] text-[var(--ui-muted)]">Zeigt die letzten {{ $activities->count() }} Einträge.</span>
            </div>
        @endif
    </div>
</div>
