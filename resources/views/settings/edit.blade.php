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
    <h2 class="mb-0">
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

      <form method="POST" action="{{ $action }}">
        @csrf

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

        <div class="mt-3">
          <button type="submit" class="btn btn-success">
            <i class="la la-save"></i> {{ trans('backpack::crud.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>

@section('after_styles')
  @stack('crud_fields_styles')
@endsection

@section('after_scripts')
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
