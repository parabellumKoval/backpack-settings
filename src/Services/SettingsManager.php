<?php

namespace Backpack\Settings\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Backpack\Settings\Contracts\SettingsDriver;

class SettingsManager
{
    protected CacheRepository $cache;
    /** @var array<string, SettingsDriver> */
    protected array $drivers;
    protected KeyResolver $resolver;

    public function __construct(CacheRepository $cache, array $drivers, KeyResolver $resolver)
    {
        $this->cache    = $cache;
        $this->drivers  = $drivers;
        $this->resolver = $resolver;
    }

    public function get(string $key, $default = null)
    {
        $canon = $this->resolver->normalize($key);

        // Единый кэш для всех ключей (зарегистрированных и нет)
        if (config('backpack-settings.cache.enabled', true)) {
            $ttl = (int) config('backpack-settings.cache.ttl', 0);
            $cacheKey = 'bp_settings:'.$canon;

            $resolver = function () use ($key, $canon, $default) {
                $isRegistered = $this->resolver->isRegistered($canon);
                if ($isRegistered) {
                    return $this->resolveRegistered($key, $canon, $default);
                }
                // НОВОЕ: поддержка префикса для незарегистрированных
                $aggregate = $this->fetchDbByPrefixAggregate([$canon]);
                if (!empty($aggregate)) {
                    return $aggregate; // массив по дочерним ключам из БД
                }
                // как и раньше для незарегистрированных — только config()
                return $this->resolveConfigOnly($key, $canon, $default);
            };

            return $ttl > 0
                ? $this->cache->remember($cacheKey, $ttl, $resolver)
                : $this->cache->rememberForever($cacheKey, $resolver);
        }

        // Без кэша
        $isRegistered = $this->resolver->isRegistered($canon);
        if ($isRegistered) {
            return $this->resolveRegistered($key, $canon, $default);
        }
        $aggregate = $this->fetchDbByPrefixAggregate([$canon]);
        if (!empty($aggregate)) return $aggregate;
        return $this->resolveConfigOnly($key, $canon, $default);
    }

    protected function resolveRegistered(string $original, string $canon, $default)
    {
        $aliases = $this->resolver->aliasesFor($canon);

        // 2) DB: канон → алиасы → исходный
        $dbKeys = array_values(array_unique(array_merge([$canon], $aliases, [$original])));
        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                foreach ($dbKeys as $k) {
                    if ($driver->has($k)) {
                        $raw = $driver->get($k);
                        // cast из БД
                        $db = app('db'); $table = config('backpack-settings.table');
                        $row = $db->table($table)->where('key', $k)->first(['cast']);
                        $cast = $row->cast ?? null;
                        return $this->castOut($raw, $cast);
                    }
                }

                $aggregate = $this->fetchDbByPrefixAggregate($dbKeys);
                if (!empty($aggregate)) {
                    return $aggregate; // массив типа ['enabled'=>..., 'type'=>...]
                }
                break; // DB проверили — выходим
            }
        }

        // 3) config() по алиасам
        foreach ($aliases as $a) {
            if (config()->has($a)) return config($a);
        }
        // (на всякий случай) если original — не в списке алиасов
        if (!in_array($original, $aliases, true) && $original !== $canon && config()->has($original)) {
            return config($original);
        }

        // 4) config() по канону
        if (config()->has($canon)) return config($canon);

        return $default;
    }

    protected function resolveConfigOnly(string $original, string $canon, $default)
    {
        // Если ключ попадает как алиас в глобальных алиасах — читаем канон из config()
        $aliases = $this->resolver->aliasesFor($canon);
        // Сценарии:
        // a) original - алиас (aliasToCanon вернул канон, он != original)
        if ($canon !== $original) {
            // читаем config(канон)
            if (config()->has($canon)) return config($canon);
        }

        // b) original - канон (может иметь алиасы, но не зарегистрирован)
        // Сначала пробуем config(original)
        if (config()->has($original)) return config($original);

        // c) если есть алиасы для canon (из конфига), вдруг один из них реально лежит в конфиге
        foreach ($aliases as $a) {
            if (config()->has($a)) return config($a);
        }

        return $default;
    }

    public function set(string $key, $value, array $meta = []): void
    {
        $canon = $this->resolver->normalize($key);

        // Писать можно ТОЛЬКО зарегистрированные ключи
        if (!$this->resolver->isRegistered($canon)) {
            // мягко игнорируем (или можно бросать исключение - на твой вкус)
            return;
        }

        $cast = $meta['cast'] ?? null;
        $group = $meta['group'] ?? null;

        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                $driver->set($canon, $this->castIn($value, $cast), $cast, $group);
                break;
            }
        }

        // Инвалидация кэша по канону
        $this->cache->forget('bp_settings:'.$canon);
        if ($canon !== $key) $this->cache->forget('bp_settings:'.$key);

        // Инвалидируем агрегаты всех предков "a", "a.b", "a.b.c", ...
        $parts = explode('.', $canon);
        $prefix = '';
        foreach ($parts as $i => $p) {
            $prefix = $prefix ? ($prefix.'.'.$p) : $p;
            $this->cache->forget('bp_settings:'.$prefix);
        }
    }

    protected function prioritizedDrivers(): array
    {
        $ordered = [];
        foreach (config('backpack-settings.drivers', ['database','config']) as $name) {
            if (isset($this->drivers[$name])) {
                $ordered[] = $this->drivers[$name];
            }
        }
        return $ordered;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__MISSING__') !== '__MISSING__';
    }

    public function many(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    protected function castOut($value, ?string $cast)
    {
        if ($cast === null) {
            return $value;
        }
        switch ($cast) {
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
            case 'array':
                $decoded = json_decode($value, true);
                return $decoded === null ? [] : $decoded;
            case 'string':
                return (string) $value;
            default:
                return $value;
        }
    }

    protected function castIn($value, ?string $cast)
    {
        if ($cast === null) {
            return $value;
        }
        switch ($cast) {
            case 'json':
            case 'array':
                return is_string($value) ? $value : json_encode($value);
            case 'bool':
            case 'boolean':
                if (is_string($value)) {
                    $value = in_array(strtolower($value), ['1','true','on','yes'], true);
                }
                return $value ? '1' : '0';
            default:
                return (string) $value;
        }
    }

    protected function getDbRow(string $key): array
    {
        $table = config('backpack-settings.table', 'backpack_settings');
        $row = app('db')->table($table)->where('key', $key)->first(['value','cast']);
        return ['value' => $row->value ?? null, 'cast' => $row->cast ?? null];
    }

    /**
     * Ищет все записи с префиксом среди кандидатов (канон/алиасы/оригинал),
     * возвращает ассоциативный массив относительно "корня" префикса, только первый уровень.
     *
     * Пример:
     *  prefix=profile.referrals.triggers.review.published
     *  keys в БД:
     *    profile.referrals.triggers.review.published.enabled => 1
     *    profile.referrals.triggers.review.published.type    => "auto"
     *    profile.referrals.triggers.review.published.meta.x  => "y"
     *  вернёт:
     *    ['enabled'=>true,'type'=>'auto','meta'=>['x'=>'y']]  // (можно и «плоско», см. заметку ниже)
     */
    protected function fetchDbByPrefixAggregate(array $candidatePrefixes)
    {
        $table = config('backpack-settings.table', 'backpack_settings');

        // соберём LIKE условия по всем кандидатам, чтобы покрыть и канон, и алиасы, и оригинал
        $query = app('db')->table($table)->select(['key','value','cast']);
        $query->where(function($q) use ($candidatePrefixes) {
            foreach ($candidatePrefixes as $i => $pref) {
                $q->orWhere('key', 'like', $pref . '.%');
            }
        });

        $rows = $query->get();
        if ($rows->isEmpty()) return [];

        // Определим, по какому из кандидатов строить относительные хвосты:
        // выбираем самый длинный префикс, который действительно встретился в результатах
        $matchedPrefix = null;
        foreach ($candidatePrefixes as $pref) {
            foreach ($rows as $r) {
                if (strpos($r->key, $pref . '.') === 0) {
                    // берём самый длинный из совпавших
                    if ($matchedPrefix === null || strlen($pref) > strlen($matchedPrefix)) {
                        $matchedPrefix = $pref;
                    }
                }
            }
        }
        if ($matchedPrefix === null) return [];

        // Собираем дерево по первому уровню (enabled => ..., type => ..., meta => [...])
        $result = [];
        foreach ($rows as $r) {
            $suffix = substr($r->key, strlen($matchedPrefix) + 1); // отрезаем "prefix."
            if ($suffix === '' || $suffix === false) continue;

            // если есть вложенность дальше "a.b.c" — положим в подмассив (один проход)
            if (strpos($suffix, '.') !== false) {
                [$head, $tail] = explode('.', $suffix, 2);
                // простая вложенность: meta['x'] = ...
                if (!isset($result[$head]) || !is_array($result[$head])) {
                    $result[$head] = [];
                }
                // только один уровень расслаивания; глубже можно оставить плоским ключом
                if (strpos($tail, '.') === false) {
                    // tail like "x"
                    $casted = $this->castOut($r->value, $r->cast ?? null);
                    $result[$head][$tail] = $casted;
                } else {
                    // tail like "x.y" — можно сохранить как строку ключа "x.y"
                    $casted = $this->castOut($r->value, $r->cast ?? null);
                    $result[$head][$tail] = $casted;
                }
            } else {
                // простой ключ "enabled"
                $casted = $this->castOut($r->value, $r->cast ?? null);
                $result[$suffix] = $casted;
            }
        }

        return $result;
    }
}
