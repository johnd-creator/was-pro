<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class ReopenFabaMonthlyApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reopen_note' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reopen_note.required' => 'Alasan membuka kembali periode wajib diisi.',
        ];
    }
}
