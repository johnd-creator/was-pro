<?php

namespace App\Http\Requests\WasteManagement;

use App\Models\FabaUtilizationEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FabaUtilizationEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExternal = $this->input('utilization_type') === FabaUtilizationEntry::TYPE_EXTERNAL;

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'material_type' => ['required', Rule::in(FabaUtilizationEntry::materialOptions())],
            'utilization_type' => ['required', Rule::in(FabaUtilizationEntry::utilizationTypeOptions())],
            'vendor_id' => [
                Rule::requiredIf($isExternal),
                'nullable',
                'uuid',
                'exists:vendors,id',
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', Rule::in([FabaUtilizationEntry::DEFAULT_UNIT])],
            'document_number' => [Rule::requiredIf($isExternal), 'nullable', 'string', 'max:255'],
            'document_date' => [Rule::requiredIf($isExternal), 'nullable', 'date', 'before_or_equal:today'],
            'attachment' => ['nullable', 'file', 'max:5120'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_id.required' => 'Vendor wajib diisi untuk pemanfaatan eksternal.',
            'vendor_id.exists' => 'Vendor yang dipilih tidak valid.',
            'quantity.gt' => 'Jumlah harus lebih besar dari 0.',
            'document_number.required' => 'Nomor dokumen wajib diisi untuk pemanfaatan eksternal.',
            'document_date.required' => 'Tanggal dokumen wajib diisi untuk pemanfaatan eksternal.',
            'document_date.before_or_equal' => 'Tanggal dokumen tidak boleh melebihi hari ini.',
            'unit.in' => 'Satuan transaksi harus ton.',
        ];
    }
}
