<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'reference'        => $this->reference,
            'supplier_id'      => $this->supplier_id,
            'supplier'         => $this->whenLoaded('supplier'),
            'requested_by'     => $this->requested_by,
            'approved_by'      => $this->approved_by,
            'rejected_by'      => $this->rejected_by,
            'title'            => $this->title,
            'description'      => $this->description,
            'priority'         => $this->priority,
            'status'           => $this->status,
            'required_date'    => $this->required_date?->toDateString(),
            'notes'            => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at'     => $this->submitted_at?->toISOString(),
            'approved_at'      => $this->approved_at?->toISOString(),
            'rejected_at'      => $this->rejected_at?->toISOString(),
            'lines'            => PurchaseRequestLineResource::collection($this->whenLoaded('lines')),
            'created_by'       => $this->created_by,
            'updated_by'       => $this->updated_by,
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
