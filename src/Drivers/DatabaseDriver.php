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

    public function get(string $key)
    {
        $row = $this->db->table($this->table)->where('key', $key)->first();
        return $row ? $row->value : null;
    }

    public function has(string $key): bool
    {
        return (bool) $this->db->table($this->table)->where('key', $key)->exists();
    }

    public function set(string $key, $value, ?string $cast = null, ?string $group = null): void
    {
        $now = now();
        $payload = [
            'key' => $key,
            'value' => is_scalar($value) ? (string) $value : json_encode($value),
            'cast' => $cast,
            'group' => $group,
            'updated_at' => $now,
        ];

        $exists = $this->db->table($this->table)->where('key', $key)->exists();
        if ($exists) {
            $this->db->table($this->table)->where('key', $key)->update($payload);
        } else {
            $payload['created_at'] = $now;
            $this->db->table($this->table)->insert($payload);
        }
    }
}
