<?php

namespace App\Http\Requests\WasteManagement;

use App\Models\FabaMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FabaProductionMovementRequest extends FormRequest
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
            'movement_type' => [
                'required',
                Rule::in(FabaMovement::productionTypeOptions()),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $materialType = (string) $this->input('material_type');

                    if ($materialType !== '' && ! FabaMovement::isValidProductionTypeForMaterial($materialType, (string) $value)) {
                        $fail('Tipe entri tidak valid untuk material yang dipilih.');
                    }
                },
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', Rule::in([FabaMovement::DEFAULT_UNIT])],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'transaction_date.before_or_equal' => 'Tanggal transaksi tidak boleh melebihi hari ini.',
            'material_type.required' => 'Material wajib dipilih.',
            'material_type.in' => 'Material tidak valid.',
            'movement_type.required' => 'Tipe movement wajib dipilih.',
            'movement_type.in' => 'Tipe movement tidak valid.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.numeric' => 'Jumlah harus berupa angka.',
            'quantity.gt' => 'Jumlah harus lebih besar dari 0.',
            'unit.in' => 'Satuan transaksi harus ton.',
        ];
    }
}
