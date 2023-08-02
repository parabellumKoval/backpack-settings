<?php

namespace Backpack\Settings\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;

use Backpack\Settings\app\Models\Settings;

class SettingsController extends \App\Http\Controllers\Controller
{ 

  protected $resource;

  function __construct() {
    $this->resource = config('parabellumkoval.settings.resource', 'Backpack\Settings\app\Http\Resources\SettingsResource');
  }

  public function show(Request $request, $template) {
    try{
      $setting = Settings::where('template', $template)
                    ->firstOrFail();

    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return new $this->resource($setting);
  }

  public function all(Request $request) {
    try{
      $settings = Settings::all();
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return $this->resource::collection($settings);
  }

}
