<?php

namespace App\Http\Controllers\Admin\Crud;

use Backpack\Settings\app\Interfaces\SettingsCrudInterface;

class SettingsCrudExtended implements SettingsCrudInterface {
  
  // Extends of SetupCreateOperation
  public static function setupCreateOperation($crud) {}

  // Extends of setupUpdateOperation
  public static function setupUpdateOperation($crud){}

  // Extends of setupListOperation
  public static function setupListOperation($crud){}

}