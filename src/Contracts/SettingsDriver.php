<?php

namespace Backpack\Settings\Contracts;

interface SettingsDriver
{
    /**
     * @param array<string,mixed> $context
     */
    public function get(string $key, array $context = []);

    /**
     * @param array<string,mixed> $context
     */
    public function has(string $key, array $context = []): bool;

    /**
     * @param array<string,mixed> $context
     */
    public function set(string $key, $value, array $context = []): void;
}
