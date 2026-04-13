<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class WasteHaulingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'waste_record_id' => ['required', 'uuid', 'exists:waste_records,id'],
            'hauling_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'waste_record_id.required' => 'Catatan limbah wajib dipilih.',
            'waste_record_id.uuid' => 'Catatan limbah tidak valid.',
            'waste_record_id.exists' => 'Catatan limbah tidak ditemukan.',
            'hauling_date.required' => 'Tanggal angkut wajib diisi.',
            'hauling_date.date' => 'Tanggal angkut harus berupa tanggal yang valid.',
            'quantity.required' => 'Jumlah angkut wajib diisi.',
            'quantity.numeric' => 'Jumlah angkut harus berupa angka.',
            'quantity.min' => 'Jumlah angkut minimal 0,01.',
            'quantity.max' => 'Jumlah angkut melebihi batas yang diizinkan.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
