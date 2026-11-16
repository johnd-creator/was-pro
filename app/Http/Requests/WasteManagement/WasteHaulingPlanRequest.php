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
        $quantity = ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'];

        return [
            'planned_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            ...($this->has('items') ? [
                'items' => ['required', 'array', 'list', 'min:1', 'max:50'],
                'items.*' => ['required', 'array:waste_record_id,quantity'],
                'items.*.waste_record_id' => ['required', 'uuid', 'distinct:ignore_case', 'exists:waste_records,id'],
                'items.*.quantity' => $quantity,
                'waste_record_id' => ['prohibited'],
                'quantity' => ['prohibited'],
            ] : [
                'waste_record_id' => ['required', 'uuid', 'exists:waste_records,id'],
                'quantity' => $quantity,
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'waste_record_id.required' => 'Catatan limbah wajib dipilih.',
            'quantity.required' => 'Jumlah rencana wajib diisi.',
            'quantity.numeric' => 'Jumlah rencana harus berupa angka.',
            'quantity.max' => 'Jumlah rencana melebihi batas yang diizinkan.',
            'quantity.decimal' => 'Jumlah maksimal dua angka desimal.',
            'quantity.min' => 'Jumlah minimal 0,01.',
            'planned_date.required' => 'Tanggal rencana wajib diisi.',
            'items.required' => 'Pilih setidaknya satu batch catatan limbah.',
            'items.array' => 'Daftar batch tidak valid.',
            'items.list' => 'Urutan batch tidak valid.',
            'items.min' => 'Pilih setidaknya satu batch catatan limbah.',
            'items.max' => 'Maksimal 50 batch dalam satu pengajuan.',
            'items.*.array' => 'Data batch hanya boleh berisi catatan limbah dan jumlah.',
            'items.*.waste_record_id.required' => 'Batch catatan limbah wajib dipilih.',
            'items.*.waste_record_id.uuid' => 'Identitas batch tidak valid.',
            'items.*.waste_record_id.exists' => 'Batch tidak tersedia pada organisasi ini.',
            'items.*.waste_record_id.distinct' => 'Batch yang sama tidak boleh dipilih dua kali.',
            'items.*.quantity.required' => 'Jumlah rencana batch ini wajib diisi.',
            'items.*.quantity.numeric' => 'Jumlah rencana batch ini harus berupa angka.',
            'items.*.quantity.max' => 'Jumlah rencana batch ini melebihi batas yang diizinkan.',
            'items.*.quantity.decimal' => 'Jumlah rencana batch ini maksimal dua angka desimal.',
            'items.*.quantity.min' => 'Jumlah rencana batch ini minimal 0,01.',
            'waste_record_id.prohibited' => 'Gunakan daftar batch saja untuk pengajuan beberapa batch.',
            'quantity.prohibited' => 'Isi jumlah pada masing-masing batch, bukan jumlah gabungan.',
        ];
    }
}
