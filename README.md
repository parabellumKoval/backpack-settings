# Backpack-settings

[![Build Status](https://travis-ci.org/parabellumKoval/backpack-settings.svg?branch=master)](https://travis-ci.org/parabellumKoval/backpack-settings)
[![Coverage Status](https://coveralls.io/repos/github/parabellumKoval/backpack-settings/badge.svg?branch=master)](https://coveralls.io/github/parabellumKoval/backpack-settings?branch=master)

[![Packagist](https://img.shields.io/packagist/v/parabellumKoval/backpack-settings.svg)](https://packagist.org/packages/parabellumKoval/backpack-settings)
[![Packagist](https://poser.pugx.org/parabellumKoval/backpack-settings/d/total.svg)](https://packagist.org/packages/parabellumKoval/backpack-settings)
[![Packagist](https://img.shields.io/packagist/l/parabellumKoval/backpack-settings.svg)](https://packagist.org/packages/parabellumKoval/backpack-settings)

This package provides a quick starter kit for implementing settings for Laravel Backpack. Provides a database, CRUD interface, API routes and more.

## Installation

Install via composer
```bash
composer require parabellumKoval/backpack-settings
```

Migrate
```bash
php artisan migrate
```

### Publish

#### Configuration File
```bash
php artisan vendor:publish --provider="Backpack\Settings\ServiceProvider" --tag="config"
```

#### Translation Files
```bash
php artisan vendor:publish --provider="Backpack\Settings\ServiceProvider" --tag="trans"
```

#### Views File
```bash
php artisan vendor:publish --provider="Backpack\Settings\ServiceProvider" --tag="views"
```

#### Migrations File
```bash
php artisan vendor:publish --provider="Backpack\Settings\ServiceProvider" --tag="migrations"
```

#### Routes File
```bash
php artisan vendor:publish --provider="Backpack\Settings\ServiceProvider" --tag="routes"
```

#### Page templates Files
```bash
php artisan vendor:publish --provider="Backpack\Settings\ServiceProvider" --tag="temps"
```

#### Stub File
```bash
php artisan vendor:publish --provider="Backpack\Settings\ServiceProvider" --tag="stub"
```

## Usage

### Seeders
```bash
php artisan db:seed --class="Backpack\Settings\database\seeders\SettingsSeeder"
```

## Security

If you discover any security related issues, please email 
instead of using the issue tracker.

## Credits

- [parabellumKoval](https://github.com/parabellumKoval/backpack-settings)
- [All contributors](https://github.com/parabellumKoval/backpack-settings/graphs/contributors)
