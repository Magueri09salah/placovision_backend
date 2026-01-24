<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'designation' => $this->designation,
            'description' => $this->description,
            'quantity_calculated' => (float) $this->quantity_calculated,
            'quantity_adjusted' => (float) $this->quantity_adjusted,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'total_ht' => (float) $this->total_ht,
            'is_modified' => $this->is_modified,
            'sort_order' => $this->sort_order,
        ];
    }
}