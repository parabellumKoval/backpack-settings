<?php

namespace Backpack\Settings\Drivers;

use Illuminate\Contracts\Config\Repository as Config;
use Backpack\Settings\Contracts\SettingsDriver;

class ConfigDriver implements SettingsDriver
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function get(string $key)
    {
        return $this->config->get($key);
    }

    public function has(string $key): bool
    {
        return $this->config->has($key);
    }

    public function set(string $key, $value, ?string $cast = null, ?string $group = null): void
    {
        // Config is read-only; do nothing.
    }
}
