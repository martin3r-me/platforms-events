<div class="space-y-4">
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white border border-[var(--ui-border)] rounded-lg p-4">
            <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase tracking-wider font-semibold">Umsatz (Angebot)</p>
            <p class="text-xl font-bold text-green-600 font-mono">{{ number_format($totalRevenue, 2, ',', '.') }} €</p>
        </div>
        <div class="bg-white border border-[var(--ui-border)] rounded-lg p-4">
            <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase tracking-wider font-semibold">Einkauf (Bestellung)</p>
            <p class="text-xl font-bold text-orange-600 font-mono">{{ number_format($totalCost, 2, ',', '.') }} €</p>
        </div>
        <div class="bg-white border border-[var(--ui-border)] rounded-lg p-4">
            <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase tracking-wider font-semibold">Deckungsbeitrag</p>
            <p class="text-xl font-bold text-[var(--ui-primary)] font-mono">{{ number_format($totalMargin, 2, ',', '.') }} €</p>
        </div>
        <div class="bg-white border border-[var(--ui-border)] rounded-lg p-4">
            <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase tracking-wider font-semibold">Marge</p>
            <p class="text-xl font-bold {{ $marginPct >= 0 ? 'text-green-600' : 'text-red-600' }} font-mono">{{ number_format($marginPct, 1, ',', '.') }}%</p>
        </div>
    </div>

    <x-ui-panel title="Tagesauswertung">
        @if($rows->isEmpty())
            <div class="p-12 text-center text-xs text-[var(--ui-muted)]">
                Keine Event-Tage vorhanden.
            </div>
        @else
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Tag</th>
                        <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Datum</th>
                        <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Umsatz</th>
                        <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Einkauf</th>
                        <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">DB</th>
                        <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Marge</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-b border-[var(--ui-border)]/60">
                            <td class="px-3 py-2 text-xs font-semibold text-[var(--ui-secondary)]">{{ $row['day']->label }}</td>
                            <td class="px-3 py-2 text-xs font-mono text-[var(--ui-muted)]">{{ $row['day']->datum?->format('d.m.Y') }}</td>
                            <td class="px-3 py-2 text-xs font-mono text-right text-green-600">{{ number_format($row['revenue'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-xs font-mono text-right text-orange-600">{{ number_format($row['cost'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-xs font-mono text-right font-bold">{{ number_format($row['margin'], 2, ',', '.') }}</td>
                            <td class="px-3 py-2 text-xs font-mono text-right {{ $row['marginPct'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($row['marginPct'], 1, ',', '.') }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-[var(--ui-border)] bg-[var(--ui-muted-5)] font-bold">
                        <td colspan="2" class="px-3 py-2 text-xs">Gesamt</td>
                        <td class="px-3 py-2 text-xs font-mono text-right text-green-600">{{ number_format($totalRevenue, 2, ',', '.') }} €</td>
                        <td class="px-3 py-2 text-xs font-mono text-right text-orange-600">{{ number_format($totalCost, 2, ',', '.') }} €</td>
                        <td class="px-3 py-2 text-xs font-mono text-right text-[var(--ui-primary)]">{{ number_format($totalMargin, 2, ',', '.') }} €</td>
                        <td class="px-3 py-2 text-xs font-mono text-right {{ $marginPct >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($marginPct, 1, ',', '.') }}%</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </x-ui-panel>
</div>
