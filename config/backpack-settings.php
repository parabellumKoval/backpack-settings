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

    'regions' => [
        // 'us' => 'United States',
        // 'eu' => 'Europe',
    ],


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
