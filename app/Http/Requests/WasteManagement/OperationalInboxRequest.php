<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class OperationalInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'read' => [$this->isMethod('PATCH') ? 'required' : 'sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'Halaman harus berupa angka bulat.',
            'page.min' => 'Halaman minimal 1.',
            'page.max' => 'Halaman di luar jangkauan.',
            'read.required' => 'Status baca wajib dipilih.',
            'read.boolean' => 'Status baca tidak valid.',
        ];
    }
}
