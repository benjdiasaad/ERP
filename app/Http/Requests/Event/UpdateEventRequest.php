<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_category_id' => ['sometimes', $this->inCompany('event_categories', 'id', false)],
            'title'             => ['sometimes', 'string', 'max:255'],
            'type'              => ['sometimes', 'in:meeting,conference,training,workshop,social,holiday'],
            'location'          => ['nullable', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'start_date'        => ['sometimes', 'date'],
            'end_date'          => ['sometimes', 'date', 'after_or_equal:start_date'],
            'budget'            => ['nullable', 'numeric', 'min:0'],
            'is_mandatory'      => ['boolean'],
            'recurring_pattern' => ['nullable', 'array'],
        ];
    }
}
