<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WasteRecordExportRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:1000'],
            'storage_status' => ['nullable', Rule::in(['all', 'expired', 'expiring_soon', 'fresh', 'completed'])],
            'storage_date' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['latest', 'oldest', 'quantity_high', 'quantity_low'])],
        ];
    }

    public function messages(): array
    {
        return [
            'attention.in' => 'Filter perhatian tidak valid.',
            'month.date_format' => 'Bulan harus berformat YYYY-MM.',
            'search.string' => 'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 1000 karakter.',
            'storage_status.in' => 'Filter masa simpan tidak valid.',
            'storage_date.date_format' => 'Tanggal masa simpan harus berformat YYYY-MM-DD.',
            'sort.in' => 'Urutan ekspor tidak valid.',
        ];
    }
}
