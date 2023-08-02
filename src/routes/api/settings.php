<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Backpack\Settings\app\Http\Controllers\Api\SettingsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('api/settings')->controller(SettingsController::class)->group(function () {
  Route::get('{template}', 'show')->middleware('api');
  Route::get('', 'all')->middleware('api');
});
