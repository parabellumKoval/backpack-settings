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
        $row = $this->findRow($key, $context);

        return $row['value'] ?? null;
    }

    public function has(string $key, array $context = []): bool
    {
        return $this->findRow($key, $context) !== null;
    }

    public function set(string $key, $value, array $context = []): void
    {
        $now = now();
        $region = $this->extractRegion($context);
        $payload = [
            'key' => $key,
            'value' => is_scalar($value) ? (string) $value : json_encode($value),
            'cast' => $context['cast'] ?? null,
            'group' => $context['group'] ?? null,
            'region' => $region,
            'is_translatable' => (bool) ($context['is_translatable'] ?? $context['translatable'] ?? false),
            'updated_at' => $now,
        ];

        $existing = $this->findRow($key, $context, false);

        if ($existing) {
            $this->db->table($this->table)
                ->where('id', $existing['id'])
                ->update($payload);
        } else {
            $payload['created_at'] = $now;
            $this->db->table($this->table)->insert($payload);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    public function getByPrefix(string $prefix, array $context = []): array
    {
        $rows = $this->rowsByPrefix($prefix, $context);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = [
                'value' => $row['value'],
                'cast' => $row['cast'],
                'is_translatable' => (bool) ($row['is_translatable'] ?? false),
                'region' => $row['region'],
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function rowsByPrefix(string $prefix, array $context = []): array
    {
        $like = $prefix . '.%';
        $columns = ['id', 'key', 'value', 'cast', 'group', 'region', 'is_translatable'];

        $rows = [];

        $region = $this->extractRegion($context);

        foreach ($this->queryForRegion($region)->where('key', 'like', $like)->get($columns) as $row) {
            $row = (array) $row;
            $rows[$row['key']] = $row;
        }

        if ($region !== null) {
            foreach ($this->queryForRegion(null)->where('key', 'like', $like)->get($columns) as $row) {
                $row = (array) $row;
                if (!isset($rows[$row['key']])) {
                    $rows[$row['key']] = $row;
                }
            }
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function findRow(string $key, array $context = [], bool $withFallback = true): ?array
    {
        $columns = ['id', 'key', 'value', 'cast', 'group', 'region', 'is_translatable'];

        $region = $this->extractRegion($context);
        $query = $this->queryForRegion($region)->where('key', $key);
        $row = $query->first($columns);

        if (!$row && $withFallback && $region !== null) {
            $row = $this->queryForRegion(null)->where('key', $key)->first($columns);
        }

        return $row ? (array) $row : null;
    }

    protected function queryForRegion(?string $region)
    {
        $query = $this->db->table($this->table);

        if ($region === null) {
            $query->whereNull('region');
        } else {
            $query->where('region', $region);
        }

        return $query;
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function extractRegion(array $context): ?string
    {
        $region = $context['region'] ?? null;

        if ($region === null || $region === '') {
            return null;
        }

        return (string) $region;
    }
}
