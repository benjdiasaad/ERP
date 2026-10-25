<?php

declare(strict_types=1);

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'full_name'    => $this->full_name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'mobile'       => $this->mobile,
            'job_title'    => $this->job_title,
            'organization' => $this->organization,
            'customer_id'  => $this->customer_id,
            'customer'     => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id'   => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'supplier_id'  => $this->supplier_id,
            'supplier'     => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id'   => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'address'      => $this->address,
            'city'         => $this->city,
            'country'      => $this->country,
            'source'       => $this->source,
            'tags'         => $this->tags ?? [],
            'notes'        => $this->notes,
            'is_active'    => $this->is_active,
            'created_by'   => $this->created_by,
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
