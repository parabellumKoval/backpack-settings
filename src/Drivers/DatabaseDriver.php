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

    public function get(string $key, ?string $region = null)
    {
        $row = $this->findRow($key, $region);

        return $row['value'] ?? null;
    }

    public function has(string $key, ?string $region = null): bool
    {
        return $this->findRow($key, $region) !== null;
    }

    public function set(string $key, $value, ?string $cast = null, ?string $group = null, ?string $region = null, bool $isTranslatable = false): void
    {
        $now = now();
        $payload = [
            'key' => $key,
            'value' => is_scalar($value) ? (string) $value : json_encode($value),
            'cast' => $cast,
            'group' => $group,
            'region' => $region,
            'is_translatable' => $isTranslatable,
            'updated_at' => $now,
        ];

        $existing = $this->findRow($key, $region, false);

        if ($existing) {
            $this->db->table($this->table)
                ->where('id', $existing['id'])
                ->update($payload);
        } else {
            $payload['created_at'] = $now;
            $this->db->table($this->table)->insert($payload);
        }
    }

    public function getByPrefix(string $prefix, ?string $region = null): array
    {
        $rows = $this->rowsByPrefix($prefix, $region);

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

    protected function rowsByPrefix(string $prefix, ?string $region = null): array
    {
        $like = $prefix . '.%';
        $columns = ['id', 'key', 'value', 'cast', 'group', 'region', 'is_translatable'];

        $rows = [];

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

    protected function findRow(string $key, ?string $region = null, bool $withFallback = true): ?array
    {
        $columns = ['id', 'key', 'value', 'cast', 'group', 'region', 'is_translatable'];

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
}
