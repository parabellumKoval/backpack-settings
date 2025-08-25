<?php

namespace Backpack\Settings\Services\Registry;

class SettingsGroup
{
    public string $slug;
    public string $title = '';
    public string $icon = '';
    /** @var Page[] */
    public array $pages = [];

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function page(string $title, \Closure $callback): self
    {
        $page = new Page($title);
        $callback($page);
        $this->pages[] = $page;
        return $this;
    }
}
