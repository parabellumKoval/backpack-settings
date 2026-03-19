<?php

namespace Backpack\Settings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, $default = null, array $context = [])
 * @method static bool has(string $key, array $context = [])
 * @method static array many(array $keys, array $context = [])
 * @method static void set(string $key, $value, array $meta = [])
 * @method static void forget(string $key, array $meta = [])
 * @method static void invalidate(string $key)
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'backpack.settings';
    }
}
