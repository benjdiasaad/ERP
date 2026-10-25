<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'reference'                   => $this->reference,
            'contact_id'                  => $this->contact_id,
            'contact'                     => new ContactResource($this->whenLoaded('contact')),
            'title'                       => $this->title,
            'description'                 => $this->description,
            'source'                      => $this->source,
            'estimated_value'             => $this->estimated_value,
            'probability'                 => $this->probability,
            'assigned_to'                 => $this->assigned_to,
            'assignee'                    => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'expected_close_date'         => $this->expected_close_date?->toDateString(),
            'status'                      => $this->status,
            'lost_reason'                 => $this->lost_reason,
            'converted_to_opportunity_id' => $this->converted_to_opportunity_id,
            'qualified_at'                => $this->qualified_at?->toISOString(),
            'converted_at'                => $this->converted_at?->toISOString(),
            'tags'                        => $this->tags ?? [],
            'created_by'                  => $this->created_by,
            'created_at'                  => $this->created_at?->toISOString(),
            'updated_at'                  => $this->updated_at?->toISOString(),
        ];
    }
}
