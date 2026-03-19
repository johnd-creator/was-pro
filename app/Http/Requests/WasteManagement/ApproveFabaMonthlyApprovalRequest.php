<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class ApproveFabaMonthlyApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_note' => ['nullable', 'string'],
        ];
    }
}
