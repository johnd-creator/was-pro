<?php

namespace App\Http\Requests\WasteManagement;

use App\Models\FabaMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFabaTpsCapacityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_type' => ['required', Rule::in(FabaMovement::materialOptions())],
            'capacity' => ['required', 'numeric', 'gt:0'],
            'warning_threshold' => ['required', 'numeric', 'gt:0', 'lt:100'],
            'critical_threshold' => ['required', 'numeric', 'gt:0', 'lte:100', 'gte:warning_threshold'],
        ];
    }

    public function messages(): array
    {
        return [
            'material_type.required' => 'Material TPS wajib dipilih.',
            'material_type.in' => 'Material TPS tidak valid.',
            'capacity.required' => 'Kapasitas TPS wajib diisi.',
            'capacity.gt' => 'Kapasitas TPS harus lebih besar dari 0.',
            'warning_threshold.required' => 'Ambang warning wajib diisi.',
            'warning_threshold.lt' => 'Ambang warning harus di bawah 100%.',
            'critical_threshold.required' => 'Ambang critical wajib diisi.',
            'critical_threshold.lte' => 'Ambang critical tidak boleh lebih dari 100%.',
            'critical_threshold.gte' => 'Ambang critical harus lebih besar atau sama dengan ambang warning.',
        ];
    }
}
