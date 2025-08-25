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

    // Валидация мы храним здесь (для последующей сборки правил при save)
    public array $rules = [];

    // HTML-атрибуты поля (aria-*, data-*, class и т.п.)
    public array $attributes = [];

    /**
     * Мешок произвольных свойств Backpack-поля (entity, model, attribute, options, placeholder, allows_null, inline_create, wrapper, etc.)
     */
    public array $props = [];

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

    // Сахар для options — кладём прямо в props и НИЧЕГО не фильтруем по type
    public function options(array $options): self { $this->props['options'] = $options; return $this; }

    /**
     * Магия: любой неизвестный чейн превращаем в ключ Backpack-поля.
     * Пример: ->inlineCreate(true) => ['inline_create' => true]
     *         ->allows_null(true)  => ['allows_null' => true]
     *         ->entity('category') => ['entity' => 'category']
     */
    public function __call($name, $arguments): self
    {
        // конвертируем camelCase → snake_case, чтобы попадать в стиль Backpack
        $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

        // Резервированные ключи, которые мы заполняем сами
        $reserved = ['name', 'type', 'label', 'value', 'tab'];
        if (in_array($key, $reserved, true)) {
            return $this; // игнор, чтобы не перезатереть базовые поля
        }

        $value = $arguments[0] ?? true; // вызов без аргументов трактуем как true-флаг
        $this->props[$key] = $value;
        return $this;
    }

    public function toBackpackArray($value = null): array
    {
        // Базовый каркас поля
        $arr = [
            'name'       => 'settings['.$this->key.']',
            'label'      => $this->label ?: $this->key,
            'type'       => $this->type,
            'value'      => $value ?? $this->default,
            'attributes' => $this->attributes,
            // дефолтный wrapper — Backpack сам отрисует обёртку
            'wrapper'    => ['class' => 'form-group col-sm-12'],
        ];

        if ($this->tab) {
            $arr['tab'] = $this->tab;
        }

        // Прозрачно добавляем ВСЕ произвольные свойства
        // (entity, model, attribute, options, allows_null, placeholder, inline_create, wrapper и т.д.)
        foreach ($this->props as $k => $v) {
            // заодно позволяем переопределить wrapper, если он задан в props
            if ($k === 'wrapper' && is_array($v)) {
                $arr['wrapper'] = $v + $arr['wrapper'];
            } else {
                $arr[$k] = $v;
            }
        }

        return $arr;
    }
}
