<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
        'reference' => $this->reference,

        // Client
        'client_name' => $this->client_name,
        'client_email' => $this->client_email,
        'client_phone' => $this->client_phone,

        // Chantier
        'site_address' => $this->site_address,
        'site_city' => $this->site_city,

        // Devis
        'work_type' => $this->work_type,
        'total_surface' => $this->total_surface,
        'estimated_amount' => $this->estimated_amount,

        // Dates
        'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
