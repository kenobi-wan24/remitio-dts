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
            // Walk-in (notarization only) clients can't have cases; a case may keep its existing client
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')->where(
                fn ($q) => $q->where('notarization_only', false)->when(
                    $this->route('legal_case'),
                    fn ($q, $case) => $q->orWhere('id', $case->client_id),
                ),
            )],
            'case_type' => ['required', Rule::enum(CaseType::class)],
            'docket_number' => ['nullable', 'string', 'max:50'],
            'court_or_venue' => ['nullable', 'string', 'max:255'],
            'is_confidential' => ['nullable', 'boolean'], // R3: applied only for lawyers (see caseData)
            'urgency' => ['nullable', Rule::enum(\App\Enums\Urgency::class)], // R4: lawyers only (see caseData)
            'handling_attorney_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', UserRole::lawyerValues())],
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

    public function messages(): array
    {
        return [
            'client_id.exists' => 'Choose a client who can have cases (walk-in clients are recorded for notarization only).',
        ];
    }

    /**
     * Validated data, cleaned up:
     *  - non-court matters (notarial, other) drop docket & court
     *  - closing a case sets date_closed to today if left blank; reopening clears it
     */
    public function caseData(): array
    {
        $data = $this->validated();

        // R3: only lawyers decide confidentiality; for staff the current value is left untouched
        unset($data['is_confidential'], $data['urgency']);
        if ($this->user()->isLawyer()) {
            $data['is_confidential'] = $this->boolean('is_confidential');
            if ($this->filled('urgency')) {
                $data['urgency'] = $this->input('urgency');
            }
        }

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
