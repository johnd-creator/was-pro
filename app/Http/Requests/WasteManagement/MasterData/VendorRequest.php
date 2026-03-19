<?php

namespace App\Http\Requests\WasteManagement\MasterData;

use App\Concerns\WasteValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorRequest extends FormRequest
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
        $rules = $this->getVendorRules();

        // Handle unique validation for updates
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $vendorId = $this->route('vendor')?->id;
            $rules['code'] = ['required', 'string', 'max:50', Rule::unique('vendors')->ignore($vendorId)];
            $rules['license_number'] = ['nullable', 'string', 'max:100', Rule::unique('vendors')->ignore($vendorId)];
        } else {
            // Only validate license_expiry_date after today for new records
            $rules['license_expiry_date'] = ['nullable', 'date'];
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
