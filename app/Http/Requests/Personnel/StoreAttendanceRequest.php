<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personnel_id'   => ['required', 'integer', $this->inCompany('personnels')],
            'date'           => ['required', 'date'],
            'check_in'       => ['nullable', 'date_format:H:i:s'],
            'check_out'      => ['nullable', 'date_format:H:i:s', 'after:check_in'],
            'total_hours'    => ['nullable', 'numeric', 'min:0', 'max:24'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'status'         => ['nullable', Rule::in(['present', 'absent', 'late', 'half_day', 'remote', 'holiday'])],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
