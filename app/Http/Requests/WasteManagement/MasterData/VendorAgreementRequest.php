<?php

namespace App\Http\Requests\WasteManagement\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class VendorAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        abort_unless(Schema::hasTable('vendor_agreements'), 503, 'Fitur MoU menunggu penyiapan database.');
        $agreement = $this->route('agreement');
        $transporter = Rule::exists('vendors', 'id')->whereNull('deleted_at');
        $receiver = Rule::exists('vendors', 'id')->whereNull('deleted_at');
        if (! $agreement || $this->input('transporter_vendor_id') !== $agreement->transporter_vendor_id) {
            $transporter->where('is_active', true)->where('is_transporter', true);
        }
        if (! $agreement || $this->input('receiver_vendor_id') !== $agreement->receiver_vendor_id) {
            $receiver->where('is_active', true)->where(fn ($query) => $query->where('is_processor', true)->orWhere('is_utilizer', true));
        }

        return [
            'transporter_vendor_id' => ['required', 'uuid', $transporter],
            'receiver_vendor_id' => ['required', 'uuid', $receiver],
            'document_number' => ['required', 'string', 'max:150', Rule::unique('vendor_agreements')->where('transporter_vendor_id', $this->input('transporter_vendor_id'))->where('receiver_vendor_id', $this->input('receiver_vendor_id'))->where('waste_classification', $this->input('waste_classification'))->ignore($agreement?->id)],
            'valid_from' => ['nullable', 'date_format:Y-m-d', 'required_with:valid_until', 'required_if:document_status,recorded'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'required_with:valid_from', 'required_if:document_status,recorded', 'after_or_equal:valid_from'],
            'waste_classification' => ['required', Rule::in(['b3', 'non_b3'])],
            'document_status' => ['required', Rule::in(['draft', 'recorded', 'revoked'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Kolom ini wajib diisi.',
            '*.exists' => 'Vendor tidak tersedia di organisasi ini atau perannya belum sesuai.',
            'document_number.unique' => 'MoU dengan nomor, pihak, dan klasifikasi ini sudah tercatat.',
            'valid_until.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            '*.date_format' => 'Gunakan tanggal yang valid.',
            '*.required_if' => 'Lengkapi tanggal untuk status Tercatat.',
            '*.required_with' => 'Lengkapi pasangan tanggal masa berlaku.',
        ];
    }
}
