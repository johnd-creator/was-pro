<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class RejectFabaMonthlyApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_note' => ['required', 'string'],
        ];
    }
}
