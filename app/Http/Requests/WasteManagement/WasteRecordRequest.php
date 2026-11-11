<?php

namespace App\Http\Requests\WasteManagement;

use App\Concerns\WasteValidationRules;
use App\Models\WasteRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $record = null;
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $routeRecord = $this->route('wasteRecord');
            $recordId = $routeRecord instanceof WasteRecord ? $routeRecord->getKey() : $routeRecord;
            if (is_string($recordId) && $recordId !== '') {
                $record = WasteRecord::query()->findOrFail($recordId);
            }
        }

        $wasteTypeRule = Rule::exists('waste_types', 'id')->whereNull('deleted_at');
        if (! $record || (string) $record->waste_type_id !== (string) $this->input('waste_type_id')) {
            $wasteTypeRule->where('is_active', true);
        }

        $rules = [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'waste_type_id' => ['required', 'uuid', $wasteTypeRule],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'unit' => ['required', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];

        // For updates, allow changing certain fields only in draft/rejected status
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
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
