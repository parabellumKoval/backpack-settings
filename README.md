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

## Regional & translatable settings

- Install the translations helper config if you need to customise locales resolution:

  ```bash
  php artisan vendor:publish --provider="Spatie\\Translatable\\TranslatableServiceProvider" --tag=translatable-config
  ```

- Settings stored in the database now support an optional `region` scope (`null` keeps the legacy "global" value). When reading a key with a region specified, the database driver will fall back to the global entry if the regional one is missing.
- Mark a record as translatable by setting its `is_translatable` flag (registrars can pass it through `$meta['is_translatable']`). Translatable values are stored as locale=>value JSON maps and transparently resolved for the current app locale.

## Migrating existing data

1. Run the new migration to introduce the `region`, `is_translatable` columns and composite unique index. All legacy rows will automatically end up with `region = null`.
2. For projects that already keep per-locale rows, consolidate them into a single JSON payload:

   ```php
   use Backpack\Settings\Models\Setting;

   Setting::query()
       ->where('is_translatable', true)
       ->whereNull('region')
       ->each(function (Setting $setting) {
           $locale = config('app.fallback_locale');
           $setting->value = [
               $locale => $setting->getRawOriginal('value'),
           ];
           $setting->save();
       });
   ```

   When multiple rows currently represent the same logical key for different locales, merge them manually into a single record and set `is_translatable = true` before saving the JSON payload.
3. If you have per-region overrides today, copy them into new records with the corresponding `region` value; global defaults stay at `region = null`.

## Add custom fields

Put blade views under `resources/views/vendor/backpack-settings/fields/yourtype.blade.php`
or ship them within this package's `resources/views/fields`.

Then, in your registrar, use `Field::make('key', 'yourtype')`.
```

## License

MIT
