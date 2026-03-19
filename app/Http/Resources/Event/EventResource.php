<?php

declare(strict_types=1);

namespace App\Http\Resources\Event;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'event_category_id' => $this->event_category_id,
            'category'          => new EventCategoryResource($this->whenLoaded('category')),
            'title'             => $this->title,
            'type'              => $this->type,
            'location'          => $this->location,
            'description'       => $this->description,
            'start_date'        => $this->start_date?->toISOString(),
            'end_date'          => $this->end_date?->toISOString(),
            'budget'            => $this->budget,
            'is_mandatory'      => $this->is_mandatory,
            'recurring_pattern' => $this->recurring_pattern,
            'status'            => $this->status,
            'created_by'        => $this->created_by,
            'participants'      => EventParticipantResource::collection($this->whenLoaded('participants')),
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
