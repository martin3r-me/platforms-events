@props([
    'uuid'       => '',      {{-- UUID der Zeile --}}
    'index'      => 0,       {{-- Position in der Liste (0-basiert) --}}
    'isSelected' => false,   {{-- Ob diese Zeile aktuell in der Auswahl ist --}}
    'toggle'     => 'toggleSelection',       {{-- Livewire-Methode fuer Single-Toggle --}}
    'range'      => 'toggleSelectionRange',  {{-- Livewire-Methode fuer Range --}}
    'toggleAll'  => 'toggleSelectionAll',    {{-- Livewire-Methode fuer Doppelklick --}}
])

{{--
    Klickbarer 8px-Streifen als td links vor der ersten Datenspalte.
    - Klick:       toggle einer Zeile
    - Shift+Klick: Range zwischen letzter Klick-Position und aktueller
    - Doppelklick: alle toggeln

    Die Alpine-State-Variable `lastIdx` muss auf einem umschliessenden
    Element definiert sein (idealerweise x-data="{ lastIdx: null }" auf
    <table> oder <tbody>).
--}}
<td class="p-0 w-[8px] relative cursor-pointer select-none"
    title="Klicken zum Auswählen · Shift für Bereich · Doppelklick für Alle"
    x-on:click.stop="
        if ($event.detail === 2) {
            $wire.{{ $toggleAll }}();
            return;
        }
        if ($event.shiftKey && lastIdx !== null && lastIdx !== {{ $index }}) {
            const target = !{{ $isSelected ? 'true' : 'false' }};
            $wire.{{ $range }}(lastIdx, {{ $index }}, target);
        } else {
            $wire.{{ $toggle }}(@js($uuid));
        }
        lastIdx = {{ $index }};
    ">
    <span class="absolute inset-y-0 left-0 w-[3px] transition-colors
                 {{ $isSelected ? 'bg-[var(--ui-primary)]' : 'bg-transparent group-hover:bg-slate-300' }}"></span>
</td>
