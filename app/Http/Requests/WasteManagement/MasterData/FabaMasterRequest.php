<?php

namespace App\Http\Requests\WasteManagement\MasterData;

use App\Models\FabaInternalDestination;
use App\Models\FabaPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FabaMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->isMethod('POST') ? 'faba_master.create' : 'faba_master.edit') ?? false;
    }

    public function rules(): array
    {
        $model = $this->route('kind') === 'destinations' ? FabaInternalDestination::class : FabaPurpose::class;
        $record = $this->route('record') ? $model::query()->findOrFail($this->route('record')) : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => $record ? ['sometimes', Rule::in([$record->slug])] : ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique((new $model)->getTable(), 'slug')],
            'description' => ['nullable', 'string', 'max:10000'], 'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Nama wajib diisi.', 'slug.required' => 'Kode wajib diisi.', 'slug.unique' => 'Kode sudah digunakan.', 'slug.in' => 'Kode tidak dapat diubah karena digunakan dalam perhitungan historis.', 'slug.regex' => 'Gunakan huruf kecil, angka, dan tanda hubung.', 'is_active.required' => 'Status wajib dipilih.'];
    }
}
