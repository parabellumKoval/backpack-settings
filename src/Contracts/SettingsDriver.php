<?php

namespace Backpack\Settings\Contracts;

interface SettingsDriver
{
    public function get(string $key, ?string $region = null);
    public function has(string $key, ?string $region = null): bool;
    public function set(string $key, $value, ?string $cast = null, ?string $group = null, ?string $region = null, bool $isTranslatable = false): void;
}
