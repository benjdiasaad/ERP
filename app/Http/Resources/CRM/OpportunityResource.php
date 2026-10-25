<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'reference'           => $this->reference,
            'lead_id'             => $this->lead_id,
            'contact_id'          => $this->contact_id,
            'contact'             => new ContactResource($this->whenLoaded('contact')),
            'customer_id'         => $this->customer_id,
            'customer'            => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id'   => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'title'               => $this->title,
            'description'         => $this->description,
            'value'               => $this->value,
            'probability'         => $this->probability,
            'weighted_value'      => $this->weighted_value,
            'stage'               => $this->stage,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'actual_close_date'   => $this->actual_close_date?->toDateString(),
            'assigned_to'         => $this->assigned_to,
            'assignee'            => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'lost_reason'         => $this->lost_reason,
            'won_quote_id'        => $this->won_quote_id,
            'tags'                => $this->tags ?? [],
            'created_by'          => $this->created_by,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
