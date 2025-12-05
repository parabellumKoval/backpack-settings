<?php

namespace Backpack\Settings\Drivers;

use Illuminate\Database\ConnectionInterface;
use Backpack\Settings\Contracts\SettingsDriver;

class DatabaseDriver implements SettingsDriver
{
    protected $db;
    protected $table;

    public function __construct(ConnectionInterface $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    public function get(string $key, array $context = [])
    {
        $ctx = $this->normalizeContext($context);
        $query = $this->db->table($this->table)->where('key', $key);
        $this->applyContext($query, $ctx);
        $row = $query->first();
        return $row ? $row->value : null;
    }

    public function has(string $key, array $context = []): bool
    {
        $ctx = $this->normalizeContext($context);
        $query = $this->db->table($this->table)->where('key', $key);
        $this->applyContext($query, $ctx);
        return (bool) $query->exists();
    }

    public function set(string $key, $value, array $meta = []): void
    {
        $ctx = $this->normalizeContext($meta);
        $now = now();
        $payload = [
            'key' => $key,
            'value' => is_scalar($value) ? (string) $value : json_encode($value),
            'cast' => $meta['cast'] ?? null,
            'group' => $meta['group'] ?? null,
            'region' => $ctx['region'] ?? null,
            'locale' => $ctx['locale'] ?? null,
            'updated_at' => $now,
        ];

        $query = $this->db->table($this->table)->where('key', $key);
        $this->applyContext($query, $ctx);
        $exists = $query->exists();
        if ($exists) {
            $query->update($payload);
        } else {
            $payload['created_at'] = $now;
            $this->db->table($this->table)->insert($payload);
        }
    }

    public function delete(string $key, array $context = []): void
    {
        $ctx = $this->normalizeContext($context);
        $query = $this->db->table($this->table)->where('key', $key);
        $this->applyContext($query, $ctx);
        $query->delete();
    }

    public function getByPrefix(string $prefix, array $context = []): array
    {
        // ожидаем $prefix без завершающей точки
        $like = $prefix . '.%';

        // ВАЖНО: индекс по колонке `key`
        $query = $this->db->table($this->table)
            ->where('key', 'like', $like);

        $this->applyContext($query, $this->normalizeContext($context));

        return $query->pluck('value', 'key')->toArray();
    }

    protected function applyContext($query, array $context): void
    {
        if (array_key_exists('region', $context)) {
            $context['region'] === null
                ? $query->whereNull('region')
                : $query->where('region', $context['region']);
        }
        if (array_key_exists('locale', $context)) {
            $context['locale'] === null
                ? $query->whereNull('locale')
                : $query->where('locale', $context['locale']);
        }
    }

    protected function normalizeContext(array $context): array
    {
        $normalized = [];
        if (array_key_exists('region', $context) || array_key_exists('country', $context)) {
            $value = $context['region'] ?? ($context['country'] ?? null);
            $normalized['region'] = $this->normalizeRegion($value);
        }
        if (array_key_exists('locale', $context) || array_key_exists('language', $context)) {
            $value = $context['locale'] ?? ($context['language'] ?? null);
            $normalized['locale'] = $this->normalizeLocale($value);
        }
        return $normalized;
    }

    protected function normalizeRegion($region): ?string
    {
        if ($region === null) {
            return null;
        }
        $value = strtolower(trim((string) $region));
        return $value === '' ? null : $value;
    }

    protected function normalizeLocale($locale): ?string
    {
        if ($locale === null) {
            return null;
        }
        $normalized = str_replace('_', '-', strtolower(trim((string) $locale)));
        return $normalized === '' ? null : $normalized;
    }
}
