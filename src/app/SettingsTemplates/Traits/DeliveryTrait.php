<?php

namespace App\SettingsTemplates\Traits;

trait DeliveryTrait
{
  private static function delivery($crud)
  {
      
    $crud->addField([
        'name' => 'seo_title',
        'label' => 'Заголовок',
        'fake' => true,
        'store_in' => 'extras',
        'tab' => 'Основное',
    ]);  
      
    $crud->addField([
        'name' => 'seo_text',
        'label' => 'Текст',
        'type' => 'ckeditor',
        'fake' => true,
        'store_in' => 'extras',
        'tab' => 'Основное',
    ]);  

  }
}