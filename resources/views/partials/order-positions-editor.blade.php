@php
    $fmt = $fmt ?? fn($v) => number_format((float)$v, 2, ',', '.');
    $bausteine = $bausteine ?? [];

    $bausteinMap = [];
    foreach ($bausteine as $b) {
        $bausteinMap[mb_strtolower(trim((string) ($b['name'] ?? '')))] = $b;
    }
    $isBaustein = fn(string $g) => isset($bausteinMap[mb_strtolower(trim($g))]);
    $rowInlineStyle = function (string $gruppe) use ($bausteinMap) {
        $b = $bausteinMap[mb_strtolower(trim($gruppe))] ?? null;
        if (!$b) return ['style' => '', 'nameStyle' => ''];
        return [
            'style'     => 'background: ' . ($b['bg'] ?? '#f8fafc') . ';',
            'nameStyle' => 'color: ' . ($b['text'] ?? '#64748b') . '; font-weight: 700; font-style: italic;',
        ];
    };
    $totalArticles = $positions->filter(fn($p) => !$isBaustein((string) $p->gruppe))->count();
    $totalGesamt = (float) $positions->sum('gesamt');
@endphp
<div id="order-positions-editor-{{ $activeItem->id }}"
     x-data
     x-on:scroll-to-positions.window="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
     class="bg-white border border-[var(--ui-border)] rounded-xl overflow-hidden shadow-sm">

    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex-wrap gap-2">
        <div class="flex items-center gap-2">
            <div class="w-[3px] h-3.5 bg-orange-600 rounded-sm"></div>
            <span class="text-[0.72rem] font-bold text-[var(--ui-secondary)]">Bestellpositionen</span>
            <span class="text-[0.6rem] text-[var(--ui-muted)]">· {{ $totalArticles }} Artikel · Einkauf {{ $fmt($totalGesamt) }} €</span>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Status</label>
            <select wire:change="updateItemStatus($event.target.value)"
                    class="border border-slate-200 rounded-md px-2 py-1 text-[0.65rem] bg-white cursor-pointer font-semibold">
                @foreach(['Offen','Bestellt','Geliefert','Storno'] as $s)
                    <option value="{{ $s }}" @selected($activeItem->status === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <label class="text-[0.58rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Lieferant</label>
            <input type="text" value="{{ $activeItem->lieferant }}"
                   wire:change="updateItemLieferant($event.target.value)"
                   placeholder="—"
                   class="w-[140px] border border-slate-200 rounded-md px-2 py-1 text-[0.65rem] bg-white">
        </div>
    </div>
    @if(session('positionError'))
        <div class="px-4 py-2 bg-red-50 border-b border-red-200 text-[0.68rem] text-red-700 flex items-center gap-1.5">
            @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5')
            {{ session('positionError') }}
        </div>
    @endif

    <x-events::bulk-actionbar
        :count="count($selectedPositionUuids ?? [])"
        deleteAction="deleteSelectedPositions"
        clearAction="clearPositionSelection"
        label="Position"
        labelPlural="Positionen" />

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[0.65rem]" x-data="{ lastIdx: null }">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-0 py-1.5 w-[8px]"></th>
                    <th class="text-left py-1.5 px-2.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gruppe</th>
                    <th class="text-left py-1.5 px-2 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Name</th>
                    <th class="text-right py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Anz.</th>
                    <th class="text-right py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Anz.2</th>
                    <th class="text-left py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Uhrzeit</th>
                    <th class="text-left py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Bis</th>
                    <th class="text-left py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gebinde</th>
                    <th class="text-right py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Basis-EK</th>
                    <th class="text-right py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">EK €</th>
                    <th class="text-center py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">MwSt.</th>
                    <th class="text-right py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gesamt €</th>
                    <th class="text-left py-1.5 px-1.5 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Bemerkung</th>
                    <th class="w-8"></th>
                </tr>
            </thead>
            <tbody x-data="sortableList('reorderOrderPositions')">
                @forelse($positions as $p)
                    @php
                        $rs = $rowInlineStyle((string) $p->gruppe);
                        $text = $isBaustein((string) $p->gruppe);
                        $isSelected = in_array($p->uuid, $selectedPositionUuids ?? [], true);
                    @endphp
                    <tr data-sortable-uuid="{{ $p->uuid }}" class="border-b border-slate-100 hover:bg-slate-50/40 group {{ $isSelected ? 'bg-blue-50/50' : '' }}" style="{{ $rs['style'] }}">
                        <x-events::select-handle
                            :uuid="$p->uuid"
                            :index="$loop->index"
                            :isSelected="$isSelected"
                            toggle="togglePositionSelection"
                            range="togglePositionRange"
                            toggleAll="toggleAllPositions" />
                        <td class="py-1.5 px-2.5 text-slate-600">{{ $p->gruppe }}</td>
                        <td class="py-1.5 px-2" style="{{ $rs['nameStyle'] ?: '' }}">{{ $p->name }}</td>
                        <td class="py-1.5 px-1.5 text-right font-mono">{{ $text ? '' : $p->anz }}</td>
                        <td class="py-1.5 px-1.5 text-right font-mono text-slate-500">{{ $text ? '' : $p->anz2 }}</td>
                        <td class="py-1.5 px-1.5 font-mono text-slate-500">{{ $text ? '' : $p->uhrzeit }}</td>
                        <td class="py-1.5 px-1.5 font-mono text-slate-500">{{ $text ? '' : $p->bis }}</td>
                        <td class="py-1.5 px-1.5 text-slate-500">{{ $text ? '' : $p->gebinde }}</td>
                        <td class="py-1.5 px-1.5 text-right font-mono text-slate-400">{{ $text ? '' : ($p->basis_ek ? $fmt($p->basis_ek) : '') }}</td>
                        <td class="py-1.5 px-1.5 text-right font-mono font-semibold">{{ $text ? '' : $fmt($p->ek) }}</td>
                        <td class="py-1.5 px-1.5 text-center text-slate-500">{{ $text ? '' : $p->mwst }}</td>
                        <td class="py-1.5 px-1.5 text-right font-mono font-bold text-red-600">{{ $text ? '' : $fmt($p->gesamt) }}</td>
                        <td class="py-1.5 px-1.5 text-slate-500 italic truncate max-w-[200px]" title="{{ $p->bemerkung }}">{{ $p->bemerkung }}</td>
                        <td class="py-1.5 px-1.5">
                            <button wire:click="deletePosition({{ $p->id }})" wire:confirm="Position löschen?"
                                    class="text-red-500 hover:text-red-700">
                                @svg('heroicon-o-trash', 'w-3 h-3')
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="py-6 text-center text-[0.7rem] text-[var(--ui-muted)]">
                            Noch keine Positionen. Fülle die Eingabefelder unten aus.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($positions->isNotEmpty())
                <tfoot>
                    <tr class="bg-slate-50 border-t-2 border-slate-200">
                        <td colspan="11" class="py-2 px-2.5 text-right text-[0.58rem] font-bold uppercase tracking-wider text-slate-500">
                            Positionen <span class="text-[var(--ui-secondary)] font-mono ml-1">{{ $totalArticles }}</span> · Einkauf
                        </td>
                        <td class="py-2 px-1.5 text-right font-mono font-bold text-red-600 text-[0.72rem]">{{ $fmt($totalGesamt) }} €</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
            <tbody class="bg-slate-50 border-t-2 border-slate-300">
                <tr>
                    <td colspan="14" class="px-2.5 pt-2 pb-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="w-[3px] h-3 bg-orange-600 rounded-sm"></div>
                            <span class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Position</span>

                            @if(!empty($bausteine))
                                <div x-data="{
                                        open: false,
                                        pos: { top: 0, left: 0 },
                                        recalc() {
                                            const r = this.$refs.trigger.getBoundingClientRect();
                                            this.pos = { top: r.bottom + window.scrollY + 4, left: r.left + window.scrollX };
                                        },
                                        toggle() {
                                            if (!this.open) this.recalc();
                                            this.open = !this.open;
                                        }
                                     }"
                                     @keydown.escape.window="open = false"
                                     @resize.window="open = false"
                                     @scroll.window.passive="open = false"
                                     class="relative">
                                    <button type="button" x-ref="trigger" @click="toggle()"
                                            class="flex items-center gap-1 px-2 py-0.5 rounded border border-purple-200 bg-purple-50 hover:bg-purple-100 text-purple-700 text-[0.6rem] font-bold cursor-pointer">
                                        @svg('heroicon-o-rectangle-stack', 'w-3 h-3')
                                        Baustein
                                        <svg class="w-2 h-2 transition-transform" :class="open ? 'rotate-180' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak
                                             @click.outside="open = false"
                                             :style="'position:absolute; top:' + pos.top + 'px; left:' + pos.left + 'px; z-index:9999; min-width:180px;'"
                                             class="bg-white border border-slate-200 rounded-md p-1 shadow-xl">
                                            @foreach($bausteine as $b)
                                                <button type="button"
                                                        wire:click="$set('newPosition.gruppe', @js($b['name'] ?? ''))"
                                                        @click="open = false"
                                                        class="flex items-center gap-2 w-full px-2.5 py-1.5 rounded hover:bg-slate-50 text-left text-[0.65rem] font-medium text-slate-700">
                                                    <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0"
                                                          style="background: {{ $b['bg'] ?? '#f8fafc' }}; border: 1px solid {{ $b['text'] ?? '#64748b' }};"></span>
                                                    <span>{{ $b['name'] ?? '' }}</span>
                                                </button>
                                            @endforeach
                                            <div class="border-t border-slate-100 mt-1 pt-1">
                                                <a href="{{ route('events.settings') }}"
                                                   class="flex items-center gap-1.5 px-2.5 py-1.5 text-[0.6rem] text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded no-underline">
                                                    @svg('heroicon-o-cog-6-tooth', 'w-3 h-3')
                                                    Bausteine verwalten
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            @endif

                            <span class="text-[0.55rem] text-slate-400 ml-auto">Baustein wählen oder Gruppe/Typ frei eingeben.</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="w-[8px]"></td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model="newPosition.gruppe" type="text" placeholder="Gruppe / Typ"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top relative" x-data="{ showArticles: false }">
                        <input wire:model.live.debounce.300ms="newPosition.name" type="text" placeholder="Bezeichnung / Artikel suchen"
                               @keydown.enter="$wire.addPosition()"
                               @focus="showArticles = true"
                               @click.outside="showArticles = false"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] bg-white">
                        @if($articleMatches->isNotEmpty())
                            <div x-show="showArticles" x-cloak
                                 class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 max-h-64 overflow-y-auto min-w-[320px]">
                                @foreach($articleMatches as $art)
                                    <button type="button"
                                            wire:click="pickArticle({{ $art->id }})"
                                            @click="showArticles = false"
                                            class="w-full flex items-center gap-2 px-2 py-1.5 text-left hover:bg-slate-50 transition border-0 bg-transparent cursor-pointer">
                                        <span class="text-[0.58rem] font-mono font-bold text-slate-500 min-w-[70px] flex-shrink-0">{{ $art->article_number }}</span>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-[0.65rem] text-slate-700 truncate">{{ $art->name }}</div>
                                            @if($art->gebinde)
                                                <div class="text-[0.55rem] text-slate-400">{{ $art->gebinde }}</div>
                                            @endif
                                        </div>
                                        @if($art->ek > 0)
                                            <span class="text-[0.6rem] font-mono text-slate-500 flex-shrink-0">{{ number_format($art->ek, 2, ',', '.') }} €</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model.blur="newPosition.anz" type="text" placeholder="0"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] font-mono text-right bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model.blur="newPosition.anz2" type="text" placeholder="0"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] font-mono text-right bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model.blur="newPosition.uhrzeit" type="text" placeholder="HH:MM" maxlength="5"
                               inputmode="numeric"
                               x-data
                               x-on:input="let v = $el.value.replace(/[^0-9]/g, '').substring(0, 4); if (v.length >= 3) v = v.substring(0, 2) + ':' + v.substring(2); $el.value = v;"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] font-mono bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model.blur="newPosition.bis" type="text" placeholder="HH:MM" maxlength="5"
                               inputmode="numeric"
                               x-data
                               x-on:input="let v = $el.value.replace(/[^0-9]/g, '').substring(0, 4); if (v.length >= 3) v = v.substring(0, 2) + ':' + v.substring(2); $el.value = v;"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] font-mono bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model="newPosition.gebinde" type="text" placeholder="1 Stk."
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model="newPosition.basis_ek" type="number" step="0.01" placeholder="0,00"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] font-mono text-right bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model.blur="newPosition.ek" type="number" step="0.01" placeholder="0,00"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] font-mono text-right bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <select wire:model="newPosition.mwst"
                                class="w-full border border-slate-200 rounded px-1 py-1 text-[0.65rem] bg-white">
                            <option value="0%">0%</option>
                            <option value="7%">7%</option>
                            <option value="19%">19%</option>
                        </select>
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model.blur="newPosition.gesamt" type="number" step="0.01" placeholder="auto"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] font-mono text-right bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <input wire:model="newPosition.bemerkung" type="text" placeholder="Bemerkung"
                               class="w-full border border-slate-200 rounded px-1.5 py-1 text-[0.65rem] bg-white">
                    </td>
                    <td class="px-1.5 py-1.5 align-top">
                        <button wire:click="addPosition"
                                class="w-full flex items-center justify-center gap-1 rounded bg-orange-600 hover:bg-orange-700 text-white border-0 cursor-pointer text-[0.65rem] font-semibold py-1 whitespace-nowrap"
                                title="Position hinzufügen">
                            @svg('heroicon-o-plus', 'w-3 h-3')
                        </button>
                    </td>
                </tr>
                <tr>
                    <td colspan="14" class="px-2.5 pt-0.5 pb-2">
                        <p class="text-[0.52rem] text-slate-400 m-0">
                            Enter im Bezeichnungs-Feld oder „+" zum Hinzufügen · Gesamt leer → Anz × EK wird berechnet.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
