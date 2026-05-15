{{--
    Geteiltes Spalten-Header-Partial fuer Quote- und Order-Positions-Editor.
    Liefert <colgroup> mit festen Breiten + <thead>.

    Param:
      $mode = 'quote' (Default) | 'order'

    Spalten-Unterschiede:
      - Quote:  Gebinde, EK,        Preis, MwSt, Gesamt, Modus,    Bemerkung
      - Order:  Gebinde, Basis-EK,  EK,    MwSt, Gesamt,           Bemerkung
--}}
@php
    $mode = $mode ?? 'quote';
    $isQuote = $mode === 'quote';
    $thBase = 'py-1.5 px-1 text-[0.55rem] font-semibold text-[var(--ui-muted)] uppercase tracking-wider';
@endphp
<colgroup>
    <col style="width: 8px;">      {{-- Handle --}}
    <col style="width: 110px;">    {{-- Gruppe --}}
    <col>                          {{-- Name (flex) --}}
    <col style="width: 50px;">     {{-- Anz --}}
    <col style="width: 50px;">     {{-- Anz.2 --}}
    <col style="width: 56px;">     {{-- Uhrzeit --}}
    <col style="width: 56px;">     {{-- Bis --}}
    <col style="width: 70px;">     {{-- Gebinde --}}
    @if($isQuote)
        <col style="width: 64px;"> {{-- EK --}}
        <col style="width: 80px;"> {{-- Preis --}}
    @else
        <col style="width: 70px;"> {{-- Basis-EK --}}
        <col style="width: 70px;"> {{-- EK --}}
    @endif
    <col style="width: 64px;">     {{-- MwSt --}}
    <col style="width: 90px;">     {{-- Gesamt --}}
    @if($isQuote)
        <col style="width: 100px;"> {{-- Modus --}}
    @endif
    <col style="width: 140px;">    {{-- Bemerkung --}}
    <col style="width: 28px;">     {{-- Trash --}}
</colgroup>
<thead>
    <tr class="bg-slate-50 border-b border-slate-200">
        <th class="px-0 py-1.5"></th>
        <th class="text-left {{ $thBase }} px-2.5">Gruppe</th>
        <th class="text-left {{ $thBase }} px-2">Name</th>
        <th class="text-right {{ $thBase }}">Anz.</th>
        <th class="text-right {{ $thBase }}">Anz.2</th>
        <th class="text-left {{ $thBase }}">Uhrzeit</th>
        <th class="text-left {{ $thBase }}">Bis</th>
        <th class="text-left {{ $thBase }}">Gebinde</th>
        @if($isQuote)
            <th class="text-right {{ $thBase }}">EK €</th>
            <th class="text-right {{ $thBase }}">Preis</th>
        @else
            <th class="text-right {{ $thBase }}">Basis-EK</th>
            <th class="text-right {{ $thBase }}">EK €</th>
        @endif
        <th class="text-center {{ $thBase }}">MwSt.</th>
        <th class="text-right {{ $thBase }}">Gesamt €</th>
        @if($isQuote)
            <th class="text-left {{ $thBase }}" title="Positions-Modus (Override pro Position)">Modus</th>
        @endif
        <th class="text-left {{ $thBase }}">Bemerkung</th>
        <th></th>
    </tr>
</thead>
