<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationWorkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_type' => $this->work_type,
            'work_type_label' => $this->work_type_label,
            'epaisseur' => $this->epaisseur,        // ✅ add
            'isolant' => $this->isolant ?? 'none',  // ✅ add
            'ouvertures' => $this->ouvertures ?? [], // ✅ add
            'surface' => (float) $this->surface,
            'longueur' => $this->longueur,
            'hauteur' => $this->hauteur,
            'unit' => $this->unit,
            'unit_label' => $this->unit_label,
            'subtotal_ht' => (float) $this->subtotal_ht,
            'sort_order' => $this->sort_order,
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
        ];
    }
}