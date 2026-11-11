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
            'quantity' => ['required', 'numeric', 'gt:0', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_numeric($value) && round((float) $value, 2) <= 0) {
                    $fail('Jumlah setelah pembulatan dua desimal harus lebih besar dari 0.');
                }
            }],
            'unit' => ['nullable', Rule::in([FabaMovement::DEFAULT_UNIT])],
            'note' => ['required', 'string'],
            'source_movement_id' => ['nullable', 'uuid'],
            'reference_type' => ['prohibited'],
            'reference_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Alasan koreksi wajib diisi.',
            'source_movement_id.uuid' => 'ID transaksi asal tidak valid.',
            'reference_type.prohibited' => 'Jenis referensi ditentukan oleh sistem.',
            'reference_id.prohibited' => 'Gunakan pilihan transaksi asal.',
        ];
    }
}
