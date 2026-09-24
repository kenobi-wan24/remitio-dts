<?php

namespace App\Http\Requests;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLegalCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'case_type' => ['required', Rule::enum(CaseType::class)],
            'docket_number' => ['nullable', 'string', 'max:50'],
            'court_or_venue' => ['nullable', 'string', 'max:255'],
            'handling_attorney_id' => ['nullable', Rule::exists('users', 'id')->where('role', UserRole::Admin->value)],
            'status' => ['required', Rule::enum(CaseStatus::class)],
            'date_opened' => ['required', 'date', 'before_or_equal:today'],
            'date_closed' => ['nullable', 'date', 'after_or_equal:date_opened', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'case_type' => 'case type',
            'court_or_venue' => 'court / venue',
            'handling_attorney_id' => 'handling attorney',
            'date_opened' => 'date opened',
            'date_closed' => 'date closed',
        ];
    }

    /**
     * Validated data, cleaned up:
     *  - non-court matters (notarial, consultation, other) drop docket & court
     *  - closing a case sets date_closed to today if left blank; reopening clears it
     */
    public function caseData(): array
    {
        $data = $this->validated();

        if (! CaseType::from($data['case_type'])->isLitigated()) {
            $data['docket_number'] = null;
            $data['court_or_venue'] = null;
        }

        if ($data['status'] === CaseStatus::Closed->value) {
            $data['date_closed'] ??= today()->toDateString();
        } else {
            $data['date_closed'] = null;
        }

        return $data;
    }
}
