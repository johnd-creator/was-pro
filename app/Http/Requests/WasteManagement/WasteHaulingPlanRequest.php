<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class WasteHaulingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(\App\Services\HaulingPlans::class)->enabled();
    }

    public function rules(): array
    {
        return ['waste_record_id' => ['required', 'uuid', 'exists:waste_records,id'], 'planned_date' => ['required', 'date'], 'quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'], 'notes' => ['nullable', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['waste_record_id.required' => 'Catatan limbah wajib dipilih.', 'quantity.required' => 'Jumlah rencana wajib diisi.', 'quantity.numeric' => 'Jumlah rencana harus berupa angka.', 'quantity.max' => 'Jumlah rencana melebihi batas yang diizinkan.', 'quantity.decimal' => 'Jumlah maksimal dua angka desimal.', 'quantity.min' => 'Jumlah minimal 0,01.', 'planned_date.required' => 'Tanggal rencana wajib diisi.'];
    }
}
