<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['required', Rule::enum(UserRole::class)],

            // 11C: for lawyers who are notaries public (printed on the register's header)
            'notarial_commission_no' => ['nullable', 'string', 'max:60'],
            'commission_expires_on' => ['nullable', 'date'],
            'notary_office_address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
