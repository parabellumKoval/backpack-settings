<?php

namespace Backpack\Settings\app\Http\Controllers\Admin;

use Backpack\Settings\app\Http\Requests\SettingsRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;


use App\Http\Controllers\Admin\Crud\SettingsCrud;
use App\SettingsTemplates\Template;
use Backpack\Settings\app\Interfaces\SettingsCrudInterface;
// use SettingsCrud;

use Str;
/**
 * Class BannerCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class SettingsCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    
    public function setup()
    {
        $this->crud->setModel('Backpack\Settings\app\Models\Settings');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/settings');
        $this->crud->setEntityNameStrings('Настройка', 'Настройки');
    }

    protected function setupListOperation()
    {   
      $this->crud->addColumn([
          'name' => 'key',
          'label' => trans('parabellumkoval::settings.key'),
      ]);

      $this->crud->addColumn([
          'name' => 'name',
          'label' => trans('parabellumkoval::settings.name'),
      ]);
      
      $this->crud->addColumn([
          'name' => 'template',
          'label' => trans('parabellumkoval::settings.template'),
          'type' => 'model_function',
          'function_name' => 'getTemplateName',
      ]);

      if($this->isSettingsCrudClass()){
        SettingsCrud::setupListOperation($this->crud);
      }
    }

    protected function setupCreateOperation()
    {
    }

    protected function setupUpdateOperation()
    {
      // if the template in the GET parameter is missing, figure it out from the db
      $template = $this->crud->getCurrentEntry()->template ?? 'common';

      $this->addDefaultSettingsFields($template);
      $this->useTemplate($template);

      $this->crud->setValidation(SettingsRequest::class);

      if($this->isSettingsCrudClass()){
        SettingsCrud::setupUpdateOperation($this->crud);
      }
    }

    // -----------------------------------------------
    // Methods that are particular to the SettingsManager.
    // -----------------------------------------------

    /**
     * Populate the create/update forms with basic fields, that all settings need.
     *
     * @param  string  $template  The name of the template that should be used in the current form.
     */
    public function addDefaultSettingsFields($template = false)
    {

      $this->crud->addField([
        'name' => 'template',
        'label' => trans('parabellumkoval::settings.template'),
        'type' => 'text',
        'attributes' => [
          'readonly' => true
        ]
      ]);

      $this->crud->addField([
        'name' => 'key',
        'label' => trans('parabellumkoval::settings.key'),
        'type' => 'text',
        'attributes' => [
          'readonly' => true
        ]
      ]);
      
      $this->crud->addField([
        'name' => 'name',
        'label' => trans('parabellumkoval::settings.name'),
        'type' => 'text',
      ]);
    }

    /**
     * Add the fields defined for a specific template.
     *
     * @param  string  $template_name  The name of the template that should be used in the current form.
     */
    public function useTemplate($template_name = false)
    {
      if($this->isTemplatesClass()){
        Template::useTemplate($template_name, $this->crud);
      }
    }

    public function isTemplatesClass() {
      return file_exists(base_path('/app/SettingsTemplates/Template.php')) && 
              class_exists('App\SettingsTemplates\Template');
    }

    public function isSettingsCrudClass() {
      return file_exists(base_path('/app/Http/Controllers/Admin/Crud/SettingsCrud.php')) && 
              class_exists('App\Http\Controllers\Admin\Crud\SettingsCrud') && 
              isset(class_implements(new SettingsCrud)['Backpack\Settings\app\Interfaces\SettingsCrudInterface']);
    }
}
