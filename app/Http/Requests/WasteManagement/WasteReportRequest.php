<?php

namespace App\Http\Requests\WasteManagement;

class WasteReportRequest extends WasteBalanceRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['year' => $this->input('year', today()->year)]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'year' => ['required', 'integer', 'between:2000,2100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_date' => ['nullable', 'date_format:Y-m-d'],
            'revision' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:100'],
            'manager_name' => ['nullable', 'string', 'max:150'],
            'environment_manager_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'year.between' => 'Tahun laporan harus antara 2000 dan 2100.',
            'year.integer' => 'Tahun laporan harus berupa angka.',
            'document_date.date_format' => 'Tanggal dokumen harus berformat YYYY-MM-DD.',
            '*.max' => 'Teks metadata laporan terlalu panjang.',
        ];
    }
}
