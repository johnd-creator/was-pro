<?php

namespace App\Http\Requests\WasteManagement\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(\App\Models\WasteSource::available(), 409);

        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('waste_sources', 'name')->ignore($this->route('source'))], 'is_active' => ['required', 'boolean']];
    }
}
