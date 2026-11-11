<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class CloseWasteHaulingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(\App\Services\HaulingPlans::class)->enabled();
    }

    public function rules(): array
    {
        return ['closure_reason' => ['required', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['closure_reason.required' => 'Alasan penutupan sisa rencana wajib diisi.'];
    }
}
