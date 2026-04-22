@props([
    'model'       => null,    {{-- z.B. 'event.inquiry_time' --}}
    'modifier'    => 'blur',  {{-- blur | defer | live --}}
    'placeholder' => 'HH:MM',
    'class'       => '',
])
<input wire:model.{{ $modifier }}="{{ $model }}"
       type="text"
       placeholder="{{ $placeholder }}"
       maxlength="5"
       inputmode="numeric"
       x-data="{ invalid: false }"
       x-on:input="
           let v = $el.value.replace(/[^0-9]/g,'').substring(0,4);
           if (v.length >= 3) v = v.substring(0,2) + ':' + v.substring(2);
           $el.value = v;
           invalid = false;
       "
       x-on:blur="
           const v = $el.value.trim();
           const ok = v === '' || /^([01]?\d|2[0-3]):[0-5]\d$/.test(v);
           if (!ok) {
               $el.value = '';
               $el.dispatchEvent(new Event('input', { bubbles: true }));
               $el.dispatchEvent(new Event('change', { bubbles: true }));
               invalid = false;
           } else {
               invalid = false;
           }
       "
       :class="invalid ? 'ring-1 ring-red-500 border-red-500 bg-red-50' : ''"
       class="{{ $class }}">
