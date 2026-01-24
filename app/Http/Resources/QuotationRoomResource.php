<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_type' => $this->room_type,
            'room_type_label' => $this->room_type_label,
            'room_name' => $this->room_name,
            'display_name' => $this->display_name,
            'subtotal_ht' => (float) $this->subtotal_ht,
            'sort_order' => $this->sort_order,
            'works' => QuotationWorkResource::collection($this->whenLoaded('works')),
        ];
    }
}