<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class FabaReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required_with:version', 'nullable', 'integer', 'between:1900,9999'],
            'month' => ['required_with:version', 'nullable', 'integer', 'between:1,12'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.required_with' => 'Tahun wajib dipilih untuk arsip closing.',
            'month.required_with' => 'Bulan wajib dipilih untuk arsip closing.',
            'year.integer' => 'Tahun harus berupa bilangan bulat.',
            'year.between' => 'Tahun tidak valid.',
            'month.integer' => 'Bulan harus berupa bilangan bulat.',
            'month.between' => 'Bulan harus antara 1 dan 12.',
            'version.integer' => 'Versi harus berupa bilangan bulat.',
            'version.min' => 'Versi minimal 1.',
        ];
    }
}
