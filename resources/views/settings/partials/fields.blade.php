@php
  // Группируем по внутренним табам (на уровне полей)
  $byTab = collect($fields)->groupBy(function($f){ return $f['tab'] ?? '_default'; });
@endphp

@foreach ($byTab as $tab => $fieldsSet)
  <div class="mb-3">
    @if ($tab !== '_default')
      <h5 class="mb-3">{{ $tab }}</h5>
    @endif

    <div class="row">
      @foreach ($fieldsSet as $field)
        @php
          $bpView = 'crud::fields.' . $field['type'];
          $field = array_merge(['wrapper' => ['class' => 'form-group col-sm-12']], $field);
        @endphp

        @if (view()->exists($bpView))
          @include($bpView, ['field' => $field, 'crud' => $crud])
        @else
          @includeIf(config('backpack-settings.view_namespace').'::fields.' . $field['type'], ['field' => $field])
        @endif

        @php($fieldErrorKey = $field['error_key'] ?? null)
        @if ($fieldErrorKey && $errors->has($fieldErrorKey))
          <div class="invalid-feedback d-block">{{ $errors->first($fieldErrorKey) }}</div>
        @endif

        @if (!empty($field['regionable']) && $errors->has('region'))
          <div class="invalid-feedback d-block">{{ $errors->first('region') }}</div>
        @endif
      @endforeach
    </div>
  </div>
@endforeach
