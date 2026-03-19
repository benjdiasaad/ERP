<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class InviteParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['nullable', 'exists:users,id'],
            'personnel_id' => ['nullable', 'exists:personnels,id'],
            'name'         => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'role'         => ['required', 'in:organizer,speaker,attendee,guest'],
            'rsvp_status'  => ['nullable', 'in:pending,confirmed,declined'],
        ];
    }
}
