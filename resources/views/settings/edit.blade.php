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
      @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif
      @if ($errors->has('settings_import_file'))
        <div class="alert alert-danger">{{ $errors->first('settings_import_file') }}</div>
      @endif

      <form method="POST" action="{{ $action }}">
        @csrf
        @if ($hasTranslatable)
          <input type="hidden" name="{{ $localeQueryParam }}" value="{{ $currentLocale ?? '' }}">
        @endif
        @if ($hasRegionable)
          <input type="hidden" name="{{ $regionQueryParam }}" value="{{ $selectedRegionValue ?? '' }}">
        @endif

        @if (($hasTranslatable && !empty($availableLocales)) || ($hasRegionable && !empty($availableRegions)))
          <div class="d-flex align-items-end flex-wrap mb-3">
            @if ($hasTranslatable && !empty($availableLocales))
              <div class="mr-3 mb-2">
                <label for="settings-locale-select" class="form-label mb-1">{{ __('Язык') }}</label>
                <select id="settings-locale-select"
                        class="form-control"
                        data-settings-context-select
                        data-query-param="{{ $localeQueryParam }}">
                  <option value="" {{ $currentLocale === null ? 'selected' : '' }}>{{ __('По умолчанию') }}</option>
                  @foreach ($availableLocales as $code => $label)
                    <option value="{{ $code }}" {{ $currentLocale === $code ? 'selected' : '' }}>
                      {{ $label }}
                    </option>
                  @endforeach
                </select>
              </div>
            @endif

            @if ($hasRegionable && !empty($availableRegions))
              <div class="mr-3 mb-2">
                <label for="settings-region-select" class="form-label mb-1">{{ __('Регион') }}</label>
                <select id="settings-region-select"
                        class="form-control"
                        data-settings-context-select
                        data-query-param="{{ $regionQueryParam }}">
                  <option value="" {{ ($selectedRegionValue ?? '') === '' ? 'selected' : '' }}>{{ __('Глобально') }}</option>
                  <option value="{{ $regionAllValue }}" {{ ($selectedRegionValue ?? '') === $regionAllValue ? 'selected' : '' }}>
                    {{ __('Все регионы') }}
                  </option>
                  @foreach ($availableRegions as $code => $label)
                    <option value="{{ $code }}" {{ ($selectedRegionValue ?? '') === $code ? 'selected' : '' }}>
                      {{ $label }}
                    </option>
                  @endforeach
                </select>
              </div>
            @endif

            <div class="mr-3 mb-2">
              <a href="{{ $exportAction }}" class="btn btn-outline-secondary">
                <i class="la la-download"></i> {{ __('Экспорт JSON') }}
              </a>
            </div>
            <div class="mr-3 mb-2">
              <button
                type="button"
                class="btn btn-outline-primary"
                data-settings-import-trigger
                data-target-input="settings-import-file"
              >
                <i class="la la-upload"></i> {{ __('Импорт JSON') }}
              </button>
            </div>
          </div>
        @else
          <div class="d-flex align-items-end flex-wrap mb-3">
            <div class="mr-3 mb-2">
              <a href="{{ $exportAction }}" class="btn btn-outline-secondary">
                <i class="la la-download"></i> {{ __('Экспорт JSON') }}
              </a>
            </div>
            <div class="mr-3 mb-2">
              <button
                type="button"
                class="btn btn-outline-primary"
                data-settings-import-trigger
                data-target-input="settings-import-file"
              >
                <i class="la la-upload"></i> {{ __('Импорт JSON') }}
              </button>
            </div>
          </div>
        @endif

        @if ($regionMode === 'all')
          <div class="alert alert-warning mb-3">
            {{ __('Значения будут применены ко всем регионам. Локальные переопределения будут перезаписаны.') }}
          </div>
        @endif

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

        <div hidden>
        <!-- load the view from the application if it exists, otherwise load the one in the package -->
        @if(view()->exists('vendor.backpack.crud.form_content'))
          @include('vendor.backpack.crud.form_content', ['fields' => $crud->fields(), 'action' => 'edit'])
        @else
          @include('crud::form_content', ['fields' => $crud->fields(), 'action' => 'edit'])
        @endif
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-success">
            <i class="la la-save"></i> {{ trans('backpack::crud.save') }}
          </button>
        </div>
      </form>

      <form
        id="settings-import-form"
        method="POST"
        action="{{ $importAction }}"
        enctype="multipart/form-data"
        class="d-none"
      >
        @csrf
        @if ($hasTranslatable)
          <input type="hidden" name="{{ $localeQueryParam }}" value="{{ $currentLocale ?? '' }}">
        @endif
        @if ($hasRegionable)
          <input type="hidden" name="{{ $regionQueryParam }}" value="{{ $selectedRegionValue ?? '' }}">
        @endif
        <input
          id="settings-import-file"
          type="file"
          name="settings_import_file"
          accept=".json,application/json,text/json"
          data-settings-import-input
          data-target-form="settings-import-form"
        >
      </form>
    </div>
  </div>
@endsection

@push('after_styles')
  <style>
    .settings-field-indicators {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      margin-left: 6px;
    }
    .settings-field-indicator {
      font-size: 0.9em;
      color: #6c757d;
    }
    .d-none {
      display: none !important;
    }
  </style>
@endpush

@push('after_scripts')
  <script>
    (function () {
      function decorateFieldIndicators(root) {
        var scope = root || document;
        if (!scope.querySelectorAll) return;
        var wrappers = scope.querySelectorAll('[data-field-translatable], [data-field-regionable]');
        if (!wrappers.length) return;

        wrappers.forEach(function (wrapper) {
          if (wrapper.getAttribute('data-field-indicators-ready') === '1') {
            return;
          }
          var label = wrapper.querySelector('label');
          if (!label) {
            wrapper.setAttribute('data-field-indicators-ready', '1');
            return;
          }

          var container = document.createElement('span');
          container.className = 'settings-field-indicators text-muted';
          var hasIcons = false;

          if (wrapper.hasAttribute('data-field-translatable')) {
            var translateIcon = document.createElement('i');
            translateIcon.className = 'la la-flag settings-field-indicator';
            translateIcon.title = 'Поле переводимое';
            container.appendChild(translateIcon);
            hasIcons = true;
          }

          if (wrapper.hasAttribute('data-field-regionable')) {
            var regionIcon = document.createElement('i');
            regionIcon.className = 'la la-globe settings-field-indicator';
            regionIcon.title = 'Поле зависит от региона';
            container.appendChild(regionIcon);
            hasIcons = true;
          }

          if (hasIcons) {
            label.appendChild(container);
          }

          wrapper.setAttribute('data-field-indicators-ready', '1');
        });
      }

      function runBpInit(container) {
        container = container || document;
        var $ = window.jQuery || window.$;
        if (!$) {
          decorateFieldIndicators(container);
          return;
        }

        // Если есть Backpack-овский helper — используем его
        if (window.crud && typeof window.crud.initFieldsWithJavascript === 'function') {
          window.crud.initFieldsWithJavascript(container);
          decorateFieldIndicators(container);
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

        decorateFieldIndicators(container);
      }

      // init после загрузки
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { runBpInit(document); });
      } else {
        runBpInit(document);
      }

      function attachImportActions() {
        var triggers = document.querySelectorAll('[data-settings-import-trigger]');
        var inputs = document.querySelectorAll('[data-settings-import-input]');

        triggers.forEach(function (trigger) {
          trigger.addEventListener('click', function () {
            var inputId = this.getAttribute('data-target-input');
            if (!inputId) return;

            var input = document.getElementById(inputId);
            if (!input) return;

            input.click();
          });
        });

        inputs.forEach(function (input) {
          input.addEventListener('change', function () {
            if (!this.files || !this.files.length) {
              return;
            }

            var formId = this.getAttribute('data-target-form');
            if (!formId) return;

            var form = document.getElementById(formId);
            if (!form) return;

            form.submit();
          });
        });
      }

      function attachContextSwitcher() {
        var selects = document.querySelectorAll('[data-settings-context-select]');
        if (!selects.length) return;

        selects.forEach(function(select) {
          select.addEventListener('change', function () {
            var param = this.getAttribute('data-query-param');
            if (!param) return;

            var targetValue = this.value || '';
            var hasModernApi = typeof window.URL === 'function' && typeof window.URLSearchParams === 'function';

            if (hasModernApi) {
              try {
                var current = new window.URL(window.location.href);
                if (targetValue) {
                  current.searchParams.set(param, targetValue);
                } else {
                  current.searchParams.delete(param);
                }
                window.location.href = current.toString();
                return;
              } catch (e) {
                // fallback below
              }
            }

            var search = window.location.search ? window.location.search.substring(1) : '';
            var pairs = search ? search.split('&') : [];
            var params = {};

            pairs.forEach(function (piece) {
              if (!piece) return;
              var parts = piece.split('=');
              var key = decodeURIComponent(parts[0] || '');
              if (!key) return;
              var value = parts.length > 1 ? decodeURIComponent(parts[1]) : '';
              params[key] = value;
            });

            if (targetValue) {
              params[param] = targetValue;
            } else {
              delete params[param];
            }

            var newQuery = Object.keys(params).map(function (key) {
              return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }).join('&');

            var base = window.location.origin || (window.location.protocol + '//' + window.location.host);
            var path = window.location.pathname || '';
            var hash = window.location.hash || '';
            var next = base + path + (newQuery ? '?' + newQuery : '') + hash;
            window.location.href = next;
          });
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
          attachContextSwitcher();
          attachImportActions();
        });
      } else {
        attachContextSwitcher();
        attachImportActions();
      }
    })();
  </script>
@endpush
