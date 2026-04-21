@php
    $fmt = $fmt ?? fn($v) => number_format((float)$v, 2, ',', '.');
    $rowStyle = function (string $gruppe) {
        $g = strtolower(trim($gruppe));
        return match ($g) {
            'headline'                         => ['bg' => 'bg-green-50',  'name' => 'font-bold italic text-green-700'],
            'speisentexte', 'speisentext'      => ['bg' => 'bg-amber-50',  'name' => 'font-semibold italic text-amber-700'],
            'trenntext'                        => ['bg' => 'bg-slate-50',  'name' => 'italic text-slate-500'],
            default                            => ['bg' => '',              'name' => 'text-[var(--ui-secondary)]'],
        };
    };
    $isText = fn(string $g) => in_array(strtolower(trim($g)), ['headline', 'speisentexte', 'speisentext', 'trenntext'], true);
    $totalArticles = $positions->filter(fn($p) => !$isText((string) $p->gruppe))->count();
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

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[0.65rem]">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
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
            <tbody>
                @forelse($positions as $p)
                    @php
                        $rs = $rowStyle((string) $p->gruppe);
                        $text = $isText((string) $p->gruppe);
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50/40 {{ $rs['bg'] }}">
                        <td class="py-1.5 px-2.5 text-slate-600">{{ $p->gruppe }}</td>
                        <td class="py-1.5 px-2 {{ $rs['name'] }}">{{ $p->name }}</td>
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
                        <td colspan="13" class="py-6 text-center text-[0.7rem] text-[var(--ui-muted)]">
                            Noch keine Positionen. Fülle die Eingabefelder unten aus.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($positions->isNotEmpty())
                <tfoot>
                    <tr class="bg-slate-50 border-t-2 border-slate-200">
                        <td colspan="10" class="py-2 px-2.5 text-right text-[0.58rem] font-bold uppercase tracking-wider text-slate-500">
                            Positionen <span class="text-[var(--ui-secondary)] font-mono ml-1">{{ $totalArticles }}</span> · Einkauf
                        </td>
                        <td class="py-2 px-1.5 text-right font-mono font-bold text-red-600 text-[0.72rem]">{{ $fmt($totalGesamt) }} €</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 space-y-2">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-[3px] h-3 bg-orange-600 rounded-sm"></div>
            <span class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Position</span>
        </div>
        <div class="grid grid-cols-12 gap-1">
            <input wire:model="newPosition.gruppe" type="text" placeholder="Gruppe / Typ"
                   class="col-span-2 border border-slate-200 rounded px-2 py-1.5 text-[0.65rem]">
            <input wire:model="newPosition.name" type="text" placeholder="Bezeichnung"
                   @keydown.enter="$wire.addPosition()"
                   class="col-span-3 border border-slate-200 rounded px-2 py-1.5 text-[0.65rem]">
            <input wire:model="newPosition.anz" type="text" placeholder="Anz"
                   class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem] font-mono text-right">
            <input wire:model="newPosition.anz2" type="text" placeholder="Anz.2"
                   class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem] font-mono text-right">
            <input wire:model="newPosition.uhrzeit" type="text" placeholder="Von"
                   class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem] font-mono">
            <input wire:model="newPosition.bis" type="text" placeholder="Bis"
                   class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem] font-mono">
            <input wire:model="newPosition.gebinde" type="text" placeholder="Gebinde"
                   class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem]">
            <input wire:model="newPosition.basis_ek" type="number" step="0.01" placeholder="Basis-EK"
                   class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem] font-mono text-right">
            <input wire:model="newPosition.ek" type="number" step="0.01" placeholder="EK"
                   class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem] font-mono text-right">
        </div>
        <div class="grid grid-cols-12 gap-1">
            <select wire:model="newPosition.mwst"
                    class="col-span-1 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem]">
                <option value="0%">0%</option>
                <option value="7%">7%</option>
                <option value="19%">19%</option>
            </select>
            <input wire:model="newPosition.gesamt" type="number" step="0.01" placeholder="Gesamt (auto)"
                   class="col-span-2 border border-slate-200 rounded px-1.5 py-1.5 text-[0.65rem] font-mono text-right">
            <input wire:model="newPosition.bemerkung" type="text" placeholder="Bemerkung (optional)"
                   class="col-span-8 border border-slate-200 rounded px-2 py-1.5 text-[0.65rem]">
            <button wire:click="addPosition"
                    class="col-span-1 flex items-center justify-center gap-1 rounded bg-orange-600 hover:bg-orange-700 text-white border-0 cursor-pointer text-[0.65rem] font-semibold">
                @svg('heroicon-o-plus', 'w-3 h-3')
                Add
            </button>
        </div>
        <p class="text-[0.55rem] text-slate-400">
            Enter im Bezeichnungs-Feld oder Button „Add" zum Hinzufügen · Gesamt leer → Anz × EK wird berechnet.
        </p>
    </div>
</div>
