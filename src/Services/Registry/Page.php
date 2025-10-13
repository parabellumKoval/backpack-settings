<?php

namespace Backpack\Settings\Services\Registry;

class Page
{
    public string $title;
    /** @var Field[] */
    public array $fields = [];

    protected Registry $registry;

    public function __construct(string $title, Registry $registry)
    {
        $this->title = $title;
        $this->registry = $registry;
    }

    public function add(Field $field): self
    {
        $this->fields[] = $field;
        $this->registry->registerField($field);
        return $this;
    }
}
