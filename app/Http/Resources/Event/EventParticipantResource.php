<?php

declare(strict_types=1);

namespace App\Http\Resources\Event;

use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Personnel\PersonnelResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'event_id'    => $this->event_id,
            'user_id'     => $this->user_id,
            'personnel_id' => $this->personnel_id,
            'name'        => $this->name,
            'email'       => $this->email,
            'role'        => $this->role,
            'rsvp_status' => $this->rsvp_status,
            'user'        => new UserResource($this->whenLoaded('user')),
            'personnel'   => new PersonnelResource($this->whenLoaded('personnel')),
            'event'       => new EventResource($this->whenLoaded('event')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
