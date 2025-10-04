<?php

namespace Backpack\Settings\Services;

use Backpack\Settings\Services\Registry\Registry;

class KeyResolver
{

    /** @var Registry */
    protected $registry;

    /** @var array<string, string[]> */
    protected $canonToAliases = [];

    /** @var array<string, string> */
    protected $aliasToCanon = [];

    /** @var array<string, bool> */
    protected $registered = [];

    /** @var array<string, string[]> */
    protected $appAliases = [];

    /** @var array<string, array> */
    protected $packagesAliases = [];

    // public function __construct(Registry $registry){
    //     $this->registry = $registry;
    //     $this->rebuildMaps($globalAliases);
    // }
    public function __construct(Registry $registry, array $appAliases = [], array $packagesAliases = [])
    {
        $this->registry        = $registry;
        $this->appAliases      = $appAliases;
        $this->packagesAliases = $packagesAliases;

        // можно собрать первоначальные карты (реестр пока пуст, но не страшно)
        $this->buildMaps($this->appAliases, $this->packagesAliases);
    }


    /**
     * Пересобрать карты с актуальными алиасами приложения/пакетов
     */
    public function refresh(array $appAliases = null, array $packagesAliases = null): void
    {
        if ($appAliases !== null)      $this->appAliases = $appAliases;
        if ($packagesAliases !== null) $this->packagesAliases = $packagesAliases;

        $this->buildMaps($this->appAliases, $this->packagesAliases);
    }

    protected function buildMaps(array $appAliases, array $packagesAliases): void
    {
        $canonToAliases = $appAliases;
        $registered = [];

        // 1) собрать из регистратора каноны и их алиасы
        foreach ($this->registry->groups() as $group) {
            foreach ($group->pages as $page) {
                foreach ($page->fields as $f) {
                    $registered[$f->key] = true;
                    if (!empty($f->aliases)) {
                        $canonToAliases[$f->key] = array_unique(array_merge(
                            isset($canonToAliases[$f->key]) ? $canonToAliases[$f->key] : [],
                            $f->aliases
                        ));
                    }
                }
            }
        }

        // 2) добавить алиасы из пакетов (config: backpack-settings.aliases_packages)
        foreach ($packagesAliases as $map) {
            foreach ((array) $map as $canon => $aliases) {
                $canonToAliases[$canon] = array_unique(array_merge(
                    isset($canonToAliases[$canon]) ? $canonToAliases[$canon] : [],
                    (array) $aliases
                ));
            }
        }

        // 3) инверсия alias -> canon
        $aliasToCanon = [];
        foreach ($canonToAliases as $canon => $aliases) {
            foreach ($aliases as $a) {
                $aliasToCanon[$a] = $canon;
            }
        }

        $this->registered     = $registered;
        $this->canonToAliases = $canonToAliases;
        $this->aliasToCanon   = $aliasToCanon;
    }

    /** Канон по ключу/алиасу (если нет — сам ключ) */
    public function normalize(string $key): string
    {
        return $this->aliasToCanon[$key] ?? $key;
    }

    /** Алиасы для канона */
    public function aliasesFor(string $canon): array
    {
        return $this->canonToAliases[$canon] ?? [];
    }

    /** Зарегистрирован ли канон (есть в реестре) */
    public function isRegistered(string $canon): bool
    {
        return isset($this->registered[$canon]);
    }
}
