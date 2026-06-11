@props([
    'model'             => '',     {{-- z.B. 'bookingForm.bestuhlung' --}}
    'modifier'          => null,   {{-- blur | defer | live | null (plain wire:model) --}}
    'locationId'        => null,   {{-- gewaehlte Location der Buchung --}}
    'current'           => '',     {{-- aktueller Bestuhlungs-Wert (Legacy-Werte bleiben waehlbar) --}}
    'seatingByLocation' => [],     {{-- Map location_id => [['label' =>, 'pax' =>], ...] --}}
    'fallback'          => [],     {{-- Team-Settings-Liste (bisheriges Verhalten) --}}
    'placeholder'       => '—',
    'class'             => '',
])

@php
    // Bevorzugt die im Locations-Stamm gepflegten Bestuhlungsoptionen der
    // gewaehlten Location (inkl. PAX-Hinweis), sonst Team-Settings-Fallback.
    $locId = $locationId ? (int) $locationId : 0;
    $seats = $locId !== 0 ? ($seatingByLocation[$locId] ?? null) : null;

    $options = [];
    if (!empty($seats)) {
        foreach ($seats as $s) {
            $options[$s['label']] = $s['pax'] > 0 ? "{$s['label']} (bis ~{$s['pax']} PAX)" : $s['label'];
        }
    } else {
        foreach ($fallback as $f) {
            $options[(string) $f] = (string) $f;
        }
    }

    $cur = (string) ($current ?? '');
    if ($cur !== '' && !array_key_exists($cur, $options)) {
        $options[$cur] = $cur;
    }

    $wireModel = 'wire:model' . ($modifier ? '.' . $modifier : '');
@endphp

@if(!empty($options))
    <select {{ $wireModel }}="{{ $model }}" class="{{ $class }}">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $val => $text)
            <option value="{{ $val }}">{{ $text }}</option>
        @endforeach
    </select>
@else
    <input {{ $wireModel }}="{{ $model }}" type="text" placeholder="Reihen / Bankett / …"
           class="{{ $class }}">
@endif
