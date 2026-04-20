<div class="space-y-4 max-w-[960px]">
    @php
        $fmt = fn($v) => number_format((float)$v, 2, ',', '.');
        $typeColors = [
            'rechnung'        => '#2563eb',
            'teilrechnung'    => '#6366f1',
            'schlussrechnung' => '#059669',
            'gutschrift'      => '#d97706',
            'storno'          => '#dc2626',
        ];
        $typeLabel = [
            'rechnung'        => 'Rechnung',
            'teilrechnung'    => 'Teilrechnung',
            'schlussrechnung' => 'Schlussrechnung',
            'gutschrift'      => 'Gutschrift',
            'storno'          => 'Storno',
        ];
        $statusMeta = [
            'draft'     => ['bg' => '#f1f5f9', 'color' => '#64748b', 'label' => 'Entwurf'],
            'sent'      => ['bg' => '#dbeafe', 'color' => '#2563eb', 'label' => 'Versendet'],
            'paid'      => ['bg' => '#dcfce7', 'color' => '#16a34a', 'label' => 'Bezahlt'],
            'overdue'   => ['bg' => '#fef3c7', 'color' => '#b45309', 'label' => 'Überfällig'],
            'cancelled' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'label' => 'Storniert'],
        ];

        $openAmount = 0;
        $paidAmount = 0;
        $overdueAmount = 0;
        foreach ($invoices as $inv) {
            $isNegative = in_array($inv->type, ['gutschrift', 'storno']);
            $amount = $isNegative ? -(float)$inv->brutto : (float)$inv->brutto;
            if ($inv->status === 'sent')    $openAmount    += $amount;
            if ($inv->status === 'paid')    $paidAmount    += $amount;
            if ($inv->status === 'overdue') $overdueAmount += $amount;
        }
    @endphp

    {{-- Header row --}}
    <div class="flex items-start justify-between mb-4 flex-wrap gap-3">
        <div>
            <div class="flex items-center gap-2.5 mb-2">
                <div class="w-[3px] h-4 bg-blue-600 rounded-sm"></div>
                <h2 class="text-[0.9rem] font-bold text-[var(--ui-secondary)]">Rechnungen</h2>
            </div>
            <div class="flex gap-4 flex-wrap">
                <div class="text-[0.62rem] text-slate-500">
                    Offen: <span class="font-bold text-blue-600 font-mono">{{ $fmt($openAmount) }} €</span>
                </div>
                <div class="text-[0.62rem] text-slate-500">
                    Bezahlt: <span class="font-bold text-green-600 font-mono">{{ $fmt($paidAmount) }} €</span>
                </div>
                <div class="text-[0.62rem] text-slate-500">
                    Überfällig: <span class="font-bold text-red-600 font-mono">{{ $fmt($overdueAmount) }} €</span>
                </div>
            </div>
        </div>
        <div class="relative" x-data="{ dropOpen: false }">
            <button type="button" @click="dropOpen = !dropOpen"
                    class="flex items-center gap-1.5 px-3.5 py-1.5 border-0 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-[0.68rem] font-bold cursor-pointer">
                @svg('heroicon-o-plus', 'w-3 h-3')
                Neue Rechnung
                @svg('heroicon-o-chevron-down', 'w-2.5 h-2.5 ml-0.5')
            </button>
            <div x-show="dropOpen" @click.outside="dropOpen = false" x-cloak
                 class="absolute right-0 top-[calc(100%+4px)] z-[100] bg-white border border-slate-200 rounded-lg p-1 shadow-lg min-w-[220px]">
                @foreach([
                    ['type' => 'rechnung',        'label' => 'Rechnung',         'color' => '#2563eb'],
                    ['type' => 'teilrechnung',    'label' => 'Teilrechnung',     'color' => '#6366f1'],
                    ['type' => 'schlussrechnung', 'label' => 'Schlussrechnung',  'color' => '#059669'],
                ] as $opt)
                    <button type="button" wire:click="$set('newType', '{{ $opt['type'] }}'); $wire.createInvoice()" @click="dropOpen = false"
                            class="flex items-center gap-2 w-full px-2.5 py-2 border-0 rounded bg-white hover:bg-slate-50 cursor-pointer text-left text-[0.65rem] text-slate-700 transition">
                        <div class="w-2 h-2 rounded-full flex-shrink-0" style="background: {{ $opt['color'] }};"></div>
                        <span class="font-semibold">{{ $opt['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @if($invoices->isEmpty())
        <div class="bg-white border border-[var(--ui-border)] rounded-xl p-10 text-center">
            @svg('heroicon-o-document-text', 'w-10 h-10 text-slate-300 mx-auto mb-3')
            <p class="text-[0.72rem] text-[var(--ui-muted)] m-0">Noch keine Rechnungen erstellt.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($invoices as $inv)
                @php
                    $s = $statusMeta[$inv->status] ?? $statusMeta['draft'];
                    $isNegative = in_array($inv->type, ['gutschrift', 'storno']);
                    $isSelected = $active && $active->id === $inv->id;
                @endphp
                <div class="bg-white border rounded-xl px-4 py-3.5 {{ $isSelected ? 'border-blue-300 ring-1 ring-blue-100' : 'border-[var(--ui-border)]' }}">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="text-[0.56rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap text-white"
                              style="background: {{ $typeColors[$inv->type] ?? '#2563eb' }};">{{ $typeLabel[$inv->type] ?? $inv->type }}</span>

                        @if(!empty($inv->version))
                            <span class="text-[0.56rem] font-bold px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-200 flex-shrink-0">v{{ $inv->version }}</span>
                        @endif

                        <span class="text-[0.58rem] font-bold px-2 py-0.5 rounded-full whitespace-nowrap"
                              style="background: {{ $s['bg'] }}; color: {{ $s['color'] }};">{{ $s['label'] }}</span>

                        @if(($inv->reminder_level ?? 0) > 0)
                            <span class="text-[0.56rem] font-bold px-1.5 py-0.5 rounded-full bg-orange-50 text-orange-600 border border-orange-200 flex-shrink-0">Mahnstufe {{ $inv->reminder_level }}</span>
                        @endif

                        <div class="flex-1 min-w-0">
                            <p class="text-[0.72rem] font-semibold text-[var(--ui-secondary)] m-0">
                                <button wire:click="selectInvoice({{ $inv->id }})" class="font-mono">{{ $inv->invoice_number }}</button>
                                @if($inv->notes)
                                    <span class="text-[0.6rem] font-medium text-amber-600 ml-1.5">{{ \Illuminate\Support\Str::limit($inv->notes, 40) }}</span>
                                @endif
                            </p>
                            <p class="text-[0.6rem] text-[var(--ui-muted)] mt-0.5">
                                {{ $inv->customer_company ?? $event->customer ?: 'Kein Kunde' }}
                                @if($inv->customer_contact) · {{ $inv->customer_contact }} @endif
                            </p>
                            <p class="text-[0.58rem] text-slate-400 mt-0.5">
                                Datum: {{ $inv->invoice_date?->format('d.m.Y') ?? '—' }}
                                · Fällig: {{ $inv->due_date?->format('d.m.Y') ?? '—' }}
                                @if($inv->created_by) · von {{ $inv->created_by }} @endif
                            </p>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <p class="text-[1.1rem] font-bold font-mono m-0 leading-tight {{ $isNegative ? 'text-red-600' : 'text-green-600' }}">
                                {{ $isNegative ? '−' : '' }}{{ $fmt($inv->brutto) }} €
                            </p>
                            <p class="text-[0.54rem] text-[var(--ui-muted)] mt-0.5">Netto: {{ $fmt($inv->netto) }} €</p>
                        </div>
                    </div>

                    <div class="flex gap-1.5 flex-wrap mt-2.5 pt-2.5 border-t border-slate-100">
                        @if($inv->status === 'draft')
                            <button wire:click="selectInvoice({{ $inv->id }}); $wire.openItems()"
                                    class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold cursor-pointer">
                                Bearbeiten
                            </button>
                        @endif
                        <a href="{{ route('events.invoice.pdf', ['event' => $event->slug, 'invoiceId' => $inv->id]) }}" target="_blank"
                           class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-slate-600 text-[0.62rem] font-semibold no-underline">
                            PDF
                        </a>
                        <button type="button"
                                x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ route('events.public.invoice', ['token' => $inv->token]) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                :class="copied ? 'bg-green-50 border-green-200 text-green-600' : 'bg-white border-slate-200 text-slate-600'"
                                class="px-2.5 py-1 border rounded-md hover:bg-slate-50 text-[0.62rem] font-semibold cursor-pointer">
                            <span x-text="copied ? 'Kopiert' : 'Link'"></span>
                        </button>
                        @if($inv->status === 'draft')
                            <button wire:click="selectInvoice({{ $inv->id }}); $wire.setStatus('sent')"
                                    class="px-2.5 py-1 border-0 rounded-md bg-blue-600 hover:bg-blue-700 text-white text-[0.62rem] font-bold cursor-pointer">
                                Versenden
                            </button>
                        @endif
                        @if(in_array($inv->status, ['sent', 'overdue']))
                            <button wire:click="selectInvoice({{ $inv->id }}); $wire.setStatus('paid')"
                                    wire:confirm="Als bezahlt markieren?"
                                    class="px-2.5 py-1 border-0 rounded-md bg-green-600 hover:bg-green-700 text-white text-[0.62rem] font-bold cursor-pointer">
                                Bezahlt
                            </button>
                            <button wire:click="selectInvoice({{ $inv->id }}); $wire.markReminded()"
                                    wire:confirm="Mahnstufe erhöhen?"
                                    class="px-2.5 py-1 border border-amber-400 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-700 text-[0.62rem] font-semibold cursor-pointer">
                                Mahnung
                            </button>
                        @endif
                        @if(in_array($inv->status, ['sent', 'paid']) && !in_array($inv->type, ['gutschrift', 'storno']))
                            <button wire:click="selectInvoice({{ $inv->id }}); $wire.createGutschrift()" wire:confirm="Gutschrift erstellen?"
                                    class="px-2.5 py-1 border border-slate-200 rounded-md bg-white hover:bg-slate-50 text-amber-700 text-[0.62rem] font-semibold cursor-pointer">
                                Gutschrift
                            </button>
                        @endif
                        @if($inv->status === 'draft')
                            <button wire:click="deleteInvoice({{ $inv->id }})" wire:confirm="Rechnung wirklich löschen?"
                                    class="ml-auto px-2.5 py-1 border border-red-200 rounded-md bg-red-50 hover:bg-red-100 text-red-500 text-[0.62rem] font-semibold cursor-pointer">
                                Löschen
                            </button>
                        @endif
                    </div>

                    @if($isSelected)
                        <div class="mt-3 pt-3 border-t border-slate-100 grid grid-cols-2 md:grid-cols-4 gap-3 text-[0.65rem]">
                            <div>
                                <div class="text-[0.56rem] font-bold uppercase text-[var(--ui-muted)] tracking-wider mb-0.5">Netto</div>
                                <div class="font-mono font-bold">{{ $fmt($active->netto) }} €</div>
                            </div>
                            <div>
                                <div class="text-[0.56rem] font-bold uppercase text-[var(--ui-muted)] tracking-wider mb-0.5">MwSt 7%</div>
                                <div class="font-mono">{{ $fmt($active->mwst_7) }} €</div>
                            </div>
                            <div>
                                <div class="text-[0.56rem] font-bold uppercase text-[var(--ui-muted)] tracking-wider mb-0.5">MwSt 19%</div>
                                <div class="font-mono">{{ $fmt($active->mwst_19) }} €</div>
                            </div>
                            <div>
                                <div class="text-[0.56rem] font-bold uppercase text-[var(--ui-muted)] tracking-wider mb-0.5">Brutto</div>
                                <div class="font-mono font-bold text-blue-600">{{ $fmt($active->brutto) }} €</div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal: Items --}}
    <x-ui-modal wire:model="showItemsModal" size="xl" :hideFooter="true">
        <x-slot name="header">Rechnungs-Positionen</x-slot>
        <div class="space-y-4">
            @if($active && $active->items->isEmpty())
                <p class="text-xs text-[var(--ui-muted)] text-center py-4">Noch keine Positionen.</p>
            @elseif($active)
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-[var(--ui-border)]">
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gruppe</th>
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Name</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Anz</th>
                            <th class="px-2 py-1.5 text-left text-[0.62rem] font-bold text-[var(--ui-muted)]">Gebinde</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Einzelpreis</th>
                            <th class="px-2 py-1.5 text-center text-[0.62rem] font-bold text-[var(--ui-muted)]">MwSt</th>
                            <th class="px-2 py-1.5 text-right text-[0.62rem] font-bold text-[var(--ui-muted)]">Gesamt</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($active->items as $it)
                            <tr class="border-b border-[var(--ui-border)]/60">
                                <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $it->gruppe }}</td>
                                <td class="px-2 py-1 text-xs text-[var(--ui-secondary)]">{{ $it->name }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ (float) $it->quantity }}</td>
                                <td class="px-2 py-1 text-xs text-[var(--ui-muted)]">{{ $it->gebinde }}</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ $fmt($it->unit_price) }}</td>
                                <td class="px-2 py-1 text-xs text-center">{{ $it->mwst_rate }}%</td>
                                <td class="px-2 py-1 text-xs font-mono text-right">{{ $fmt($it->total) }}</td>
                                <td class="px-2 py-1">
                                    <button wire:click="deleteItem({{ $it->id }})" wire:confirm="Löschen?" class="text-red-500 hover:text-red-700">
                                        @svg('heroicon-o-trash', 'w-3 h-3')
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="bg-slate-50 rounded-md p-3 space-y-2">
                <p class="text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Neue Rechnungsposition</p>
                <div class="grid grid-cols-8 gap-2">
                    <input wire:model="newItem.gruppe" type="text" placeholder="Gruppe" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.name" type="text" placeholder="Name" class="col-span-2 border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.quantity" type="number" step="0.01" placeholder="Anz" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs font-mono">
                    <input wire:model="newItem.gebinde" type="text" placeholder="Gebinde" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                    <input wire:model="newItem.unit_price" type="number" step="0.01" placeholder="Einzelpr." class="border border-slate-200 rounded-md px-2 py-1.5 text-xs font-mono">
                    <select wire:model="newItem.mwst_rate" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs">
                        <option value="7">7%</option>
                        <option value="19">19%</option>
                    </select>
                    <input wire:model="newItem.total" type="number" step="0.01" placeholder="Gesamt" class="border border-slate-200 rounded-md px-2 py-1.5 text-xs font-mono">
                </div>
                <div class="flex justify-end">
                    <x-ui-button wire:click="addItem" variant="primary" size="sm">Position hinzufügen</x-ui-button>
                </div>
            </div>

            <div class="flex justify-end pt-3 border-t border-[var(--ui-border)]">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeItems">Schließen</x-ui-button>
            </div>
        </div>
    </x-ui-modal>
</div>
