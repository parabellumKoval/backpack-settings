<?php

namespace Backpack\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
        // return true;
    }

    public function rules(): array
    {
        return [
            // You may dynamically compose rules per registry group/page
        ];
    }
}
