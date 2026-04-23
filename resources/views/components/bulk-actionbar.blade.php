@props([
    'count'          => 0,                        {{-- Anzahl selektierter Eintraege --}}
    'deleteAction'   => 'deleteSelected',         {{-- Livewire-Methode fuer Loeschen --}}
    'clearAction'    => 'clearSelection',         {{-- Livewire-Methode fuer Auswahl aufheben --}}
    'label'          => 'Eintrag',                {{-- Singular-Label fuer Bestaetigung --}}
    'labelPlural'    => null,                     {{-- Optionales Plural; Default: label + 'e' --}}
])

@php
    $labelPlural = $labelPlural ?: ($label . 'e');
    $confirmText = "{$count} {$labelPlural} wirklich loeschen?";
@endphp

{{--
    Floating Action-Bar fuer Mehrfach-Auswahl. Erscheint nur wenn count > 0.
    Eingebettet als erstes Element zwischen Listen-Header und Tabelle.
--}}
@if($count > 0)
    <div class="flex items-center gap-3 px-4 py-2 bg-blue-50 border-b border-blue-200 text-[0.72rem]">
        <span class="font-semibold text-blue-900">{{ $count }} ausgewählt</span>
        <span class="text-blue-700/70 text-[0.65rem] hidden sm:inline">· Shift-Klick markiert Bereiche</span>
        <div class="ml-auto flex items-center gap-2">
            <x-ui-button variant="danger" size="sm"
                         wire:click="{{ $deleteAction }}"
                         wire:confirm="{{ $confirmText }}">
                @svg('heroicon-o-trash', 'w-3.5 h-3.5 inline') Löschen
            </x-ui-button>
            <button type="button" wire:click="{{ $clearAction }}"
                    class="text-[0.7rem] text-blue-800 hover:text-blue-900 underline underline-offset-2 cursor-pointer">
                Abbrechen
            </button>
        </div>
    </div>
@endif
