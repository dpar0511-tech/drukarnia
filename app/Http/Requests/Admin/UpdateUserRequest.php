<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_users');
    }

    public function rules(): array
    {
        return [
            'imie' => ['required', 'string', 'max:255'],
            'nazwisko' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user)],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'role_id' => ['required', 'exists:roles,id'],
            'klient_id' => ['nullable', 'exists:klienci,id'],
            'aktywny' => ['boolean'],
        ];
    }
}
