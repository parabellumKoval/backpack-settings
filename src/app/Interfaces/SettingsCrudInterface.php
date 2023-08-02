<?php
namespace Backpack\Settings\app\Interfaces;

interface SettingsCrudInterface
{
  public static function setupCreateOperation($crud);
  public static function setupUpdateOperation($crud);
  public static function setupListOperation($crud);
}