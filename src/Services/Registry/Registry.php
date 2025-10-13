<?php

namespace Backpack\Settings\Services\Registry;

class Registry
{
    /** @var array<string, SettingsGroup> */
    protected $groups = [];

    /** @var array<string, Field> */
    protected array $fieldsByKey = [];

    public function group(string $slug, \Closure $callback): void
    {
        $group = $this->groups[$slug] ?? new SettingsGroup($slug, $this);
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

    public function registerField(Field $field): void
    {
        $this->fieldsByKey[$field->key] = $field;
    }

    public function fieldByKey(string $key): ?Field
    {
        return $this->fieldsByKey[$key] ?? null;
    }
}
