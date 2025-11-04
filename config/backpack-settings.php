<?php

return [
    // The DB table for settings
    'table' => 'ak_settings',

    // Cache settings
    'cache' => [
        'enabled' => true,
        'ttl' => 0, // 0 = forever
        'store' => null, // null => default
    ],

    // Driver priority (first found wins). Supported: database, config
    'drivers' => ['database', 'config'],

    // Registrars: list of classes implementing SettingsRegistrarInterface
    'registrars' => [
        // Example: \Vendor\Package\Settings\StoreSettingsRegistrar::class,
    ],

    // Access control for the admin UI routes
    'middleware' => ['web', 'admin'], // add your own if needed

    // Route prefix inside /admin
    'route_prefix' => 'settings',

    // Blade view namespace
    'view_namespace' => 'backpack-settings',

    // Group/page titles fallback (if not provided by registrar)
    'titles' => [
        'default_group' => 'Settings',
        'default_page'  => 'General',
    ],

    // Context-aware settings -------------------------------------------------

    // Automatically attempt to resolve locale from the current application locale
    // when no explicit locale was provided to Settings::get()
    'auto_locale' => true,

    // Explicit list of locales for translatable fields (null => pull keys from backpack.crud.locales)
    'available_locales' => null,

    // List of available regions for regionable fields (code => human readable label)
    'available_regions' => [
        // 'de' => 'Germany',
        // 'us' => 'United States',
    ],

    // Query parameter names for resolving context from requests (admin UI & API)
    'region_query_parameter' => 'country',
    'locale_query_parameter' => 'locale',


    // Алиасы в формате: канон => [алиасы...]
    'aliases' => [
        // 'store.products.modifications.enabled' => ['store.products_modifications_enabled', 'backpack-store.products_modifications_enabled'],
    ],

    // Алиасы, привнесённые внешними пакетами.
    // Формат: 'vendor/package' => [ канон => [алиасы...] ]
    'aliases_packages' => [
        // 'vendor/package' => [
        //     'backpack.store.catalog_table_cache' => ['bs.catalog_table_cache'],
        // ],
    ],
];
