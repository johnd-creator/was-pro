<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationalAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('operational_audit.view') ?? false;
    }

    public function rules(): array
    {
        return ['module' => ['nullable', Rule::in(['waste_records', 'waste_haulings', 'waste_hauling_plans'])], 'action' => ['nullable', 'string', 'max:50'], 'reference_id' => ['nullable', 'uuid'], 'actor_id' => ['nullable', 'uuid'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000']];
    }

    public function messages(): array
    {
        return ['module.in' => 'Modul tidak valid.', 'reference_id.uuid' => 'ID catatan tidak valid.', 'actor_id.uuid' => 'ID pelaku tidak valid.', 'from.date_format' => 'Gunakan tanggal YYYY-MM-DD.', 'to.date_format' => 'Gunakan tanggal YYYY-MM-DD.', 'to.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal awal.'];
    }
}
