{{-- Simple toggle field; relies on Bootstrap 4 custom-switch --}}
@php
  $name = $field['name'];
  $label = $field['label'] ?? $name;
  $value = old(str_replace(['[',']'], ['.',''], $name), $field['value'] ?? null);
@endphp

<div class="custom-control custom-switch">
  <input type="checkbox" class="custom-control-input" id="{{ md5($name) }}" name="{{ $name }}" value="1" {{ $value ? 'checked' : '' }}>
  <label class="custom-control-label" for="{{ md5($name) }}">{{ $label }}</label>
</div>
