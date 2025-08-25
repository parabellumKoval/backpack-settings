<?php

namespace Backpack\Settings\Contracts;

use Backpack\Settings\Services\Registry\Registry;

interface SettingsRegistrarInterface
{
    public function register(Registry $registry): void;
}
