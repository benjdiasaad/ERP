<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personnel_id'     => ['sometimes', 'integer', $this->inCompany('personnels')],
            'type'             => ['sometimes', Rule::in(['annual', 'sick', 'maternity', 'paternity', 'unpaid', 'compensatory'])],
            'start_date'       => ['sometimes', 'date'],
            'end_date'         => ['sometimes', 'date', 'after_or_equal:start_date'],
            'total_days'       => ['nullable', 'numeric', 'min:0'],
            'reason'           => ['nullable', 'string'],
            'status'           => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'rejection_reason' => ['nullable', 'string'],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
