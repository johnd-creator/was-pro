<?php

namespace App\Concerns;

trait WasteValidationRules
{
    /**
     * Get common validation rules for master data.
     */
    public function getMasterDataRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get validation rules for waste category.
     */
    public function getCategoryRules(): array
    {
        return array_merge($this->getMasterDataRules(), [
            'code' => ['required', 'string', 'max:50', 'unique:waste_categories,code'],
        ]);
    }

    /**
     * Get validation rules for waste characteristic.
     */
    public function getCharacteristicRules(): array
    {
        return array_merge($this->getMasterDataRules(), [
            'code' => ['required', 'string', 'max:50', 'unique:waste_characteristics,code'],
            'is_hazardous' => ['boolean'],
        ]);
    }

    /**
     * Get validation rules for waste type.
     */
    public function getWasteTypeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:waste_types,code'],
            'category_id' => ['required', 'uuid', 'exists:waste_categories,id'],
            'characteristic_id' => ['required', 'uuid', 'exists:waste_characteristics,id'],
            'description' => ['nullable', 'string'],
            'storage_period_days' => ['required', 'integer', 'min:0', 'max:36500'],
            'transport_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get validation rules for vendor.
     */
    public function getVendorRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:vendors,code'],
            'description' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'license_number' => ['nullable', 'string', 'max:100', 'unique:vendors,license_number'],
            'license_expiry_date' => ['nullable', 'date', 'after:today'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom error messages for validation.
     */
    public function getCustomMessages(): array
    {
        return [
            'code.unique' => 'The code has already been taken.',
            'category_id.required' => 'The category field is required.',
            'category_id.exists' => 'The selected category is invalid.',
            'characteristic_id.required' => 'The characteristic field is required.',
            'characteristic_id.exists' => 'The selected characteristic is invalid.',
            'storage_period_days.required' => 'The storage period field is required.',
            'storage_period_days.integer' => 'The storage period must be a whole number.',
            'storage_period_days.min' => 'The storage period must be at least 0 days.',
            'transport_cost.required' => 'The transport cost field is required.',
            'transport_cost.numeric' => 'The transport cost must be a number.',
            'transport_cost.min' => 'The transport cost must be at least 0.',
            'license_expiry_date.after' => 'The license expiry date must be after today.',
        ];
    }
}
