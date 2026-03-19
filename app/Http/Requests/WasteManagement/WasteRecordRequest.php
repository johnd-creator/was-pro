<?php

namespace App\Http\Requests\WasteManagement;

use App\Concerns\WasteValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class WasteRecordRequest extends FormRequest
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
        $rules = [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'waste_type_id' => ['required', 'uuid', 'exists:waste_types,id'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'unit' => ['required', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];

        // For updates, allow changing certain fields only in draft/rejected status
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $record = $this->route('wasteRecord');

            if ($record && ! $record->canBeEdited()) {
                // If record is not editable, only allow notes
                $rules = [
                    'notes' => ['nullable', 'string'],
                ];
            }
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
        return [
            'date.required' => 'Tanggal pencatatan wajib diisi.',
            'date.date' => 'Tanggal pencatatan harus berupa tanggal yang valid.',
            'date.before_or_equal' => 'Tanggal pencatatan tidak boleh melebihi hari ini.',
            'waste_type_id.required' => 'Jenis limbah wajib dipilih.',
            'waste_type_id.uuid' => 'Jenis limbah tidak valid.',
            'waste_type_id.exists' => 'Jenis limbah yang dipilih tidak tersedia.',
            'quantity.required' => 'Jumlah limbah wajib diisi.',
            'quantity.numeric' => 'Jumlah limbah harus berupa angka.',
            'quantity.min' => 'Jumlah limbah minimal 0.',
            'quantity.max' => 'Jumlah limbah melebihi batas yang diizinkan.',
            'unit.required' => 'Satuan wajib dipilih.',
            'unit.max' => 'Satuan terlalu panjang.',
            'source.max' => 'Sumber atau lokasi maksimal 255 karakter.',
        ];
    }
}
