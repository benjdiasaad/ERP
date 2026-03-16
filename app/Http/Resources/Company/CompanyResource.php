<?php

declare(strict_types=1);

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'legal_name'          => $this->legal_name,
            'tax_id'              => $this->tax_id,
            'registration_number' => $this->registration_number,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'address'             => $this->address,
            'currency'            => $this->currency,
            'fiscal_year_start'   => $this->fiscal_year_start,
            'logo'                => $this->logo,
            'settings'            => $this->settings,
            'is_active'           => $this->is_active,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
