<?php

namespace Backpack\Settings\Services\Registry;

class SettingsGroup
{
    public string $slug;
    public string $title = '';
    public string $icon = '';
    /** @var Page[] */
    public array $pages = [];

    protected Registry $registry;

    public function __construct(string $slug, Registry $registry)
    {
        $this->slug = $slug;
        $this->registry = $registry;
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
        $page = new Page($title, $this->registry);
        $callback($page);
        $this->pages[] = $page;
        return $this;
    }
}
