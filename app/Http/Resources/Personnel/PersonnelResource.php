<?php

declare(strict_types=1);

namespace App\Http\Resources\Personnel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'company_id'                => $this->company_id,
            'user_id'                   => $this->user_id,
            'department_id'             => $this->department_id,
            'position_id'               => $this->position_id,
            'matricule'                 => $this->matricule,
            'first_name'                => $this->first_name,
            'last_name'                 => $this->last_name,
            'email'                     => $this->email,
            'phone'                     => $this->phone,
            'mobile'                    => $this->mobile,
            'gender'                    => $this->gender,
            'birth_date'                => $this->birth_date?->toDateString(),
            'birth_place'               => $this->birth_place,
            'nationality'               => $this->nationality,
            'national_id'               => $this->national_id,
            'social_security_number'    => $this->social_security_number,
            'address'                   => $this->address,
            'city'                      => $this->city,
            'country'                   => $this->country,
            'photo_path'                => $this->photo_path,
            'employment_type'           => $this->employment_type,
            'hire_date'                 => $this->hire_date?->toDateString(),
            'termination_date'          => $this->termination_date?->toDateString(),
            'status'                    => $this->status,
            'bank_name'                 => $this->bank_name,
            'bank_account'              => $this->bank_account,
            'bank_iban'                 => $this->bank_iban,
            'emergency_contact_name'    => $this->emergency_contact_name,
            'emergency_contact_phone'   => $this->emergency_contact_phone,
            'emergency_contact_relation'=> $this->emergency_contact_relation,
            'notes'                     => $this->notes,
            'created_by'                => $this->created_by,
            'department'                => $this->whenLoaded('department', fn () => new DepartmentResource($this->department)),
            'position'                  => $this->whenLoaded('position', fn () => new PositionResource($this->position)),
            'created_at'                => $this->created_at?->toISOString(),
            'updated_at'                => $this->updated_at?->toISOString(),
        ];
    }
}
