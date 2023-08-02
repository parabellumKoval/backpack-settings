<?php

namespace Backpack\Settings\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      return [
		    'id' => $this->id,
		    'key' => $this->key,
		    'template' => $this->template,
		    'name' => $this->name,
		    'extras' => $this->extrasDecoded,
      ];
    }
}
