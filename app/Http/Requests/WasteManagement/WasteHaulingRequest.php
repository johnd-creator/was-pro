<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class WasteHaulingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $metadataRules = app(\App\Services\HaulingMetadata::class)->available() ? [
            'vendor_id' => ['nullable', 'uuid', \Illuminate\Validation\Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'receiver' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:5120'],
        ] : array_fill_keys([...\App\Services\HaulingMetadata::FIELDS, 'attachment'], ['prohibited']);

        return [
            ...$metadataRules,
            'waste_hauling_plan_id' => app(\App\Services\HaulingPlans::class)->enabled() ? ['nullable', 'uuid', \Illuminate\Validation\Rule::exists('waste_hauling_plans', 'id')->whereNull('deleted_at')] : ['prohibited'],
            'waste_record_id' => ['required', 'uuid', 'exists:waste_records,id'],
            'hauling_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', ...($this->filled('waste_hauling_plan_id') ? ['decimal:0,2'] : [])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'waste_record_id.required' => 'Catatan limbah wajib dipilih.',
            'waste_record_id.uuid' => 'Catatan limbah tidak valid.',
            'waste_record_id.exists' => 'Catatan limbah tidak ditemukan.',
            'hauling_date.required' => 'Tanggal angkut wajib diisi.',
            'hauling_date.date' => 'Tanggal angkut harus berupa tanggal yang valid.',
            'quantity.required' => 'Jumlah angkut wajib diisi.',
            'quantity.numeric' => 'Jumlah angkut harus berupa angka.',
            'quantity.min' => 'Jumlah angkut minimal 0,01.',
            'quantity.max' => 'Jumlah angkut melebihi batas yang diizinkan.',
            '*.prohibited' => 'Metadata pengangkutan belum tersedia. Hubungi administrator untuk aktivasi migrasi tenant.',
            'vendor_id.exists' => 'Vendor tidak ditemukan pada organisasi ini.',
            'vendor_id.uuid' => 'Vendor tidak valid.',
            'vehicle_number.max' => 'Nomor kendaraan maksimal 50 karakter.',
            'driver_name.max' => 'Nama pengemudi maksimal 255 karakter.',
            'document_number.max' => 'Nomor dokumen maksimal 255 karakter.',
            'destination.max' => 'Tujuan maksimal 255 karakter.',
            'receiver.max' => 'Penerima maksimal 255 karakter.',
            'attachment.mimetypes' => 'Bukti harus berupa JPG, PNG, WEBP atau PDF.',
            'attachment.max' => 'Bukti maksimal 5 MB.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
