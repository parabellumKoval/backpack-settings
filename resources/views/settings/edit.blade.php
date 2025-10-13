@php
    // 1) Гарантируем наличие CrudPanel в контейнере
    if (!app()->bound('crud')) {
        app()->instance('crud', app(\Backpack\CRUD\app\Library\CrudPanel\CrudPanel::class));
    }

    /** @var \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud */
    $crud = app('crud');

    // 2) Устанавливаем операцию, НО не присваиваем результат
    if (method_exists($crud, 'setOperation')) {
        $crud->setOperation('update'); // не "$crud = ..."
    }

    // 3) Дадим CrudPanel'у модель (любую Eloquent-модель), чтобы проверки перевода не падали
    if (method_exists($crud, 'setModel')) {
        $crud->setModel(\Backpack\Settings\Models\Setting::class);
    }
@endphp

@extends(backpack_view('blank'))

@section('header')
  <div class="container-fluid">
    <h2 class="mb-3">
      {{ $group->title ?: config('backpack-settings.titles.default_group') }}
    </h2>
  </div>
@endsection

@section('content')
  <div class="row">
    <div class="col-md-12">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      @php
        $selectedLocale = old('locale', $currentLocale ?? app()->getLocale());
        $selectedRegion = old('region', $currentRegion ?? null);
        if (!empty($availableLocales ?? []) && !in_array($selectedLocale, $availableLocales, true)) {
          $selectedLocale = $currentLocale ?? app()->getLocale();
        }
        if (!empty($regions ?? []) && !array_key_exists($selectedRegion, $regions)) {
          $selectedRegion = $currentRegion ?? null;
        }
      @endphp

      <form method="POST" action="{{ $action }}">
        @csrf
        <input type="hidden" name="locale" value="{{ $selectedLocale }}">
        <input type="hidden" name="region" value="{{ $selectedRegion }}">

        <div class="d-flex flex-wrap align-items-center mb-3">
          @if (!empty($hasTranslatable))
            @includeIf(backpack_view('inc.multilingual_language_switcher'), [
              'crud' => $crud,
              'currentLocale' => $selectedLocale,
              'locales' => $availableLocales ?? [],
            ])
          @endif

          @if (!empty($hasRegionable) && !empty($regions ?? []))
            <div class="ml-auto">
              <label for="settings-region" class="d-block mb-1">{{ __('Region') }}</label>
              <select id="settings-region" class="form-control" onchange="(function(select){
                var url = new URL(window.location.href);
                if (select.value) {
                  url.searchParams.set('region', select.value);
                } else {
                  url.searchParams.delete('region');
                }
                url.searchParams.set('locale', '{{ $selectedLocale }}');
                window.location = url.toString();
              })(this)">
                @foreach ($regions as $value => $label)
                  <option value="{{ $value }}" {{ $value === $selectedRegion ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
              @if ($errors->has('region'))
                <div class="invalid-feedback d-block">{{ $errors->first('region') }}</div>
              @endif
            </div>
          @endif
        </div>

        <ul class="nav nav-tabs" role="tablist">
          @foreach ($pages as $i => $page)
            <li class="nav-item">
              <a class="nav-link {{ $i===0 ? 'active' : '' }}" data-toggle="tab" href="#page-{{ $i }}" role="tab">
                {{ $page['title'] }}
              </a>
            </li>
          @endforeach
        </ul>

        <div class="tab-content p-3 border border-top-0">
          @foreach ($pages as $i => $page)
            <div class="tab-pane fade {{ $i===0 ? 'show active' : '' }}" id="page-{{ $i }}" role="tabpanel">
              @include(config('backpack-settings.view_namespace').'::settings.partials.fields', ['fields' => $page['fields']])
            </div>
          @endforeach
        </div>

        <!-- load the view from the application if it exists, otherwise load the one in the package -->
        @if(view()->exists('vendor.backpack.crud.form_content'))
          @include('vendor.backpack.crud.form_content', ['fields' => $crud->fields(), 'action' => 'edit'])
        @else
          @include('crud::form_content', ['fields' => $crud->fields(), 'action' => 'edit'])
        @endif

        <div class="mt-3">
          <button type="submit" class="btn btn-success">
            <i class="la la-save"></i> {{ trans('backpack::crud.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>

@section('after_styles')
  <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/crud.css').'?v='.config('backpack.base.cachebusting_string') }}">
  <link rel="stylesheet" href="{{ asset('packages/backpack/crud/css/form.css').'?v='.config('backpack.base.cachebusting_string') }}">
  @stack('crud_fields_styles')
@endsection

@section('after_scripts')
  @if (!empty($hasTranslatable))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var links = document.querySelectorAll('a[href*="locale="]');
        links.forEach(function (link) {
          try {
            var url = new URL(link.getAttribute('href'), window.location.origin);
            if ('{{ $selectedRegion }}' !== '') {
              url.searchParams.set('region', '{{ $selectedRegion }}');
            } else {
              url.searchParams.delete('region');
            }
            link.setAttribute('href', url.toString());
          } catch (e) {}
        });
      });
    </script>
  @endif
  @stack('crud_fields_scripts')

  <script>
    (function () {
      function runBpInit(container) {
        container = container || document;
        var $ = window.jQuery || window.$;
        if (!$) return;

        // Если есть Backpack-овский helper — используем его
        if (window.crud && typeof window.crud.initFieldsWithJavascript === 'function') {
          window.crud.initFieldsWithJavascript(container);
          return;
        }

        // Фоллбэк: вызов всех data-init-function вручную
        $('[data-init-function]', container).each(function () {
          var $el = $(this);
          var fnName = $el.attr('data-init-function');
          var fn = window[fnName];
          if (typeof fn === 'function') {
            fn($el);
          }
        });
      }

      // init после загрузки
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { runBpInit(document); });
      } else {
        runBpInit(document);
      }
    })();
  </script>
@endsection
@endsection
