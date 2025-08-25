<?php

namespace Backpack\Settings\Services\Registry;

class Registry
{
    /** @var array<string, SettingsGroup> */
    protected $groups = [];

    public function group(string $slug, \Closure $callback): void
    {
        $group = $this->groups[$slug] ?? new SettingsGroup($slug);
        $callback($group);
        $this->groups[$slug] = $group;
    }

    /**
     * @return SettingsGroup[]
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public function get(string $slug): ?SettingsGroup
    {
        return $this->groups[$slug] ?? null;
    }
}
