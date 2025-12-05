<?php

namespace Backpack\Settings\Contracts;

interface SettingsDriver
{
    public function get(string $key, array $context = []);
    public function has(string $key, array $context = []): bool;
    public function set(string $key, $value, array $meta = []): void;
    public function delete(string $key, array $context = []): void;
}
