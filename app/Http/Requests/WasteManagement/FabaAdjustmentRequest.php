<?php

namespace App\Http\Requests\WasteManagement;

use App\Models\FabaMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FabaAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'material_type' => ['required', Rule::in(FabaMovement::materialOptions())],
            'movement_type' => ['required', Rule::in([
                FabaMovement::TYPE_ADJUSTMENT_IN,
                FabaMovement::TYPE_ADJUSTMENT_OUT,
            ])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', Rule::in([FabaMovement::DEFAULT_UNIT])],
            'note' => ['required', 'string'],
        ];
    }
}
