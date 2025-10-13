<?php

namespace Backpack\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Translatable\HasTranslations;

class Setting extends Model
{
    use CrudTrait;
    use HasTranslations {
        isTranslatableAttribute as protected baseIsTranslatableAttribute;
        setAttribute as protected setTranslatedAttribute;
    }

    protected $table;
    protected $fillable = ['key','value','cast','group','region','is_translatable','updated_by'];
    protected $casts = [
        'is_translatable' => 'bool',
    ];

    public $translatable = ['value'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('backpack-settings.table', 'backpack_settings');
    }

    public function isTranslatableAttribute(string $key): bool
    {
        if (! $this->baseIsTranslatableAttribute($key)) {
            return false;
        }

        $flag = $this->attributes['is_translatable'] ?? $this->getAttributeFromArray('is_translatable') ?? false;

        return (bool) $flag;
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'value' && ! $this->isTranslatableAttribute($key)) {
            $this->attributes[$key] = $this->normalizeScalarValue($value);

            return $this;
        }

        return $this->setTranslatedAttribute($key, $value);
    }

    protected function normalizeScalarValue($value)
    {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
