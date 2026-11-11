<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    public function validationData(): array
    {
        return ['organization_id' => $this->query('organization_id')];
    }

    public function messages(): array
    {
        return [
            'organization_id.uuid' => 'Pilihan organisasi tidak valid.',
            'organization_id.exists' => 'Organisasi tidak tersedia atau tidak aktif.',
        ];
    }
}
