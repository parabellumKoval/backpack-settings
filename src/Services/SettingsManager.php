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

    public function get(string $key, $default = null, array $meta = [])
    {
        $canon = $this->resolver->normalize($key);
        $region = $meta['region'] ?? null;

        // Единый кэш для всех ключей (зарегистрированных и нет)
        if (config('backpack-settings.cache.enabled', true) && $region === null) {
            $ttl = (int) config('backpack-settings.cache.ttl', 0);
            $cacheKey = $this->cacheKey($canon, $region);

            $resolver = function () use ($key, $canon, $default, $meta, $region) {
                $isRegistered = $this->resolver->isRegistered($canon);
                if ($isRegistered) {
                    return $this->resolveRegistered($key, $canon, $default, $region, $meta);
                }
                // НОВОЕ: поддержка префикса для незарегистрированных
                $aggregate = $this->fetchDbByPrefixAggregate([$canon], $region);
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
            return $this->resolveRegistered($key, $canon, $default, $region, $meta);
        }
        $aggregate = $this->fetchDbByPrefixAggregate([$canon], $region);
        if (!empty($aggregate)) return $aggregate;
        return $this->resolveConfigOnly($key, $canon, $default);
    }

    protected function resolveRegistered(string $original, string $canon, $default, ?string $region = null, array $meta = [])
    {
        $aliases = $this->resolver->aliasesFor($canon);

        // 2) DB: канон → алиасы → исходный
        $dbKeys = array_values(array_unique(array_merge([$canon], $aliases, [$original])));
        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                foreach ($dbKeys as $k) {
                    if ($driver->has($k, $region)) {
                        $row = $this->getDbRow($k, $region);
                        return $this->castOut($row['value'], $row['cast'], $row['is_translatable']);
                    }
                }

                $aggregate = $this->fetchDbByPrefixAggregate($dbKeys, $region);
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
        $region = $meta['region'] ?? null;
        $existingRow = $this->getDbRow($canon, $region);
        $isTranslatable = array_key_exists('is_translatable', $meta)
            ? (bool) $meta['is_translatable']
            : ($existingRow['is_translatable'] ?? false);

        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                $payload = $this->castIn($canon, $value, $cast, $isTranslatable, $region);
                $driver->set($canon, $payload, $cast, $group, $region, $isTranslatable);
                break;
            }
        }

        if ($region === null && config('backpack-settings.cache.enabled', true)) {
            // Инвалидация кэша по канону
            $this->cache->forget($this->cacheKey($canon, null));
            if ($canon !== $key) {
                $this->cache->forget($this->cacheKey($key, null));
            }

            // Инвалидируем агрегаты всех предков "a", "a.b", "a.b.c", ...
            $parts = explode('.', $canon);
            $prefix = '';
            foreach ($parts as $i => $p) {
                $prefix = $prefix ? ($prefix.'.'.$p) : $p;
                $this->cache->forget($this->cacheKey($prefix, null));
            }
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

    protected function cacheKey(string $key, ?string $region = null): string
    {
        $regionSuffix = $region === null ? 'global' : ('region:' . $region);

        return 'bp_settings:' . $regionSuffix . ':' . $key;
    }

    public function has(string $key, array $meta = []): bool
    {
        return $this->get($key, '__MISSING__', $meta) !== '__MISSING__';
    }

    public function many(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    protected function castOut($value, ?string $cast, bool $isTranslatable = false)
    {
        if ($isTranslatable) {
            $translations = $this->decodeJsonToArray($value);
            if ($translations === []) {
                return null;
            }

            $locale = app()->getLocale();
            $fallback = config('app.fallback_locale');

            if (array_key_exists($locale, $translations)) {
                return $translations[$locale];
            }

            if ($fallback && array_key_exists($fallback, $translations)) {
                return $translations[$fallback];
            }

            return reset($translations);
        }

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
                $decoded = $this->decodeJsonToArray($value);
                return $decoded;
            case 'string':
                return (string) $value;
            default:
                return $value;
        }
    }

    protected function castIn(string $key, $value, ?string $cast, bool $isTranslatable = false, ?string $region = null)
    {
        if ($isTranslatable) {
            if (is_array($value)) {
                $translations = $this->sanitizeTranslations($value);
            } else {
                $translations = $this->existingTranslations($key, $region);
                $translations[app()->getLocale()] = $value;
                $translations = $this->sanitizeTranslations($translations);
            }

            return json_encode($translations, JSON_UNESCAPED_UNICODE);
        }

        if ($cast === null) {
            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            return $value;
        }

        switch ($cast) {
            case 'json':
            case 'array':
                return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
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

    protected function getDbRow(string $key, ?string $region = null): array
    {
        $table = config('backpack-settings.table', 'backpack_settings');

        $query = app('db')->table($table)->where('key', $key);
        if ($region === null) {
            $query->whereNull('region');
        } else {
            $query->where('region', $region);
        }

        $row = $query->first(['value','cast','is_translatable']);

        if (! $row && $region !== null) {
            $row = app('db')->table($table)
                ->where('key', $key)
                ->whereNull('region')
                ->first(['value','cast','is_translatable']);
        }

        if (! $row) {
            return [
                'value' => null,
                'cast' => null,
                'is_translatable' => false,
            ];
        }

        return [
            'value' => $row->value ?? null,
            'cast' => $row->cast ?? null,
            'is_translatable' => (bool) ($row->is_translatable ?? false),
        ];
    }

    protected function decodeJsonToArray($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function sanitizeTranslations(array $translations): array
    {
        $clean = [];
        foreach ($translations as $locale => $text) {
            if ($text === null || $text === '') {
                continue;
            }
            $clean[(string) $locale] = $text;
        }

        return $clean;
    }

    protected function existingTranslations(string $key, ?string $region = null): array
    {
        $row = $this->getDbRow($key, $region);

        if (! $row['value']) {
            return [];
        }

        return $this->decodeJsonToArray($row['value']);
    }

    protected function fetchDbByPrefixAggregate(array $candidatePrefixes, ?string $region = null)
    {
        $driver = $this->databaseDriver();
        if (! $driver) {
            return [];
        }

        $matchedPrefix = null;
        $matchedRows = [];

        foreach ($candidatePrefixes as $pref) {
            $rows = $driver->getByPrefix($pref, $region);
            if (!empty($rows)) {
                if ($matchedPrefix === null || strlen($pref) > strlen($matchedPrefix)) {
                    $matchedPrefix = $pref;
                    $matchedRows = $rows;
                }
            }
        }

        if ($matchedPrefix === null) {
            return [];
        }

        $result = [];
        foreach ($matchedRows as $fullKey => $row) {
            $suffix = substr($fullKey, strlen($matchedPrefix) + 1);
            if ($suffix === '' || $suffix === false) {
                continue;
            }

            $casted = $this->castOut($row['value'], $row['cast'] ?? null, (bool) ($row['is_translatable'] ?? false));

            if (strpos($suffix, '.') !== false) {
                [$head, $tail] = explode('.', $suffix, 2);
                if (!isset($result[$head]) || !is_array($result[$head])) {
                    $result[$head] = [];
                }
                $result[$head][$tail] = $casted;
            } else {
                $result[$suffix] = $casted;
            }
        }

        return $result;
    }

    protected function databaseDriver(): ?\Backpack\Settings\Drivers\DatabaseDriver
    {
        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                return $driver;
            }
        }

        return null;
    }
}
