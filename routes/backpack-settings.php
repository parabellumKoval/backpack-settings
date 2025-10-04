<?php

use Illuminate\Support\Facades\Route;
use Backpack\Settings\Http\Controllers\Admin\SettingsController;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin').'/'.config('backpack-settings.route_prefix', 'settings'),
    'middleware' => config('backpack-settings.middleware', ['web','admin']),
], function () {
    Route::get('{group}', [SettingsController::class, 'edit'])->name('backpack.settings.edit');
    Route::post('{group}', [SettingsController::class, 'update'])->name('backpack.settings.update');
});
