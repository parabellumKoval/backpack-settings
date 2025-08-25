<?php

namespace Backpack\Settings\Services\Registry;

class Page
{
    public string $title;
    /** @var Field[] */
    public array $fields = [];

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function add(Field $field): self
    {
        $this->fields[] = $field;
        return $this;
    }
}
