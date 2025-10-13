<?php

namespace Backpack\Settings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, $default = null, $context = [])
 * @method static bool has(string $key, $context = [])
 * @method static array many(array $keys, $context = [])
 * @method static void set(string $key, $value, $context = [])
*/
class Settings extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'backpack.settings';
    }
}
