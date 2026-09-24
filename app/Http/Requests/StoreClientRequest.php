<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any logged-in user (route is behind auth)
    }

    public function rules(): array
    {
        return [
            'client_type' => ['required', Rule::enum(ClientType::class)],
            'first_name' => ['nullable', 'required_if:client_type,individual', 'string', 'max:100'],
            'last_name' => ['nullable', 'required_if:client_type,individual', 'string', 'max:100'],
            'company_name' => ['nullable', 'required_if:client_type,company', 'string', 'max:150'],
            'contact_number' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required_if' => 'The first name is required for individual clients.',
            'last_name.required_if' => 'The last name is required for individual clients.',
            'company_name.required_if' => 'The company name is required for company clients.',
            'contact_number.regex' => 'Enter a valid phone number (digits, spaces, +, - and parentheses only).',
        ];
    }

    /**
     * Validated data with the fields that don't apply to the chosen type cleared.
     */
    public function clientData(): array
    {
        $data = $this->validated();

        if ($data['client_type'] === ClientType::Company->value) {
            $data['first_name'] = null;
            $data['last_name'] = null;
        } else {
            $data['company_name'] = null;
        }

        return $data;
    }
}
