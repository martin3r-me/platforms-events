<div class="space-y-4 max-w-[960px]">
    @php
        $audienceMap = [
            'participant' => 'Teilnehmer',
            'client'      => 'Auftraggeber',
            'vendor'      => 'Dienstleister',
            'other'       => 'Sonstige',
        ];
        $audienceBadge = [
            'participant' => 'bg-blue-50 text-blue-700',
            'client'      => 'bg-purple-50 text-purple-700',
            'vendor'      => 'bg-orange-50 text-orange-700',
            'other'       => 'bg-slate-100 text-slate-600',
        ];
        $stars = fn($n) => str_repeat('★', (int)$n) . str_repeat('☆', 5 - (int)$n);
        $fmtAvg = fn($v) => $v === null ? '—' : number_format((float)$v, 1, ',', '');
    @endphp

    {{-- Feedback-Links Panel --}}
    <div class="bg-slate-50 border border-[var(--ui-border)] rounded-lg overflow-hidden">
        <div class="flex items-center justify-between px-3.5 py-2.5 border-b border-[var(--ui-border)] bg-white">
            <div class="flex items-center gap-2">
                <div class="w-[3px] h-3.5 bg-blue-600 rounded-sm flex-shrink-0"></div>
                <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Feedback-Links</span>
                <span class="text-[0.6rem] text-[var(--ui-muted)]">· Individuelle Links für Teilnehmer, Auftraggeber etc.</span>
            </div>
            <button type="button" wire:click="toggleNewLink"
                    class="flex items-center gap-1 px-3 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white border-0 cursor-pointer text-[0.65rem] font-semibold transition">
                @svg('heroicon-o-plus', 'w-3 h-3')
                Neuer Link
            </button>
        </div>

        @if($showNewLink)
            <div class="flex items-center gap-2 px-3.5 py-2.5 border-b border-slate-100 bg-amber-50">
                <input type="text" wire:model="newLabel" wire:keydown.enter="createLink"
                       placeholder="Bezeichnung, z.B. »Teilnehmer allgemein«"
                       class="flex-1 border border-slate-200 rounded px-2 py-1 text-[0.68rem]">
                <select wire:model="newAudience"
                        class="w-[140px] border border-slate-200 rounded px-2 py-1 text-[0.68rem] cursor-pointer">
                    @foreach($audienceMap as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="createLink"
                        class="px-3 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white border-0 cursor-pointer text-[0.65rem] font-semibold whitespace-nowrap">
                    Generieren
                </button>
                <button type="button" wire:click="toggleNewLink"
                        class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-500 border border-slate-200 cursor-pointer text-[0.65rem]">
                    ✕
                </button>
            </div>
        @endif

        <div class="px-3.5 py-2.5 flex flex-col gap-1.5">
            @forelse($links as $link)
                @php $publicUrl = route('events.public.feedback', ['token' => $link->token]); @endphp
                <div class="bg-white border border-[var(--ui-border)] rounded-md px-3 py-2.5 flex items-center gap-3"
                     x-data="{ copied: false }">
                    <div class="w-[30px] h-[30px] rounded-md bg-blue-50 border border-blue-200 flex items-center justify-center flex-shrink-0">
                        @svg('heroicon-o-link', 'w-3.5 h-3.5 text-blue-600')
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[0.68rem] font-semibold text-[var(--ui-secondary)]">{{ $link->label }}</span>
                            <span class="text-[0.55rem] font-semibold px-1.5 py-0.5 rounded-full {{ $audienceBadge[$link->audience] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $audienceMap[$link->audience] ?? $link->audience }}
                            </span>
                            <button wire:click="toggleActive({{ $link->id }})"
                                    class="text-[0.55rem] font-semibold px-1.5 py-0.5 rounded-full {{ $link->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $link->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </button>
                        </div>
                        <p class="text-[0.58rem] text-[var(--ui-muted)] mt-0.5 font-mono truncate">{{ $publicUrl }}</p>
                    </div>
                    <div class="flex gap-3.5 flex-shrink-0 text-center">
                        <div>
                            <p class="text-[0.72rem] font-bold text-[var(--ui-secondary)] m-0">{{ $link->view_count ?? 0 }}</p>
                            <p class="text-[0.55rem] text-[var(--ui-muted)] m-0">Aufrufe</p>
                        </div>
                        <div>
                            <p class="text-[0.72rem] font-bold text-green-700 m-0">{{ $link->entries_count }}</p>
                            <p class="text-[0.55rem] text-[var(--ui-muted)] m-0">Eingaben</p>
                        </div>
                        <div>
                            <p class="text-[0.55rem] text-[var(--ui-muted)] m-0">{{ $link->created_at->format('d.m.y') }}</p>
                            <p class="text-[0.55rem] text-[var(--ui-muted)] m-0">Erstellt</p>
                        </div>
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $publicUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                :class="copied
                                    ? 'bg-green-100 border-green-300 text-green-700'
                                    : 'bg-slate-100 hover:bg-slate-200 border-slate-200 text-slate-600'"
                                class="flex items-center gap-1 px-2.5 py-1 rounded border cursor-pointer text-[0.62rem] font-semibold whitespace-nowrap transition">
                            <template x-if="!copied">
                                @svg('heroicon-o-document-duplicate', 'w-3 h-3')
                            </template>
                            <template x-if="copied">
                                @svg('heroicon-o-check', 'w-3 h-3')
                            </template>
                            <span x-text="copied ? 'Kopiert!' : 'Link kopieren'"></span>
                        </button>
                        <a href="{{ $publicUrl }}" target="_blank"
                           class="flex items-center gap-1 px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-600 text-[0.62rem] font-semibold no-underline">
                            @svg('heroicon-o-arrow-top-right-on-square', 'w-3 h-3')
                            Vorschau
                        </a>
                        <button wire:click="deleteLink({{ $link->id }})"
                                wire:confirm="Feedback-Link wirklich löschen? Bestehende Bewertungen bleiben erhalten, der Link funktioniert aber nicht mehr."
                                class="px-1.5 py-1 rounded hover:bg-red-50 text-red-500 border-0 cursor-pointer">
                            @svg('heroicon-o-trash', 'w-3 h-3')
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-4 text-center text-[var(--ui-muted)] text-[0.68rem]">
                    Noch kein Feedback-Link erstellt. Über „Neuer Link" eine URL generieren und an Teilnehmer oder Auftraggeber verteilen.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-[0.95rem] font-bold text-[var(--ui-secondary)]">Eingegangene Bewertungen</p>
            <p class="text-[0.65rem] text-[var(--ui-muted)]">{{ $total }} Bewertungen gesamt</p>
        </div>
    </div>

    {{-- Kennzahlen --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-3.5">
        @foreach([
            ['key' => 'overall',      'label' => 'Gesamtbewertung'],
            ['key' => 'location',     'label' => 'Location'],
            ['key' => 'catering',     'label' => 'Catering'],
            ['key' => 'organization', 'label' => 'Organisation'],
        ] as $k)
            <div class="bg-white border border-[var(--ui-border)] rounded-lg px-3.5 py-3 text-center">
                <p class="text-2xl font-bold text-[var(--ui-secondary)] m-0 leading-none">{{ $fmtAvg($avg[$k['key']] ?? null) }}</p>
                <p class="text-[0.58rem] text-amber-500 mt-1 mb-0.5 tracking-wider">★★★★★</p>
                <p class="text-[0.6rem] text-[var(--ui-muted)] m-0 font-medium">{{ $k['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Feedback-Einträge --}}
    @if($entries->isNotEmpty())
        <div class="flex flex-col gap-2">
            @foreach($entries as $entry)
                <div class="bg-white border border-[var(--ui-border)] rounded-lg overflow-hidden"
                     x-data="{ expanded: false }">
                    <div class="flex items-center gap-3 px-3.5 py-2.5 cursor-pointer" @click="expanded = !expanded">
                        <div class="w-[30px] h-[30px] rounded-full bg-blue-50 border border-blue-200 flex items-center justify-center flex-shrink-0">
                            @svg('heroicon-o-user', 'w-3.5 h-3.5 text-blue-600')
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[0.72rem] font-semibold text-[var(--ui-secondary)]">{{ $entry->name ?: 'Anonym' }}</span>
                                <span class="text-[0.55rem] font-semibold px-1.5 py-0.5 rounded-full {{ $audienceBadge[$entry->link?->audience ?? 'other'] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ $audienceMap[$entry->link?->audience ?? 'other'] ?? ($entry->link?->audience ?? '—') }}
                                </span>
                                <span class="text-[0.6rem] text-[var(--ui-muted)]">{{ $entry->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            <p class="text-[0.65rem] text-slate-500 mt-0.5 truncate">{{ $entry->comment ?: '—' }}</p>
                        </div>
                        <div class="hidden md:flex gap-4 flex-shrink-0">
                            @foreach([
                                ['key' => 'rating_overall',      'label' => 'Gesamt'],
                                ['key' => 'rating_location',     'label' => 'Location'],
                                ['key' => 'rating_catering',     'label' => 'Catering'],
                                ['key' => 'rating_organization', 'label' => 'Organisation'],
                            ] as $r)
                                <div class="text-center">
                                    <p class="text-[0.55rem] text-[var(--ui-muted)] m-0 uppercase tracking-wider">{{ $r['label'] }}</p>
                                    <p class="text-[0.68rem] font-bold text-amber-500 m-0 tracking-wider">{{ $stars($entry->{$r['key']} ?? 0) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <svg :class="expanded ? 'rotate-180' : ''" class="w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0 transition-transform"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div x-show="expanded" x-cloak class="border-t border-slate-100 px-3.5 py-2.5 bg-slate-50">
                        <p class="text-[0.6rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-1.5">Vollständiger Kommentar</p>
                        <p class="text-[0.72rem] text-slate-700 m-0 leading-relaxed whitespace-pre-wrap">{{ $entry->comment ?: '—' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12 text-[var(--ui-muted)]">
            @svg('heroicon-o-chat-bubble-left-right', 'w-8 h-8 mx-auto mb-2.5 opacity-40')
            <p class="text-[0.72rem] m-0">Noch kein Feedback erfasst.</p>
        </div>
    @endif
</div>
