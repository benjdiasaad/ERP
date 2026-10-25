<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'subject'      => $this->subject,
            'description'  => $this->description,
            'related_type' => $this->related_alias,
            'related_id'   => $this->related_id,
            'related_label' => $this->whenLoaded('related', fn () => $this->relatedLabel()),
            'assigned_to'  => $this->assigned_to,
            'assignee'     => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'due_date'     => $this->due_date?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'priority'     => $this->priority,
            'status'       => $this->status,
            'outcome'      => $this->outcome,
            'is_overdue'   => $this->due_date !== null
                && in_array($this->status, ['pending', 'in_progress'], true)
                && $this->due_date->isPast(),
            'created_by'   => $this->created_by,
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
