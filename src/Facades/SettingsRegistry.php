<?php

namespace Backpack\Settings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void group(string $slug, \Closure $callback)
 * @method static array groups()
 * @method static \Backpack\Settings\Services\Registry\Registry getRegistry()
 */
class SettingsRegistry extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'backpack.settings.registry';
    }
}
