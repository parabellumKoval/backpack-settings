<?php

namespace Backpack\Settings\Services\Registry;

class Field
{
    public string $key;
    public string $type;
    public string $label = '';
    public $default = null;
    public ?string $cast = null;
    public ?string $tab = null;

    public array $rules = [];
    public array $attributes = [];

    public bool $translatable = false;
    public bool $regionable = false;

    // всё, что Backpack-полю можно передать напрямую (entity, model, options, placeholder, …)
    public array $props = [];

    // АЛИАСЫ ДЛЯ ЭТОГО КАНОНА
    public array $aliases = [];

    public static function make(string $key, string $type): self
    {
        $f = new self();
        $f->key = $key;
        $f->type = $type;
        return $f;
    }

    // Явные «часто используемые» чейны — оставим как сахар:
    public function label(string $label): self { $this->label = $label; return $this; }
    public function default($value): self { $this->default = $value; return $this; }
    public function cast(?string $cast): self { $this->cast = $cast; return $this; }
    public function tab(?string $tab): self { $this->tab = $tab; return $this; }
    public function rules($rules): self { $this->rules = is_array($rules) ? $rules : [$rules]; return $this; }
    public function attrs(array $attrs): self { $this->attributes = $attrs + $this->attributes; return $this; }

    public function translatable(bool $state = true): self
    {
        $this->translatable = $state;
        return $this;
    }

    public function regionable(bool $state = true): self
    {
        $this->regionable = $state;
        return $this;
    }

    // сахар
    public function options(array $options): self { $this->props['options'] = $options; return $this; }

    // НОВОЕ
    public function aliases(array $aliases): self
    {
        $this->aliases = $aliases;
        return $this;
    }

    // «проходной» приём неизвестных чейнов → snake_case ключи в props
    public function __call($name, $arguments): self
    {
        $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        if (in_array($key, ['name','type','label','value','tab'], true)) return $this;
        $this->props[$key] = $arguments[0] ?? true;
        return $this;
    }

    public function toBackpackArray($value = null, array $context = []): array
    {
        $region = $context['region'] ?? null;

        if ($value === null) {
            $value = $this->default;
        }

        if ($this->regionable) {
            if (is_array($value) && $region !== null && $region !== '') {
                $value = $value[$region] ?? ($this->translatable ? [] : null);
            } elseif ($region === null || $region === '') {
                $value = $this->translatable ? [] : null;
            }
        }

        if ($this->translatable) {
            if (!is_array($value)) {
                $value = $value === null ? [] : (array) $value;
            }
        }

        $name = 'settings['.$this->key.']';
        if ($this->regionable && $region !== null && $region !== '') {
            $name .= '['.$region.']';
        }

        $errorKey = 'settings.'.$this->key;
        if ($this->regionable && $region !== null && $region !== '') {
            $errorKey .= '.'.$region;
        }

        $arr = [
            'name'       => $name,
            'label'      => $this->label ?: $this->key,
            'type'       => $this->type,
            'value'      => $value ?? $this->default,
            'attributes' => $this->attributes,
            'wrapper'    => ['class' => 'form-group col-sm-12'],
            'translatable' => $this->translatable,
            'regionable' => $this->regionable,
            'error_key' => $errorKey,
            'settings_key' => $this->key,
        ];
        if ($this->tab) $arr['tab'] = $this->tab;

        foreach ($this->props as $k => $v) {
            if ($k === 'wrapper' && is_array($v)) {
                $arr['wrapper'] = $v + $arr['wrapper'];
            } else {
                $arr[$k] = $v;
            }
        }

        if ($this->regionable && ($region === null || $region === '')) {
            $arr['attributes'] = ['disabled' => 'disabled'] + $arr['attributes'];
        }

        return $arr;
    }
}
