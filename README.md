# parabellumkoval/backpack-settings

Settings UI & Service for Laravel 8 + Backpack 4.1.

- Use **base Backpack fields** _and_ add **custom fields**.
- Define settings via a **Class Registrar** (separate from providers).
- **Tabs** support per page (and per field via `tab()`).
- DB + Config drivers, with **DB override**.
- Simple facade: `Settings::get('some.key')` / `Settings::set(...)`.

## Install

```bash
composer require parabellumkoval/backpack-settings
php artisan vendor:publish --provider="Backpack\Settings\Providers\BackpackSettingsServiceProvider" --tag=config
php artisan vendor:publish --provider="Backpack\Settings\Providers\BackpackSettingsServiceProvider" --tag=migrations
php artisan migrate
```

## Register your settings via Class Registrar

Create a class in your package/app, e.g. `App\Settings\StoreSettingsRegistrar.php`:

```php
<?php

namespace App\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

class StoreSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        $registry->group('store', function ($group) {
            $group->title('Магазин')->icon('la la-store')
                ->page('Общее', function ($page) {
                    $page->add(Field::make('store.products.modifications.enabled', 'checkbox')
                        ->label('Включить модификации')
                        ->default(false)
                        ->cast('bool')
                        ->tab('Основное')
                    );
                    $page->add(Field::make('store.products.modifications.mode', 'select')
                        ->label('Режим модификаций')
                        ->options(['vertical' => 'Вертикальные', 'flat' => 'Плоские'])
                        ->default('vertical')
                        ->cast('string')
                        ->tab('Расширенные')
                    );
                })
                ->page('Оплата', function ($page) {
                    $page->add(Field::make('store.payment.cod_enabled', 'toggle')
                        ->label('Наложенный платеж')
                        ->default(true)
                        ->cast('bool')
                    );
                });
        });
    }
}
```

Then reference it in `config/backpack-settings.php`:

```php
'registrars' => [
    App\Settings\StoreSettingsRegistrar::class,
],
```

> Alternatively, in your package service provider: resolve and call your registrar manually  
> (still keeps your settings definition in a dedicated class).

## Admin UI

Visit: `/admin/settings/store`

- The page is split into **tabs** by *pages* (Общее, Оплата).
- Inside a page, you can further segment fields by setting `->tab('...')`.

## Access values in code

```php
use Backpack\Settings\Facades\Settings;

if (Settings::get('store.products.modifications.enabled')) {
    // ...
}
```

## Add custom fields

Put blade views under `resources/views/vendor/backpack-settings/fields/yourtype.blade.php`
or ship them within this package's `resources/views/fields`.

Then, in your registrar, use `Field::make('key', 'yourtype')`.
```

## License

MIT
