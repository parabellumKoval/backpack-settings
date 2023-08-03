<?php

namespace Backpack\Settings\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

use Str;

class Settings extends Model
{
    use CrudTrait;
    use HasTranslations;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_settings';
    protected $primaryKey = 'id';
    public $timestamps = true;
    // protected $guarded = ['id'];
    protected $fillable = ['template', 'key', 'name', 'extras'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $fakeColumns = ['extras'];
    // protected $casts = [
    //     'extras' => 'array',
    // ];

    protected $translatable = ['extras', 'name'];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function getTemplateName()
    {
        return str_replace('_', ' ', Str::title($this->template));
    }

    private function isJson($string) {
      json_decode($string);
      return json_last_error() === JSON_ERROR_NONE;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeActive($query)
    {
      return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESORS
    |--------------------------------------------------------------------------
    */

    public function getExtrasDecodedAttribute() {
      if(empty($this->extras))
        return null;
      
      $data = json_decode($this->extras, true);
      return $data;      
    }

    public function getExtrasNormalizedAttribute() {
      if(empty($this->extrasDecoded))
        return null;
      
      
      return array_map(function($item) {
        if($this->isJson($item)) {
          return json_decode($item, true);
        }else {
          return $item;
        }
      }, $this->extrasDecoded);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
