<?php

namespace Backpack\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Setting extends Model
{
    use CrudTrait;
    
    protected $table;
    protected $fillable = ['key','value','cast','group','region','locale','updated_by'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('backpack-settings.table', 'backpack_settings');
    }
}
