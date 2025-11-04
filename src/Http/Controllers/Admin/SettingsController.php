<?php

namespace Backpack\Settings\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Backpack\Settings\Facades\Settings;
use Illuminate\Http\Request;
use Backpack\Settings\Facades\SettingsRegistry;
use Backpack\Settings\Events\SettingsGroupChanged;
use Illuminate\Support\Arr;


class SettingsController extends Controller
{
    public function edit(Request $request, string $groupSlug)
    {
        $registry = SettingsRegistry::getFacadeRoot();
        $group = $registry->get($groupSlug);
        abort_if(!$group, 404);

        $context = $this->resolveRequestContext($request);
        $availableLocales = $this->availableLocales();
        $availableRegions = $this->availableRegions();
        $hasTranslatable = false;
        $hasRegionable = false;

        // Build pages fields
        $pages = [];
        foreach ($group->pages as $page) {
            $fields = [];
            foreach ($page->fields as $f) {
                $fieldContext = $this->fieldContext($context, [
                    'translatable' => $f->translatable,
                    'regionable' => $f->regionable,
                ]);
                $current = Settings::get($f->key, $f->default, $fieldContext);
                $fieldArr = $f->toBackpackArray($current);
                $fields[] = $fieldArr;

                if ($f->translatable) {
                    $hasTranslatable = true;
                }
                if ($f->regionable) {
                    $hasRegionable = true;
                }
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
            'currentLocale' => $context['locale'],
            'currentRegion' => $context['region'],
            'availableLocales' => $availableLocales,
            'availableRegions' => $availableRegions,
            'hasTranslatable' => $hasTranslatable,
            'hasRegionable' => $hasRegionable,
            'regionQueryParam' => config('backpack-settings.region_query_parameter', 'country'),
            'localeQueryParam' => config('backpack-settings.locale_query_parameter', 'locale'),
        ]);
    }

    
    public function update(Request $request, string $groupSlug)
    {
        $group   = $this->resolveGroupOrFail($groupSlug);
        $fields  = $this->flattenFields($group);                   // [ ['key'=>..., 'type'=>..., 'cast'=>...], ... ]
        $payload = (array) $request->input('settings', []);
        $context = $this->resolveRequestContext($request);

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
     * @return array<int, array{key:string,type:?string,cast:?string}>
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
                    'translatable' => $f->translatable ?? false,
                    'regionable' => $f->regionable ?? false,
                ];
            }
        }
        return $list;
    }

    /**
     * Снимок значений по ключам.
     * @param array<int, array{key:string,type:?string,cast:?string}> $fields
     * @return array<string,mixed>
     */
    protected function snapshotValues(array $fields, string $groupSlug, array $context): array
    {
        $state = [];
        foreach ($fields as $f) {
            $state[$f['key']] = \Settings::get($f['key'], null, $this->fieldContext($context, $f));
        }
        return $state;
    }

    /**
     * Запись значений из payload. Учитываем чекбоксы (неотмеченные → '0').
     * @param array<int, array{key:string,type:?string,cast:?string}> $fields
     * @param array<string,mixed> $payload
     */
    protected function persistValues(array $fields, array $payload, string $groupSlug, array $context): void
    {
        foreach ($fields as $f) {
            $key  = $f['key'];
            $type = $f['type'] ?? null;
            $cast = $f['cast'] ?? null;

             if (array_key_exists($key, $payload)) {
                $value = $payload[$key];
                if ($type === 'checkbox') {
                    $value = $value ? '1' : '0';
                }
                $meta = [
                    'cast' => $cast,
                    'group' => $groupSlug,
                ] + $this->fieldContext($context, $f);

                // if($key === 'profile.referrals.triggers.store.order_paid.levels')
                //     dd($key, $value, $meta);
                
                \Settings::set($key, $value, $meta);
            } else {
                // для неотмеченного чекбокса — сохранить '0'
                if ($type === 'checkbox') {
                    $meta = [
                        'cast' => $cast,
                        'group' => $groupSlug,
                    ] + $this->fieldContext($context, $f);
                    \Settings::set($key, '0', $meta);
                }
            }

        }
    }

    /**
     * Backpack repeatable field приходит строкой (json) или массивом.
     * Нормализуем к массиву и удаляем полностью пустые строки.
     *
     * @param mixed $value
     * @return array<int, array<string,mixed>>
     */
    // protected function normalizeRepeatableValue($value): array
    // {
    //     if ($value === null || $value === '') {
    //         return [];
    //     }

    //     if (is_string($value)) {
    //         $decoded = json_decode($value, true);
    //         $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    //     }

    //     if (!is_array($value)) {
    //         return [];
    //     }

    //     $filtered = [];
    //     foreach ($value as $row) {
    //         if (!is_array($row)) {
    //             continue;
    //         }

    //         $hasData = false;
    //         foreach ($row as $cell) {
    //             if ($cell !== '' && $cell !== null) {
    //                 $hasData = true;
    //                 break;
    //             }
    //         }

    //         if ($hasData) {
    //             $filtered[] = $row;
    //         }
    //     }

    //     return array_values($filtered);
    // }

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

    protected function resolveRequestContext(Request $request): array
    {
        $localeParam = config('backpack-settings.locale_query_parameter', 'locale');
        $regionParam = config('backpack-settings.region_query_parameter', 'country');

        $locale = $request->query($localeParam);
        if ($locale === null) {
            $locale = $request->input($localeParam);
        }

        $region = $request->query($regionParam);
        if ($region === null) {
            $region = $request->input($regionParam);
        }

        return [
            'locale' => $this->normalizeLocale($locale),
            'region' => $this->normalizeRegion($region),
        ];
    }

    protected function availableLocales(): array
    {
        $configured = config('backpack-settings.available_locales');
        if ($configured === null) {
            $configured = config('backpack.crud.locales', []);
        }

        if (empty($configured)) {
            return [];
        }

        if (array_is_list($configured)) {
            $configured = array_combine($configured, $configured);
        }

        $locales = [];
        foreach ($configured as $code => $label) {
            $normalized = $this->normalizeLocale($code);
            if ($normalized === null) {
                continue;
            }
            $locales[$normalized] = $label;
        }

        return $locales;
    }

    protected function availableRegions(): array
    {
        $regions = config('backpack-settings.available_regions', []);
        if (empty($regions)) {
            return [];
        }

        if (array_is_list($regions)) {
            $regions = array_combine($regions, $regions);
        }

        $normalized = [];
        foreach ($regions as $code => $label) {
            $normCode = $this->normalizeRegion($code);
            $normalized[$normCode ?? ''] = $label;
        }

        return $normalized;
    }

    protected function fieldContext(array $baseContext, array $fieldMeta): array
    {
        $context = [];
        if (!empty($fieldMeta['regionable'])) {
            $context['region'] = $baseContext['region'];
        }
        if (!empty($fieldMeta['translatable'])) {
            $context['locale'] = $baseContext['locale'];
        }
        return $context;
    }

    protected function normalizeLocale($locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }
        return str_replace('_', '-', strtolower((string) $locale));
    }

    protected function normalizeRegion($region): ?string
    {
        if ($region === null || $region === '') {
            return null;
        }
        return strtolower((string) $region);
    }

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
