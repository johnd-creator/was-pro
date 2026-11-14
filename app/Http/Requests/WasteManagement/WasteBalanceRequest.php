<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class WasteBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'view' => $this->input('view', $this->has('start_date') || $this->has('end_date') ? 'range' : 'annual'),
            'year' => $this->input('year', today()->year),
            'start_date' => $this->input('start_date', today()->startOfMonth()->toDateString()),
            'end_date' => $this->input('end_date', today()->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'view' => ['required', 'in:annual,range'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'view.in' => 'Pilih tampilan tahunan atau rentang tanggal.',
            'year.required' => 'Tahun wajib diisi.',
            'year.integer' => 'Tahun harus berupa angka.',
            'year.between' => 'Tahun harus antara 2000 dan 2100.',
            'start_date.required' => 'Tanggal awal wajib diisi.',
            'start_date.date_format' => 'Tanggal awal harus berformat YYYY-MM-DD.',
            'end_date.required' => 'Tanggal akhir wajib diisi.',
            'end_date.date_format' => 'Tanggal akhir harus berformat YYYY-MM-DD.',
            'end_date.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }
}
