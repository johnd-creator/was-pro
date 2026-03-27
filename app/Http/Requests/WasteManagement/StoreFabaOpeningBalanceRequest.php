<?php

namespace App\Http\Requests\WasteManagement;

use App\Models\FabaMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFabaOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'material_type' => ['required', Rule::in(FabaMovement::materialOptions())],
            'quantity' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
