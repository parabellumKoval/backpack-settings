<?php

use Illuminate\Support\Facades\Route;
use Backpack\Settings\Http\Controllers\Api\SettingsApiController;

Route::group([
    'prefix'     => 'api/settings',
    'middleware' => ['api'], // при необходимости: ['api','auth:api'] или sanctum
], function () {
    // Все записи из БД (простая выдача)
    Route::get('/', [SettingsApiController::class, 'db'])->name('backpack.settings.api.db');
});
