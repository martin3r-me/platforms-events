@php
    $fmt = $fmt ?? fn($v) => number_format((float)$v, 2, ',', '.');
@endphp
<div id="order-positions-editor-{{ $activeItem->id }}"
     x-data
     x-on:scroll-to-positions.window="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
     class="mx-3.5 mb-3.5 bg-white border-2 border-orange-400 rounded-xl overflow-hidden shadow-sm">
    <div class="flex items-center justify-between px-4 py-2.5 bg-orange-50 border-b border-orange-200">
        <div class="flex items-center gap-2.5">
            <div class="w-[3px] h-4 bg-orange-600 rounded-sm"></div>
            <h3 class="text-[0.75rem] font-bold text-[var(--ui-secondary)]">Bestellpositionen · {{ $activeItem->typ }}</h3>
            <span class="text-[0.6rem] text-[var(--ui-muted)]">{{ $positions->count() }} Positionen · Einkauf {{ $fmt($activeItem->einkauf) }} €</span>
        </div>
        <button wire:click="closePositions"
                class="flex items-center gap-1 px-2.5 py-1 rounded bg-white border border-slate-200 hover:bg-slate-50 text-slate-500 text-[0.62rem] font-semibold cursor-pointer">
            @svg('heroicon-o-x-mark', 'w-3 h-3')
            Schließen
        </button>
    </div>

    @if($positions->isEmpty())
        <div class="px-4 py-5 text-center text-[0.7rem] text-[var(--ui-muted)]">
            Noch keine Positionen. Fülle die Eingabefelder unten aus, um die erste Position hinzuzufügen.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[0.65rem]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left py-1.5 px-2.5 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gruppe</th>
                        <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Name</th>
                        <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Anz</th>
                        <th class="text-left py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gebinde</th>
                        <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">EK</th>
                        <th class="text-center py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">MwSt</th>
                        <th class="text-right py-1.5 px-2 text-[0.56rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Gesamt</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($positions as $p)
                        <tr class="border-b border-slate-100 hover:bg-slate-50/60">
                            <td class="py-1.5 px-2.5 text-slate-600">{{ $p->gruppe }}</td>
                            <td class="py-1.5 px-2 text-[var(--ui-secondary)]">{{ $p->name }}</td>
                            <td class="py-1.5 px-2 text-right font-mono">{{ $p->anz }}</td>
                            <td class="py-1.5 px-2 text-slate-500">{{ $p->gebinde }}</td>
                            <td class="py-1.5 px-2 text-right font-mono">{{ $fmt($p->ek) }}</td>
                            <td class="py-1.5 px-2 text-center text-slate-500">{{ $p->mwst }}</td>
                            <td class="py-1.5 px-2 text-right font-mono font-semibold">{{ $fmt($p->gesamt) }}</td>
                            <td class="py-1.5 px-2">
                                <button wire:click="deletePosition({{ $p->id }})" wire:confirm="Position löschen?"
                                        class="text-red-500 hover:text-red-700">
                                    @svg('heroicon-o-trash', 'w-3 h-3')
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="px-4 py-3 bg-slate-50 border-t border-slate-100 space-y-2">
        <p class="text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Position</p>
        <div class="grid grid-cols-12 gap-1.5">
            <input wire:model="newPosition.gruppe" type="text" placeholder="Gruppe"
                   class="col-span-2 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem]">
            <input wire:model="newPosition.name" type="text" placeholder="Name / Bezeichnung"
                   class="col-span-3 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem]">
            <input wire:model="newPosition.anz" type="text" placeholder="Anz"
                   class="col-span-1 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem] font-mono text-right">
            <input wire:model="newPosition.gebinde" type="text" placeholder="Gebinde"
                   class="col-span-2 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem]">
            <input wire:model="newPosition.ek" type="number" step="0.01" placeholder="EK"
                   class="col-span-1 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem] font-mono text-right">
            <select wire:model="newPosition.mwst"
                    class="col-span-1 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem]">
                <option value="7%">7%</option>
                <option value="19%">19%</option>
            </select>
            <input wire:model="newPosition.gesamt" type="number" step="0.01" placeholder="Gesamt (auto)"
                   class="col-span-2 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem] font-mono text-right">
        </div>
        <div class="flex gap-1.5">
            <input wire:model="newPosition.bemerkung" type="text" placeholder="Bemerkung (optional)"
                   class="flex-1 border border-slate-200 rounded-md px-2 py-1.5 text-[0.65rem]">
            <button wire:click="addPosition"
                    class="flex items-center gap-1 px-3 py-1.5 rounded bg-orange-600 hover:bg-orange-700 text-white border-0 cursor-pointer text-[0.65rem] font-semibold whitespace-nowrap">
                @svg('heroicon-o-plus', 'w-3 h-3')
                Hinzufügen
            </button>
        </div>
        <p class="text-[0.55rem] text-[var(--ui-muted)]">
            Gesamt-Feld leer lassen ⇒ Anz × EK wird automatisch berechnet.
        </p>
    </div>
</div>
