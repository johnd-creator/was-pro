<?php

namespace App\Http\Requests\WasteManagement;

use App\Models\FabaProductionEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FabaProductionEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'material_type' => ['required', Rule::in(FabaProductionEntry::materialOptions())],
            'entry_type' => [
                'required',
                Rule::in(FabaProductionEntry::entryTypeOptions()),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $materialType = (string) $this->input('material_type');

                    if ($materialType !== '' && ! FabaProductionEntry::isValidEntryTypeForMaterial($materialType, (string) $value)) {
                        $fail('Tipe entri tidak valid untuk material yang dipilih.');
                    }
                },
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', Rule::in([FabaProductionEntry::DEFAULT_UNIT])],
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
            'entry_type.required' => 'Tipe entri wajib dipilih.',
            'entry_type.in' => 'Tipe entri tidak valid.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.numeric' => 'Jumlah harus berupa angka.',
            'quantity.gt' => 'Jumlah harus lebih besar dari 0.',
            'unit.in' => 'Satuan transaksi harus ton.',
        ];
    }
}
