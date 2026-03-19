<?php

namespace App\Http\Requests\WasteManagement\MasterData;

use App\Concerns\WasteValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use WasteValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->getCategoryRules();

        // Handle unique validation for updates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $categoryId = $this->route('category')?->id;
            $rules['code'] = ['required', 'string', 'max:50', Rule::unique('waste_categories')->ignore($categoryId)];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->getCustomMessages();
    }
}
