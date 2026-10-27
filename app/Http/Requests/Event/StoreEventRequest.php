<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_category_id' => ['required', $this->inCompany('event_categories', 'id', false)],
            'title'             => ['required', 'string', 'max:255'],
            'type'              => ['required', 'in:meeting,conference,training,workshop,social,holiday'],
            'location'          => ['nullable', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'start_date'        => ['required', 'date'],
            'end_date'          => ['required', 'date', 'after_or_equal:start_date'],
            'budget'            => ['nullable', 'numeric', 'min:0'],
            'is_mandatory'      => ['boolean'],
            'recurring_pattern' => ['nullable', 'array'],
            'status'            => ['nullable', 'in:planned,confirmed,in_progress,completed,cancelled,postponed'],
        ];
    }
}
