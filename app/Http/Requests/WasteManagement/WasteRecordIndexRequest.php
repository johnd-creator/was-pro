<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WasteRecordIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'attention' => ['nullable', Rule::in(['all', 'expired', 'expiring_soon', 'critical', 'backlog', 'pending', 'fresh', 'completed'])],
            'expiry' => ['nullable', Rule::in(['expired'])],
            'as_of' => ['required_with:expiry', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'unit' => ['nullable', 'string', 'max:30'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'attention.in' => 'Filter perhatian tidak valid.',
            'month.date_format' => 'Bulan harus berformat YYYY-MM.',
            'expiry.in' => 'Filter masa simpan tidak valid.',
            'as_of.required_with' => 'Tanggal snapshot wajib diisi.',
            'as_of.date_format' => 'Tanggal snapshot harus berformat YYYY-MM-DD.',
            'as_of.before_or_equal' => 'Tanggal snapshot tidak boleh melewati hari ini.',
            'unit.max' => 'Satuan maksimal 30 karakter.',
            'organization_id.exists' => 'Organisasi tidak ditemukan.',
        ];
    }
}
