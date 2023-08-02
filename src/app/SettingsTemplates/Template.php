<?php

namespace App\SettingsTemplates;

use App\SettingsTemplates\Traits\DeliveryTrait;

class Template {

  use DeliveryTrait;

  public static $templates = [
    'text' => 'Текст',
    'delivery' => 'Доставка'
  ];

  public static function useTemplate($name, $crud) {
    self::{$name}($crud);
  }

  private static function common($crud)
  {
      $crud->addField([
        'name' => 'content',
        'label' => trans('pages.content'),
        'type' => 'ckeditor',
        'placeholder' => trans('pages.content_placeholder'),
        'fake' => true,
        'store_in' => 'extras',
        'tab' => 'Основное',
      ]);
  }
}