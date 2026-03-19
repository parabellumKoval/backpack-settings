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

    public function get(string $key, $default = null, array $context = [])
    {
        $canon = $this->resolver->normalize($key);
        $contextData = $this->resolveContext($context);
        $variants = $contextData['variants'];

        if (config('backpack-settings.cache.enabled', true)) {
            $ttl = (int) config('backpack-settings.cache.ttl', 0);
            $cacheKey = $this->buildCacheKey($canon, $variants);
            $this->rememberCacheKey($canon, $cacheKey);

            $resolver = function () use ($key, $canon, $default, $contextData) {
                $isRegistered = $this->resolver->isRegistered($canon);
                if ($isRegistered) {
                    return $this->resolveRegistered($key, $canon, $default, $contextData);
                }
                $aggregate = $this->fetchDbByPrefixAggregate([$canon], $contextData['variants']);
                if (!empty($aggregate)) {
                    return $aggregate;
                }
                return $this->resolveConfigOnly($key, $canon, $default);
            };

            return $ttl > 0
                ? $this->cache->remember($cacheKey, $ttl, $resolver)
                : $this->cache->rememberForever($cacheKey, $resolver);
        }

        $isRegistered = $this->resolver->isRegistered($canon);
        if ($isRegistered) {
            return $this->resolveRegistered($key, $canon, $default, $contextData);
        }
        $aggregate = $this->fetchDbByPrefixAggregate([$canon], $contextData['variants']);
        if (!empty($aggregate)) {
            return $aggregate;
        }
        return $this->resolveConfigOnly($key, $canon, $default);
    }

    protected function resolveRegistered(string $original, string $canon, $default, array $contextData)
    {
        $aliases = $this->resolver->aliasesFor($canon);
        $variants = $contextData['variants'];

        $dbKeys = array_values(array_unique(array_merge([$canon], $aliases, [$original])));
        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                foreach ($dbKeys as $k) {
                    $row = $this->findDbRow($k, $variants);
                    if ($row !== null) {
                        return $this->castOut($row['value'], $row['cast']);
                    }
                }

                $aggregate = $this->fetchDbByPrefixAggregate($dbKeys, $variants);
                if (!empty($aggregate)) {
                    return $aggregate;
                }
                break;
            }
        }

        foreach ($aliases as $a) {
            if (config()->has($a)) return config($a);
        }
        if (!in_array($original, $aliases, true) && $original !== $canon && config()->has($original)) {
            return config($original);
        }

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
        $contextMeta = $this->normalizeMutationContext($meta);

        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                $driver->set($canon, $this->castIn($value, $cast), [
                    'cast' => $cast,
                    'group' => $group,
                    'region' => $contextMeta['region'],
                    'locale' => $contextMeta['locale'],
                ]);
                break;
            }
        }

        $this->forgetCacheKeys($canon);
        if ($canon !== $key) {
            $this->forgetCacheKeys($key);
        }

        // Инвалидируем агрегаты всех предков "a", "a.b", "a.b.c", ...
        $parts = explode('.', $canon);
        $prefix = '';
        foreach ($parts as $i => $p) {
            $prefix = $prefix ? ($prefix.'.'.$p) : $p;
            $this->forgetCacheKeys($prefix);
        }
    }

    public function forget(string $key, array $meta = []): void
    {
        $canon = $this->resolver->normalize($key);
        if (!$this->resolver->isRegistered($canon)) {
            return;
        }

        $contextMeta = $this->normalizeMutationContext($meta);

        foreach ($this->prioritizedDrivers() as $driver) {
            if (method_exists($driver, 'delete')) {
                $driver->delete($canon, [
                    'group' => $meta['group'] ?? null,
                    'region' => $contextMeta['region'],
                    'locale' => $contextMeta['locale'],
                ]);
            }
        }

        $this->forgetCacheKeys($canon);
        if ($canon !== $key) {
            $this->forgetCacheKeys($key);
        }

        $parts = explode('.', $canon);
        $prefix = '';
        foreach ($parts as $p) {
            $prefix = $prefix ? ($prefix.'.'.$p) : $p;
            $this->forgetCacheKeys($prefix);
        }
    }

    public function invalidate(string $key): void
    {
        $canon = $this->resolver->normalize($key);

        $this->forgetCacheKeys($canon);
        if ($canon !== $key) {
            $this->forgetCacheKeys($key);
        }

        $parts = explode('.', $canon);
        $prefix = '';
        foreach ($parts as $part) {
            $prefix = $prefix ? ($prefix.'.'.$part) : $part;
            $this->forgetCacheKeys($prefix);
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

    public function has(string $key, array $context = []): bool
    {
        return $this->get($key, '__MISSING__', $context) !== '__MISSING__';
    }

    public function many(array $keys, array $context = []): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, null, $context);
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

    protected function findDbRow(string $key, array $variants): ?array
    {
        $table = config('backpack-settings.table', 'backpack_settings');
        foreach ($variants as $variant) {
            $query = app('db')->table($table)->where('key', $key);

            if (array_key_exists('region', $variant)) {
                $variant['region'] === null
                    ? $query->whereNull('region')
                    : $query->where('region', $variant['region']);
            }

            if (array_key_exists('locale', $variant)) {
                $variant['locale'] === null
                    ? $query->whereNull('locale')
                    : $query->where('locale', $variant['locale']);
            }

            $row = $query->first(['value', 'cast']);
            if ($row) {
                return ['value' => $row->value, 'cast' => $row->cast];
            }
        }

        return null;
    }

    protected function fetchDbByPrefixAggregate(array $candidatePrefixes, array $variants)
    {
        $rowsByKey = [];

        foreach ($variants as $variant) {
            $rows = $this->queryVariantRows($candidatePrefixes, $variant);
            if ($rows->isEmpty()) {
                continue;
            }

            foreach ($rows as $row) {
                if (!isset($rowsByKey[$row->key])) {
                    $rowsByKey[$row->key] = $row;
                }
            }
        }

        if (empty($rowsByKey)) {
            return [];
        }

        $matchedPrefix = null;
        foreach ($candidatePrefixes as $pref) {
            foreach ($rowsByKey as $key => $row) {
                if (strpos($key, $pref . '.') === 0) {
                    if ($matchedPrefix === null || strlen($pref) > strlen($matchedPrefix)) {
                        $matchedPrefix = $pref;
                    }
                }
            }
        }

        if ($matchedPrefix === null) {
            return [];
        }

        $result = [];
        foreach ($rowsByKey as $key => $row) {
            $suffix = substr($key, strlen($matchedPrefix) + 1);
            if ($suffix === '' || $suffix === false) {
                continue;
            }

            $casted = $this->castOut($row->value, $row->cast ?? null);

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

    protected function queryVariantRows(array $candidatePrefixes, array $variant)
    {
        $table = config('backpack-settings.table', 'backpack_settings');
        $query = app('db')->table($table)->select(['key', 'value', 'cast']);

        $query->where(function ($q) use ($candidatePrefixes) {
            foreach ($candidatePrefixes as $pref) {
                $q->orWhere('key', 'like', $pref . '.%');
            }
        });

        if (array_key_exists('region', $variant)) {
            $variant['region'] === null
                ? $query->whereNull('region')
                : $query->where('region', $variant['region']);
        }

        if (array_key_exists('locale', $variant)) {
            $variant['locale'] === null
                ? $query->whereNull('locale')
                : $query->where('locale', $variant['locale']);
        }

        return $query->get();
    }

    protected function buildCacheKey(string $canon, array $variants): string
    {
        $version = $this->currentCacheVersion($canon);
        return 'bp_settings:' . $canon . ':' . $version . ':' . md5(json_encode($variants));
    }

    protected function rememberCacheKey(string $identifier, string $cacheKey): void
    {
        $mapKey = $this->cacheNamespaceKey($identifier);
        $known = $this->cache->get($mapKey, []);
        if (!in_array($cacheKey, $known, true)) {
            $known[] = $cacheKey;
            $this->cache->forever($mapKey, $known);
        }
    }

    protected function forgetCacheKeys(string $identifier): void
    {
        $mapKey = $this->cacheNamespaceKey($identifier);
        $keys = $this->cache->get($mapKey, []);
        foreach ($keys as $key) {
            $this->cache->forget($key);
        }
        $this->cache->forget($mapKey);
        $this->bumpCacheVersion($identifier);
    }

    protected function cacheNamespaceKey(string $identifier): string
    {
        return 'bp_settings:context_map:' . $identifier;
    }

    protected function cacheVersionKey(string $identifier): string
    {
        return 'bp_settings:version:' . $identifier;
    }

    protected function currentCacheVersion(string $identifier): string
    {
        $versionKey = $this->cacheVersionKey($identifier);
        $version = $this->cache->get($versionKey);
        if (!is_string($version) || $version === '') {
            $version = $this->generateCacheVersion();
            $this->cache->forever($versionKey, $version);
        }
        return $version;
    }

    protected function bumpCacheVersion(string $identifier): void
    {
        $versionKey = $this->cacheVersionKey($identifier);
        $this->cache->forever($versionKey, $this->generateCacheVersion());
    }

    protected function generateCacheVersion(): string
    {
        $micro = sprintf('%.6F', microtime(true));
        try {
            $random = bin2hex(random_bytes(4));
        } catch (\Exception $e) {
            $random = dechex(mt_rand());
        }
        return $micro . ':' . $random;
    }

    protected function resolveContext(array $context): array
    {
        $region = $this->normalizeRegion($context['region'] ?? ($context['country'] ?? null));
        $variants = $this->variantsFromRegionAndLocales($region, $this->localeCandidates($context));

        return [
            'variants' => $variants,
        ];
    }

    protected function normalizeMutationContext(array $meta): array
    {
        return [
            'region' => $this->normalizeRegion($meta['region'] ?? ($meta['country'] ?? null)),
            'locale' => $this->normalizeLocale($meta['locale'] ?? ($meta['language'] ?? null)),
        ];
    }

    protected function normalizeRegion($region): ?string
    {
        if ($region === null || $region === '') {
            return null;
        }
        return strtolower((string) $region);
    }

    protected function normalizeLocale($locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }
        $normalized = str_replace('_', '-', strtolower((string) $locale));
        return $normalized;
    }

    protected function availableLocales(): array
    {
        $configured = config('backpack-settings.available_locales');
        if ($configured !== null) {
            $list = array_is_list($configured) ? $configured : array_keys((array) $configured);
            $normalized = array_filter(array_map(fn ($locale) => $this->normalizeLocale($locale), $list));
            return array_values(array_unique($normalized));
        }

        $crudLocales = array_keys(config('backpack.crud.locales', []));
        if (!empty($crudLocales)) {
            $normalized = array_filter(array_map(fn ($locale) => $this->normalizeLocale($locale), $crudLocales));
            return array_values(array_unique($normalized));
        }

        return [];
    }

    protected function localeCandidates(array $context): array
    {
        $candidates = [];
        $explicit = $this->normalizeLocale($context['locale'] ?? ($context['language'] ?? null));
        if ($explicit) {
            $candidates[] = $explicit;
        }

        $acceptHeader = $context['accept_language'] ?? $context['accept-language'] ?? null;
        foreach ($this->parseAcceptLanguage($acceptHeader) as $locale) {
            $normalized = $this->normalizeLocale($locale);
            if ($normalized) {
                $candidates[] = $normalized;
                if (str_contains($normalized, '-')) {
                    $base = explode('-', $normalized)[0];
                    $candidates[] = $base;
                }
            }
        }

        if (config('backpack-settings.auto_locale', true)) {
            $appLocale = $this->normalizeLocale(app()->getLocale());
            if ($appLocale) {
                $candidates[] = $appLocale;
            }
        }

        $available = $this->availableLocales();
        if (!empty($available)) {
            $candidates = array_filter($candidates, function ($locale) use ($available) {
                return in_array($locale, $available, true);
            });
        }

        $unique = [];
        $ordered = [];
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            if (!isset($unique[$candidate])) {
                $unique[$candidate] = true;
                $ordered[] = $candidate;
            }
        }

        $ordered[] = null;

        return $ordered;
    }

    protected function parseAcceptLanguage(?string $header): array
    {
        if (!$header) {
            return [];
        }

        $segments = array_map('trim', explode(',', $header));
        $locales = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $locale = $segment;
            $quality = 1.0;

            if (strpos($segment, ';') !== false) {
                [$locale, $rest] = explode(';', $segment, 2);
                $locale = trim($locale);
                $quality = 1.0;
                if (preg_match('/q=([0-9.]+)/', $rest, $matches)) {
                    $quality = (float) $matches[1];
                }
            }

            $locales[] = ['locale' => $locale, 'q' => $quality];
        }

        usort($locales, function ($a, $b) {
            if ($a['q'] === $b['q']) {
                return 0;
            }
            return $a['q'] < $b['q'] ? 1 : -1;
        });

        return array_column($locales, 'locale');
    }

    protected function variantsFromRegionAndLocales(?string $region, array $locales): array
    {
        $regions = [];
        if ($region !== null) {
            $regions[] = $region;
        }
        $regions[] = null;

        $variants = [];
        foreach ($regions as $reg) {
            foreach ($locales as $locale) {
                $variants[] = ['region' => $reg, 'locale' => $locale];
            }
        }

        $unique = [];
        $ordered = [];
        foreach ($variants as $variant) {
            $key = ($variant['region'] ?? '__null__') . '|' . ($variant['locale'] ?? '__null__');
            if (!isset($unique[$key])) {
                $unique[$key] = true;
                $ordered[] = $variant;
            }
        }

        return $ordered;
    }
}
