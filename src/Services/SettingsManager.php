<?php

namespace Backpack\Settings\Services;

use Backpack\Settings\Contracts\SettingsDriver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;

class SettingsManager
{
    protected CacheRepository $cache;

    /** @var array<string, SettingsDriver> */
    protected array $drivers;

    protected KeyResolver $resolver;

    protected SettingsContextResolver $contextResolver;

    public function __construct(CacheRepository $cache, array $drivers, KeyResolver $resolver, SettingsContextResolver $contextResolver)
    {
        $this->cache            = $cache;
        $this->drivers          = $drivers;
        $this->resolver         = $resolver;
        $this->contextResolver  = $contextResolver;
    }

    /**
     * @param array<string,mixed>|object|null $context
     */
    public function get(string $key, $default = null, $context = [])
    {
        $context = $this->contextResolver->resolve($context);
        $canon   = $this->resolver->normalize($key);

        if (!config('backpack-settings.cache.enabled', true)) {
            return $this->resolveValue($key, $canon, $default, $context);
        }

        $ttl = (int) config('backpack-settings.cache.ttl', 0);
        $cacheKey = $this->cacheKey($canon, $context);

        $resolver = function () use ($key, $canon, $default, $context) {
            return $this->resolveValue($key, $canon, $default, $context);
        };

        return $ttl > 0
            ? $this->cache->remember($cacheKey, $ttl, $resolver)
            : $this->cache->rememberForever($cacheKey, $resolver);
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function resolveValue(string $original, string $canon, $default, array $context)
    {
        if ($this->resolver->isRegistered($canon)) {
            return $this->resolveRegistered($original, $canon, $default, $context);
        }

        $aggregate = $this->fetchDbByPrefixAggregate([$canon], $context);
        if (!empty($aggregate)) {
            return $aggregate;
        }

        return $this->resolveConfigOnly($original, $canon, $default);
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function resolveRegistered(string $original, string $canon, $default, array $context)
    {
        $aliases = $this->resolver->aliasesFor($canon);
        $dbKeys = array_values(array_unique(array_merge([$canon], $aliases, [$original])));

        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                foreach ($dbKeys as $k) {
                    if ($driver->has($k, $context)) {
                        $row = $this->getDbRow($k, $context);
                        return $this->castOut($row['value'], $row['cast'], $context, $row['is_translatable']);
                    }
                }

                $aggregate = $this->fetchDbByPrefixAggregate($dbKeys, $context);
                if (!empty($aggregate)) {
                    return $aggregate;
                }

                break;
            }
        }

        foreach ($aliases as $a) {
            if (config()->has($a)) {
                return config($a);
            }
        }

        if (!in_array($original, $aliases, true) && $original !== $canon && config()->has($original)) {
            return config($original);
        }

        if (config()->has($canon)) {
            return config($canon);
        }

        return $default;
    }

    protected function resolveConfigOnly(string $original, string $canon, $default)
    {
        $aliases = $this->resolver->aliasesFor($canon);

        if ($canon !== $original && config()->has($canon)) {
            return config($canon);
        }

        if (config()->has($original)) {
            return config($original);
        }

        foreach ($aliases as $a) {
            if (config()->has($a)) {
                return config($a);
            }
        }

        return $default;
    }

    /**
     * @param array<string,mixed>|object|null $context
     */
    public function set(string $key, $value, $context = []): void
    {
        $context = $this->contextResolver->resolve($context);
        $canon   = $this->resolver->normalize($key);

        if (!$this->resolver->isRegistered($canon)) {
            return;
        }

        $existingRow = $this->getDbRow($canon, $context);
        $context['cast'] = $context['cast'] ?? ($existingRow['cast'] ?? null);
        $context['is_translatable'] = array_key_exists('is_translatable', $context)
            ? (bool) $context['is_translatable']
            : (bool) ($context['translatable'] ?? $existingRow['is_translatable'] ?? false);

        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                $payload = $this->castIn($canon, $value, $context, $existingRow);
                $driver->set($canon, $payload, $context);
                break;
            }
        }

        if (config('backpack-settings.cache.enabled', true)) {
            $this->forgetCacheVariants($canon, $key, $context);
        }
    }

    /**
     * @param array<string,mixed>|object|null $context
     */
    public function has(string $key, $context = []): bool
    {
        return $this->get($key, '__MISSING__', $context) !== '__MISSING__';
    }

    /**
     * @param array<string,mixed>|object|null $context
     */
    public function many(array $keys, $context = []): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, null, $context);
        }

        return $result;
    }

    protected function prioritizedDrivers(): array
    {
        $ordered = [];
        foreach (config('backpack-settings.drivers', ['database', 'config']) as $name) {
            if (isset($this->drivers[$name])) {
                $ordered[] = $this->drivers[$name];
            }
        }

        return $ordered;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function cacheKey(string $key, array $context): string
    {
        $regionPart = $this->sanitizeCacheSegment($context['region'] ?? null, 'region-global');
        $localePart = $this->sanitizeCacheSegment($context['locale'] ?? null, 'locale-default');
        $keyPart = $this->sanitizeCacheSegment($key, $key);

        return sprintf('bp_settings:%s:%s:%s', $regionPart, $localePart, $keyPart);
    }

    protected function sanitizeCacheSegment($value, string $default): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $value = (string) $value;
        $sanitized = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $value);

        return $sanitized === '' ? $default : $sanitized;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function forgetCacheVariants(string $canon, string $original, array $context): void
    {
        $keys = array_unique(array_merge([$canon, $original], $this->resolver->aliasesFor($canon)));

        foreach ($keys as $key) {
            $this->forgetCacheForKey($key, $context);
        }

        $parts = explode('.', $canon);
        $prefix = '';
        foreach ($parts as $part) {
            $prefix = $prefix ? $prefix . '.' . $part : $part;
            $this->forgetCacheForKey($prefix, $context);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function forgetCacheForKey(string $key, array $context): void
    {
        foreach ($this->cacheRegions($context) as $region) {
            foreach ($this->cacheLocales($context) as $locale) {
                $variantContext = $context;
                $variantContext['region'] = $region;
                $variantContext['locale'] = $locale;
                $this->cache->forget($this->cacheKey($key, $variantContext));
            }
        }
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,?string>
     */
    protected function cacheRegions(array $context): array
    {
        $regions = (array) config('backpack-settings.context.supported_regions', []);
        if (!in_array(null, $regions, true)) {
            $regions[] = null;
        }

        $current = $context['region'] ?? null;
        if (!in_array($current, $regions, true)) {
            $regions[] = $current;
        }

        return array_values(array_unique($regions, SORT_REGULAR));
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,?string>
     */
    protected function cacheLocales(array $context): array
    {
        $locales = (array) config('backpack-settings.context.supported_locales', []);
        $current = $context['locale'] ?? null;
        $fallback = $context['fallback_locale'] ?? null;

        if ($current !== null && !in_array($current, $locales, true)) {
            $locales[] = $current;
        }

        if ($fallback !== null && !in_array($fallback, $locales, true)) {
            $locales[] = $fallback;
        }

        if (empty($locales)) {
            $locales[] = null;
        }

        return array_values(array_unique($locales));
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function castOut($value, ?string $cast, array $context, bool $isTranslatable = false)
    {
        if ($isTranslatable) {
            $translations = $this->decodeJsonToArray($value);
            if ($translations === []) {
                return null;
            }

            $locale = $context['locale'] ?? null;
            $fallback = $context['fallback_locale'] ?? null;

            if ($locale && array_key_exists($locale, $translations)) {
                return $this->applyCastOut($translations[$locale], $cast);
            }

            if ($fallback && array_key_exists($fallback, $translations)) {
                return $this->applyCastOut($translations[$fallback], $cast);
            }

            $first = reset($translations);
            return $this->applyCastOut($first, $cast);
        }

        return $this->applyCastOut($value, $cast);
    }

    protected function applyCastOut($value, ?string $cast)
    {
        if ($cast === null) {
            return $value;
        }

        switch ($cast) {
            case 'bool':
            case 'boolean':
                if (is_string($value)) {
                    $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    return $filtered ?? false;
                }

                return (bool) $value;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
            case 'array':
                return $this->decodeJsonToArray($value);
            case 'string':
                return (string) $value;
            default:
                return $value;
        }
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $existingRow
     */
    protected function castIn(string $key, $value, array $context, array $existingRow = [])
    {
        $cast = $context['cast'] ?? null;
        $isTranslatable = (bool) ($context['is_translatable'] ?? false);

        if ($isTranslatable) {
            if (is_array($value) && $this->isAssoc($value)) {
                $translations = $this->sanitizeTranslations($value);
            } else {
                $translations = $this->existingTranslations($key, $context, $existingRow);
                $locale = $context['locale'] ?? app()->getLocale();
                if ($locale !== null) {
                    $translations[$locale] = $value;
                }
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
                    $value = in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
                }

                return $value ? '1' : '0';
            default:
                return (string) $value;
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function getDbRow(string $key, array $context): array
    {
        $region = $context['region'] ?? null;
        $table = config('backpack-settings.table', 'backpack_settings');

        $query = app('db')->table($table)->where('key', $key);
        if ($region === null) {
            $query->whereNull('region');
        } else {
            $query->where('region', $region);
        }

        $row = $query->first(['value', 'cast', 'is_translatable']);

        if (!$row && $region !== null) {
            $row = app('db')->table($table)
                ->where('key', $key)
                ->whereNull('region')
                ->first(['value', 'cast', 'is_translatable']);
        }

        if (!$row) {
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

        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
            if (is_array($value)) {
                return $value;
            }
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function sanitizeTranslations(array $translations): array
    {
        $clean = [];
        foreach ($translations as $locale => $text) {
            if ($text === null) {
                continue;
            }

            if (is_string($text) && $text === '') {
                continue;
            }

            $clean[(string) $locale] = $text;
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $existingRow
     */
    protected function existingTranslations(string $key, array $context, array $existingRow = []): array
    {
        if (!empty($existingRow)) {
            $value = $existingRow['value'] ?? null;
        } else {
            $row = $this->getDbRow($key, $context);
            $value = $row['value'] ?? null;
        }

        if (!$value) {
            return [];
        }

        return $this->decodeJsonToArray($value);
    }

    /**
     * @param array<int,string> $candidatePrefixes
     * @param array<string,mixed> $context
     */
    protected function fetchDbByPrefixAggregate(array $candidatePrefixes, array $context)
    {
        $driver = $this->databaseDriver();
        if (!$driver) {
            return [];
        }

        $matchedPrefix = null;
        $matchedRows = [];

        foreach ($candidatePrefixes as $pref) {
            $rows = $driver->getByPrefix($pref, $context);
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

            $casted = $this->castOut($row['value'], $row['cast'] ?? null, $context, (bool) ($row['is_translatable'] ?? false));
            Arr::set($result, $suffix, $casted);
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

    protected function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
