<?php

namespace Backpack\Settings\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Backpack\Settings\Contracts\SettingsDriver;

class SettingsManager
{
    protected $cache;
    /** @var array<string, SettingsDriver> */
    protected $drivers;

    public function __construct(CacheRepository $cache, array $drivers)
    {
        $this->cache = $cache;
        $this->drivers = $drivers;
    }

    public function get(string $key, $default = null)
    {
        if (!config('backpack-settings.cache.enabled', true)) {
            return $this->resolve($key, $default);
        }

        $ttl = (int) config('backpack-settings.cache.ttl', 0);
        $store = $this->cache;

        $cacheKey = 'bp_settings:'.$key;
        if ($ttl > 0) {
            return $store->remember($cacheKey, $ttl, fn() => $this->resolve($key, $default));
        }
        return $store->rememberForever($cacheKey, fn() => $this->resolve($key, $default));
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

    public function set(string $key, $value, array $meta = []): void
    {
        // Cast info & group can be provided via $meta
        $cast = $meta['cast'] ?? null;
        $group = $meta['group'] ?? null;

        // Write to first writable driver (database)
        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                $driver->set($key, $this->castIn($value, $cast), $cast, $group);
                break;
            }
        }
        // Invalidate cache
        $this->cache->forget('bp_settings:'.$key);
    }

    protected function resolve(string $key, $default)
    {
        foreach ($this->prioritizedDrivers() as $driver) {
            if ($driver->has($key)) {
                $raw = $driver->get($key);
                // Try to get cast from DB if available
                $cast = null;
                if ($driver instanceof \Backpack\Settings\Drivers\DatabaseDriver) {
                    // Quick fetch cast column
                    $db = app('db'); $table = config('backpack-settings.table');
                    $row = $db->table($table)->where('key', $key)->first(['cast']);
                    $cast = $row->cast ?? null;
                }
                return $this->castOut($raw, $cast);
            }
        }
        return $default;
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
}
