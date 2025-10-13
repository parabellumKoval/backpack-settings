<?php

namespace Backpack\Settings\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Backpack\Settings\Facades\Settings;
use Illuminate\Http\Request;
use Backpack\Settings\Facades\SettingsRegistry;
use Backpack\Settings\Events\SettingsGroupChanged;


class SettingsController extends Controller
{
    public function edit(Request $request, string $groupSlug)
    {
        $registry = SettingsRegistry::getFacadeRoot();
        $group = $registry->get($groupSlug);
        abort_if(!$group, 404);

        $availableLocales = $this->availableLocales();
        $locale = $request->query('locale', app()->getLocale());
        if (!in_array($locale, $availableLocales, true)) {
            $locale = app()->getLocale();
        }

        $regions = (array) config('backpack-settings.regions', []);
        $region = $request->query('region');
        if ($region === null && !empty($regions)) {
            $region = array_key_first($regions);
        }

        app()->setLocale($locale);

        // Build pages fields
        $pages = [];
        $hasTranslatable = false;
        $hasRegionable = false;
        foreach ($group->pages as $page) {
            $fields = [];
            foreach ($page->fields as $f) {
                $hasTranslatable = $hasTranslatable || $f->translatable;
                $hasRegionable = $hasRegionable || $f->regionable;
                $current = Settings::get($f->key, $f->default, [
                    'cast' => $f->cast,
                    'group' => $groupSlug,
                    'translatable' => $f->translatable,
                    'regionable' => $f->regionable,
                    'region' => $region,
                    'locale' => $locale,
                    'return_full' => true,
                ]);
                $fields[] = $f->toBackpackArray($current, [
                    'region' => $region,
                    'locale' => $locale,
                ]);
            }
            $pages[] = [
                'title' => $page->title,
                'fields' => $fields,
            ];
        }

        return view(config('backpack-settings.view_namespace').'::settings.edit', [
            'group' => $group,
            'pages' => $pages,
            'action' => route('backpack.settings.update', ['group' => $groupSlug]),
            'regions' => $regions,
            'currentRegion' => $region,
            'availableLocales' => $availableLocales,
            'currentLocale' => $locale,
            'hasTranslatable' => $hasTranslatable,
            'hasRegionable' => $hasRegionable,
        ]);
    }

    
    public function update(Request $request, string $groupSlug)
    {
        $group   = $this->resolveGroupOrFail($groupSlug);
        $fields  = $this->flattenFields($group);                   // [ ['key'=>..., 'type'=>..., 'cast'=>...], ... ]
        $payload = (array) $request->input('settings', []);

        $availableLocales = $this->availableLocales();
        $locale = $request->input('locale', app()->getLocale());
        if (!in_array($locale, $availableLocales, true)) {
            $locale = app()->getLocale();
        }

        $regions = (array) config('backpack-settings.regions', []);
        $region = $request->input('region');
        if ($region === null && !empty($regions)) {
            $region = array_key_first($regions);
        }

        $requiresRegion = collect($fields)->contains(fn ($f) => $f['regionable']);
        if ($requiresRegion && ($region === null || $region === '')) {
            return redirect()->back()
                ->withErrors(['region' => __('Please select a region before saving these settings.')])
                ->withInput();
        }

        app()->setLocale($locale);

        $context = [
            'region' => $region,
            'locale' => $locale,
            'locales' => $availableLocales,
        ];

        // dd($payload);
        $before = $this->snapshotValues($fields, $groupSlug, $context);      // ДО

        $this->persistValues($fields, $payload, $groupSlug, $context);       // ЗАПИСЬ

        // (опционально) если у тебя есть теговый кеш по группе — сброс:
        // Cache::tags(["settings:group:{$groupSlug}"])->flush();

        $after = $this->snapshotValues($fields, $groupSlug, $context);       // ПОСЛЕ
        $diff  = $this->computeDiff($before, $after);

        event(new SettingsGroupChanged($groupSlug, $before, $after, $diff));

        return redirect()->back()->with('success', 'Settings saved.');
    }

    /**
     * Достаём группу или 404.
     */
    protected function resolveGroupOrFail(string $groupSlug)
    {
        $registry = SettingsRegistry::getFacadeRoot();
        $group = $registry->get($groupSlug);
        abort_if(!$group, 404);
        return $group;
    }

    /**
     * Превращаем $group->pages[*]->fields[*] в плоский массив данных полей.
     * @return array<int, array{key:string,type:?string,cast:?string,is_translatable:bool,is_regionable:bool}>
     */
    protected function flattenFields(object $group): array
    {
        $list = [];
        foreach ($group->pages as $page) {
            foreach ($page->fields as $f) {
                // гарантируем ключ
                if (empty($f->key)) { continue; }
                $list[] = [
                    'key'  => $f->key,
                    'type' => $f->type ?? null,
                    'cast' => $f->cast ?? null,
                    'is_translatable' => $f->translatable,
                    'is_regionable'   => $f->regionable,
                ];
            }
        }
        return $list;
    }

    /**
     * Снимок значений по ключам.
     * @param array<int, array{key:string,type:?string,cast:?string,is_translatable:bool,is_regionable:bool}> $fields
     * @return array<string,mixed>
     */
    protected function snapshotValues(array $fields, string $groupSlug, array $context = []): array
    {
        $state = [];
        foreach ($fields as $f) {
            $state[$f['key']] = Settings::get($f['key'], null, [
                'cast'  => $f['cast'],
                'group' => $groupSlug,
                'translatable' => $f['is_translatable'],
            ]);
        }
        return $state;
    }

    /**
     * Запись значений из payload. Учитываем чекбоксы (неотмеченные → '0').
     * @param array<int, array{key:string,type:?string,cast:?string,is_translatable:bool,is_regionable:bool}> $fields
     * @param array<string,mixed> $payload
     */
    protected function persistValues(array $fields, array $payload, string $groupSlug, array $context = []): void
    {
        $region = $context['region'] ?? null;
        $locale = $context['locale'] ?? null;
        $locales = $context['locales'] ?? [];

        foreach ($fields as $f) {
            $key  = $f['key'];
            $type = $f['type'];
            $cast = $f['cast'];
            $translatable = $f['translatable'] ?? false;
            $regionable = $f['regionable'] ?? false;

            $meta = [
                'cast' => $cast,
                'group' => $groupSlug,
                'translatable' => $translatable,
                'regionable' => $regionable,
                'region' => $region,
                'locale' => $locale,
            ];

            if ($translatable || $regionable) {
                $current = Settings::get($key, null, $meta + ['return_full' => true]);
                if (!is_array($current)) {
                    $current = [];
                }

                $incoming = $payload[$key] ?? ($translatable ? [] : null);

                if ($regionable) {
                    if ($region === null || $region === '') {
                        continue;
                    }
                    if (is_array($incoming) && array_key_exists($region, $incoming)) {
                        $incoming = $incoming[$region];
                    } else {
                        $incoming = $translatable ? [] : null;
                    }
                }

                if ($translatable) {
                    $existingLocales = [];
                    if ($regionable) {
                        $existingLocales = array_keys(is_array($current[$region] ?? null) ? $current[$region] : []);
                    } else {
                        $existingLocales = array_keys($current);
                    }
                    $normalized = $this->normalizeTranslatableValue(
                        $incoming,
                        $type,
                        $locales ?: $existingLocales,
                        $existingLocales
                    );

                    if ($regionable) {
                        $current[$region] = $normalized;
                    } else {
                        $current = $normalized;
                    }
                } else {
                    $normalized = $this->normalizeScalarValue($incoming, $type);
                    if ($regionable) {
                        $current[$region] = $normalized;
                    } else {
                        $current = $normalized;
                    }
                }

                Settings::set($key, $current, $meta + ['cast' => 'json']);
                continue;
            }

            if (array_key_exists($key, $payload)) {
                $value = $payload[$key];
                if ($type === 'checkbox') {
                    $value = $value ? '1' : '0';
                }
                \Settings::set($key, $value, ['cast' => $cast, 'group' => $groupSlug, 'translatable' => $f['is_translatable']]);
            } else {
                // для неотмеченного чекбокса — сохранить '0'
                if ($type === 'checkbox') {
                    \Settings::set($key, '0', ['cast' => $cast, 'group' => $groupSlug, 'translatable' => $f['is_translatable']]);
                }
            }
        }
    }

    protected function normalizeScalarValue($value, ?string $type)
    {
        if ($type === 'checkbox') {
            return $value ? '1' : '0';
        }

        return $value;
    }

    protected function normalizeTranslatableValue($incoming, ?string $type, array $preferredLocales, array $existingLocales): array
    {
        $incoming = is_array($incoming) ? $incoming : [];
        $locales = $preferredLocales;
        if (empty($locales)) {
            $locales = array_unique(array_merge(array_keys($incoming), $existingLocales));
        }

        $result = [];
        foreach ($locales as $loc) {
            $value = $incoming[$loc] ?? null;
            if ($type === 'checkbox') {
                $value = $value ? '1' : '0';
            }
            $result[$loc] = $value;
        }

        // включим любые переданные локали, которых нет в preferredLocales
        foreach ($incoming as $loc => $value) {
            if (!array_key_exists($loc, $result)) {
                if ($type === 'checkbox') {
                    $value = $value ? '1' : '0';
                }
                $result[$loc] = $value;
            }
        }

        return $result;
    }

    protected function availableLocales(): array
    {
        $configured = config('backpack.crud.locales', []);
        if (empty($configured)) {
            $configured = (array) config('app.locales', []);
        }
        if (empty($configured)) {
            $configured = [config('app.locale')];
        }

        return array_values(array_filter(array_unique($configured)));
    }

    /**
     * Запись значений из payload. Учитываем вложенные ключи (dot-notation),
     * repeatable и чекбоксы (неотмеченные → '0').
     * @param array<int, array{key:string,type:?string,cast:?string}> $fields
     * @param array<string,mixed> $payload
     */
    // protected function persistValues(array $fields, array $payload, string $groupSlug): void
    // {
    //     foreach ($fields as $f) {
    //         $key  = $f['key'];           // dot-key, напр. profile.referrals.triggers.store.order_paid.levels
    //         $type = $f['type'] ?? null;
    //         $cast = $f['cast'] ?? null;

    //         $hasValue = Arr::has($payload, $key);

    //         // Неотмеченный чекбокс должен сохраниться как '0'
    //         if (! $hasValue) {
    //             if ($type === 'checkbox') {
    //                 \Settings::set($key, '0', ['cast' => $cast, 'group' => $groupSlug]);
    //             }
    //             continue;
    //         }

    //         $value = data_get($payload, $key);

    //         // Нормализация checkbox → '1'/'0'
    //         if ($type === 'checkbox') {
    //             $value = $value ? '1' : '0';
    //         }

    //         // Нормализация repeatable (массив строк)
    //         if ($type === 'repeatable') {
    //             // Пустое значение → []
    //             if ($value === null || $value === '') {
    //                 $value = [];
    //             }

    //             if (is_array($value)) {
    //                 // Удаляем полностью пустые строки
    //                 $value = array_values(array_filter($value, function ($row) {
    //                     if (!is_array($row)) return false;
    //                     foreach ($row as $v) {
    //                         if ($v !== '' && $v !== null) return true;
    //                     }
    //                     return false;
    //                 }));

    //                 // Приводим типы известных полей (если они есть в строке)
    //                 foreach ($value as &$row) {
    //                     if (!is_array($row)) { $row = []; continue; }

    //                     if (array_key_exists('level', $row) && $row['level'] !== '' && $row['level'] !== null) {
    //                         $row['level'] = (int) $row['level'];
    //                     }
    //                     if (array_key_exists('value', $row) && $row['value'] !== '' && $row['value'] !== null) {
    //                         $row['value'] = (float) str_replace(',', '.', $row['value']);
    //                     }
    //                     if (array_key_exists('amount', $row) && $row['amount'] !== '' && $row['amount'] !== null) {
    //                         $row['amount'] = (float) str_replace(',', '.', $row['amount']);
    //                     }
    //                     // currency — строка; оставляем как есть
    //                 }
    //                 unset($row);
    //             }
    //         }

    //         \Settings::set($key, $value, ['cast' => $cast, 'group' => $groupSlug]);
    //     }
    // }

    /**
     * Diff по ключам.
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array<string, array{old:mixed,new:mixed}>
     */
    protected function computeDiff(array $before, array $after): array
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diff = [];
        foreach ($keys as $k) {
            $old = $before[$k] ?? null;
            $new = $after[$k]  ?? null;
            if ($old !== $new) {
                $diff[$k] = ['old' => $old, 'new' => $new];
            }
        }
        return $diff;
    }

}
