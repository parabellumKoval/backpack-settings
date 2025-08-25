<?php

namespace Backpack\Settings\Contracts;

interface SettingsDriver
{
    public function get(string $key);
    public function has(string $key): bool;
    public function set(string $key, $value, ?string $cast = null, ?string $group = null): void;
}
