<?php

namespace App\Http\Requests\WasteManagement;

use Illuminate\Foundation\Http\FormRequest;

class TransportationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'waste_record_id' => 'required|uuid|exists:waste_records,id',
            'vendor_id' => 'required|uuid|exists:vendors,id',
            'transportation_date' => 'required|date|after_or_equal:today',
            'quantity' => 'required|numeric|min:0.01',
            'vehicle_number' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:255',
            'driver_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'waste_record_id.required' => 'The waste record field is required.',
            'waste_record_id.exists' => 'The selected waste record is invalid.',
            'vendor_id.required' => 'The vendor field is required.',
            'vendor_id.exists' => 'The selected vendor is invalid.',
            'transportation_date.required' => 'The transportation date is required.',
            'transportation_date.after_or_equal' => 'The transportation date must be today or in the future.',
            'quantity.required' => 'The quantity field is required.',
            'quantity.numeric' => 'The quantity must be a number.',
            'quantity.min' => 'The quantity must be at least 0.01.',
        ];
    }
}
