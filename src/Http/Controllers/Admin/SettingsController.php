<?php

namespace Backpack\Settings\Http\Controllers\Admin;

use Illuminate\Support\Arr;
use Illuminate\Routing\Controller;
use Backpack\Settings\Facades\Settings;
use Illuminate\Http\Request;
use Backpack\Settings\Facades\SettingsRegistry;
use Backpack\Settings\Events\SettingsGroupChanged;


class SettingsController extends Controller
{
    public function edit(string $groupSlug)
    {
        $registry = SettingsRegistry::getFacadeRoot();
        $group = $registry->get($groupSlug);
        abort_if(!$group, 404);

        // Build pages fields
        $pages = [];
        foreach ($group->pages as $page) {
            $fields = [];
            foreach ($page->fields as $f) {
                $current = Settings::get($f->key, $f->default);
                $fields[] = $f->toBackpackArray($current);
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
        ]);
    }

    
    public function update(Request $request, string $groupSlug)
    {
        $group   = $this->resolveGroupOrFail($groupSlug);
        $fields  = $this->flattenFields($group);                   // [ ['key'=>..., 'type'=>..., 'cast'=>...], ... ]
        $payload = (array) $request->input('settings', []);

        // dd($payload);
        $before = $this->snapshotValues($fields, $groupSlug);      // ДО

        $this->persistValues($fields, $payload, $groupSlug);       // ЗАПИСЬ

        // (опционально) если у тебя есть теговый кеш по группе — сброс:
        // Cache::tags(["settings:group:{$groupSlug}"])->flush();

        $after = $this->snapshotValues($fields, $groupSlug);       // ПОСЛЕ
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
    protected function snapshotValues(array $fields, string $groupSlug): array
    {
        $state = [];
        foreach ($fields as $f) {
            $state[$f['key']] = \Settings::get($f['key'], null, [
                'cast'  => $f['cast'],
                'group' => $groupSlug,
            ]);
        }
        return $state;
    }

    /**
     * Запись значений из payload. Учитываем чекбоксы (неотмеченные → '0').
     * @param array<int, array{key:string,type:?string,cast:?string}> $fields
     * @param array<string,mixed> $payload
     */
    protected function persistValues(array $fields, array $payload, string $groupSlug): void
    {
        foreach ($fields as $f) {
            $key  = $f['key'];
            $type = $f['type'];
            $cast = $f['cast'];

            if (array_key_exists($key, $payload)) {
                $value = $payload[$key];
                if ($type === 'checkbox') {
                    $value = $value ? '1' : '0';
                }
                \Settings::set($key, $value, ['cast' => $cast, 'group' => $groupSlug]);
            } else {
                // для неотмеченного чекбокса — сохранить '0'
                if ($type === 'checkbox') {
                    \Settings::set($key, '0', ['cast' => $cast, 'group' => $groupSlug]);
                }
            }
        }
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
