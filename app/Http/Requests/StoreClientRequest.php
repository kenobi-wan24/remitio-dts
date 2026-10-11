<?php

namespace App\Http\Requests;

use App\Enums\CivilStatus;
use App\Enums\ClientType;
use App\Enums\Gender;
use App\Enums\NameSuffix;
use App\Services\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    /** Form field "category" → regular / retainer / walk_in (notarization only). */
    public const CATEGORIES = ['regular', 'retainer', 'walk_in'];

    private const PHONE = '/^[0-9+()\-\s]{7,30}$/';

    public function authorize(): bool
    {
        return true; // any logged-in user (route is behind auth)
    }

    /** A walk-in client is always an individual. */
    protected function prepareForValidation(): void
    {
        if ($this->input('category') === 'walk_in') {
            $this->merge(['client_type' => ClientType::Individual->value]);
        }
    }

    public function isWalkIn(): bool
    {
        return $this->input('category') === 'walk_in';
    }

    private function isCompany(): bool
    {
        return $this->input('client_type') === ClientType::Company->value;
    }

    private function isMarried(): bool
    {
        return ! $this->isCompany() && $this->input('civil_status') === CivilStatus::Married->value;
    }

    /** The ID section only exists on the "new client" form (later IDs are added on the profile). */
    private function collectsId(): bool
    {
        return $this->isMethod('POST') && ! $this->isCompany();
    }

    public function rules(): array
    {
        $walkIn = $this->isWalkIn();

        $rules = [
            'client_type' => ['required', Rule::enum(ClientType::class)],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'is_retainer' => ['nullable', 'boolean'],

            // Individual
            'last_name' => ['nullable', 'required_if:client_type,individual', 'string', 'max:100'],
            'first_name' => ['nullable', 'required_if:client_type,individual', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', Rule::enum(NameSuffix::class)],
            'civil_status' => $walkIn
                ? ['nullable', Rule::enum(CivilStatus::class)]
                : ['nullable', 'required_if:client_type,individual', Rule::enum(CivilStatus::class)],
            'gender' => $walkIn
                ? ['nullable', Rule::enum(Gender::class)]
                : ['nullable', 'required_if:client_type,individual', Rule::enum(Gender::class)],

            // Spouse (only when married)
            'spouse_last_name' => ['nullable', Rule::requiredIf(fn () => $this->isMarried()), 'string', 'max:100'],
            'spouse_first_name' => ['nullable', Rule::requiredIf(fn () => $this->isMarried()), 'string', 'max:100'],
            'spouse_middle_name' => ['nullable', 'string', 'max:100'],
            'spouse_contact_number' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE],

            // Company
            'company_name' => ['nullable', 'required_if:client_type,company', 'string', 'max:150'],
            'representative' => ['nullable', 'required_if:client_type,company', 'string', 'max:150'],
            'business_permit_number' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:30', 'regex:/^[0-9][0-9\-\s]{7,24}$/'],

            // Contact (walk-ins may leave it blank)
            'contact_number' => [$walkIn ? 'nullable' : 'required', 'string', 'max:30', 'regex:'.self::PHONE],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->collectsId()) {
            // Walk-ins are recorded for notarization, so their ID is required
            $rules['id_type'] = [$walkIn ? 'required' : 'nullable', 'required_with:id_number', 'string', 'max:60'];
            $rules['id_number'] = [$walkIn ? 'required' : 'nullable', 'required_with:id_type', 'string', 'max:60'];
            $rules['scan'] = [
                'nullable', 'file', 'extensions:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.AttachmentService::MAX_KB,
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'last_name' => 'last name',
            'first_name' => 'first name',
            'middle_name' => 'middle name',
            'civil_status' => 'civil status',
            'spouse_last_name' => "spouse's last name",
            'spouse_first_name' => "spouse's first name",
            'spouse_middle_name' => "spouse's middle name",
            'spouse_contact_number' => "spouse's contact number",
            'company_name' => 'business name',
            'business_permit_number' => 'business permit number',
            'tin' => 'TIN',
            'contact_number' => 'contact number',
            'id_type' => 'ID type',
            'id_number' => 'ID number',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required_if' => 'The first name is required for individual clients.',
            'last_name.required_if' => 'The last name is required for individual clients.',
            'civil_status.required_if' => 'Select the civil status.',
            'gender.required_if' => 'Select the gender.',
            'spouse_last_name.required' => "Enter the spouse's last name (civil status is Married).",
            'spouse_first_name.required' => "Enter the spouse's first name (civil status is Married).",
            'company_name.required_if' => 'The business name is required for company clients.',
            'representative.required_if' => 'Enter the name of the company representative.',
            'contact_number.regex' => 'Enter a valid phone number (digits, spaces, +, - and parentheses only).',
            'spouse_contact_number.regex' => 'Enter a valid phone number (digits, spaces, +, - and parentheses only).',
            'tin.regex' => 'Enter a valid TIN (digits and dashes, e.g. 123-456-789-000).',
            'id_type.required' => 'Walk-in clients need a valid ID (type and number) for the notarial record.',
            'id_number.required' => 'Walk-in clients need a valid ID (type and number) for the notarial record.',
            'id_type.required_with' => 'Enter the ID type together with the ID number.',
            'id_number.required_with' => 'Enter the ID number together with the ID type.',
            'scan.extensions' => 'The scan must be a PDF, JPG or PNG file.',
            'scan.mimetypes' => 'The scan must be a PDF, JPG or PNG file.',
            'scan.max' => 'The scan must be 10 MB or smaller.',
        ];
    }

    /**
     * Validated client columns, with fields that don't apply to the chosen
     * type / category / civil status cleared.
     */
    public function clientData(): array
    {
        $data = collect($this->validated())
            ->except(['category', 'id_type', 'id_number', 'scan'])
            ->all();

        // Category (regular / retainer / walk-in). Without it, fall back to the old checkbox.
        $category = $this->input('category');
        $data['notarization_only'] = $category === 'walk_in';
        $data['is_retainer'] = match ($category) {
            'retainer' => true,
            'regular', 'walk_in' => false,
            default => $this->boolean('is_retainer'),
        };

        if ($data['client_type'] === ClientType::Company->value) {
            foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'civil_status', 'gender'] as $field) {
                $data[$field] = null;
            }
            $data['notarization_only'] = false;
        } else {
            foreach (['company_name', 'representative', 'business_permit_number', 'tin'] as $field) {
                $data[$field] = null;
            }
        }

        if (($data['civil_status'] ?? null) !== CivilStatus::Married->value) {
            foreach (['spouse_last_name', 'spouse_first_name', 'spouse_middle_name', 'spouse_contact_number'] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /** ID entered on the "new client" form, or null when none was given. */
    public function idData(): ?array
    {
        if (! $this->collectsId() || blank($this->input('id_type')) || blank($this->input('id_number'))) {
            return null;
        }

        return [
            'id_type' => trim($this->input('id_type')),
            'id_number' => trim($this->input('id_number')),
            'scan' => $this->file('scan') instanceof UploadedFile ? $this->file('scan') : null,
        ];
    }
}
