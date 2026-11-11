<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');
        if (! $actor) {
            return false;
        }
        if ($actor->isSuperAdmin()) {
            return true;
        }
        if ($this->superAdminStatus() || ($target instanceof User && $target->isSuperAdmin())) {
            return false;
        }
        if (! $actor->organization_id || ! $actor->canManageUsers($actor->organization_id)) {
            return false;
        }

        return (! ($target instanceof User) || $target->organization_id === $actor->organization_id)
            && (! $this->filled('organization_id') || $this->input('organization_id') === $actor->organization_id);
    }

    public function superAdminStatus(): bool
    {
        $target = $this->route('user');

        return $this->has('is_super_admin')
            ? $this->boolean('is_super_admin')
            : ($target instanceof User && $target->isSuperAdmin());
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target instanceof User ? $target->id : null)],
            'password' => $target instanceof User ? ['exclude'] : ['required', 'string', 'min:8', 'confirmed'],
            'organization_id' => $this->superAdminStatus()
                ? ['exclude']
                : ['required', 'uuid', Rule::exists('organizations', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'role_id' => ['required', 'uuid', Rule::exists('roles', 'id')],
            'is_super_admin' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'organization_id.required' => 'Pengguna standar wajib memiliki organisasi.',
            'organization_id.uuid' => 'Organisasi tidak valid.',
            'organization_id.exists' => 'Pilih organisasi yang aktif.',
            'role_id.required' => 'Peran wajib dipilih.',
            'role_id.uuid' => 'Peran tidak valid.',
            'role_id.exists' => 'Peran tidak ditemukan.',
            'is_super_admin.boolean' => 'Status super admin tidak valid.',
        ];
    }
}
