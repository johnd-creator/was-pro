<?php

namespace App\Http\Requests\WasteManagement;

use App\Models\FabaMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class FabaUtilizationMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExternal = $this->input('movement_type') === FabaMovement::TYPE_UTILIZATION_EXTERNAL;
        $requiresAttachment = $isExternal && ! $this->hasExistingAttachment();

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'material_type' => ['required', Rule::in(FabaMovement::materialOptions())],
            'movement_type' => ['required', Rule::in([
                FabaMovement::TYPE_UTILIZATION_EXTERNAL,
                FabaMovement::TYPE_UTILIZATION_INTERNAL,
            ])],
            'vendor_id' => [
                Rule::requiredIf($isExternal),
                'nullable',
                'uuid',
                $this->masterRule('vendors', 'vendor_id', true),
            ],
            'internal_destination_id' => [
                Rule::requiredIf(! $isExternal),
                'nullable',
                'uuid',
                $this->masterRule('faba_internal_destinations', 'internal_destination_id'),
            ],
            'purpose_id' => [
                'nullable',
                'uuid',
                $this->masterRule('faba_purposes', 'purpose_id'),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_numeric($value) && round((float) $value, 2) <= 0) {
                    $fail('Jumlah setelah pembulatan dua desimal harus lebih besar dari 0.');
                }
            }],
            'unit' => ['nullable', Rule::in([FabaMovement::DEFAULT_UNIT])],
            'document_number' => [Rule::requiredIf($isExternal), 'nullable', 'string', 'max:255'],
            'document_date' => [Rule::requiredIf($isExternal), 'nullable', 'date', 'before_or_equal:today'],
            'attachment' => [Rule::requiredIf($requiresAttachment), 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:5120'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'vendor_id.required' => 'Vendor wajib diisi untuk pemanfaatan eksternal.',
            'vendor_id.exists' => 'Vendor yang dipilih tidak valid.',
            'internal_destination_id.required' => 'Tujuan internal wajib diisi untuk pemanfaatan internal.',
            'internal_destination_id.exists' => 'Tujuan internal yang dipilih tidak valid.',
            'movement_type.required' => 'Tipe movement wajib dipilih.',
            'movement_type.in' => 'Tipe movement pemanfaatan tidak valid.',
            'purpose_id.exists' => 'Use-case yang dipilih tidak valid.',
            'quantity.gt' => 'Jumlah harus lebih besar dari 0.',
            'document_number.required' => 'Nomor dokumen wajib diisi untuk pemanfaatan eksternal.',
            'document_date.required' => 'Tanggal dokumen wajib diisi untuk pemanfaatan eksternal.',
            'document_date.before_or_equal' => 'Tanggal dokumen tidak boleh melebihi hari ini.',
            'attachment.required' => 'Lampiran wajib diunggah untuk pemanfaatan eksternal.',
            'attachment.mimetypes' => 'Lampiran harus berupa foto JPG/PNG/WEBP atau PDF.',
            'unit.in' => 'Satuan transaksi harus ton.',
        ];
    }

    protected function masterRule(string $table, string $field, bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, 'id');
        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        $current = null;
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $utilization = $this->route('utilization');
            $id = $utilization instanceof FabaMovement ? $utilization->getKey() : $utilization;
            if (is_string($id) && $id !== '') {
                $current = FabaMovement::query()
                    ->whereIn('movement_type', FabaMovement::utilizationTypeOptions())
                    ->find($id);
            }
        }

        if (! $current || (string) $current->{$field} !== (string) $this->input($field)) {
            $rule->where('is_active', true);
        }

        return $rule;
    }

    protected function hasExistingAttachment(): bool
    {
        $utilization = $this->route('utilization');

        if (! is_string($utilization) || $utilization === '') {
            return false;
        }

        return FabaMovement::query()
            ->whereKey($utilization)
            ->whereNotNull('attachment_path')
            ->exists();
    }
}
