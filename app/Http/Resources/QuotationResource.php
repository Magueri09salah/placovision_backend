<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            
            // Client
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            'client_phone' => $this->client_phone,
            
            // Site
            'site_address' => $this->site_address,
            'site_city' => $this->site_city,
            'site_postal_code' => $this->site_postal_code,
            
            // Totaux
            'total_ht' => (float) $this->total_ht,
            'total_tva' => (float) $this->total_tva,
            'total_ttc' => (float) $this->total_ttc,
            'tva_rate' => (float) $this->tva_rate,
            'discount_percent' => (float) $this->discount_percent,
            'discount_amount' => (float) $this->discount_amount,
            
            // Statut
            'status' => $this->status,
            'status_label' => $this->status_label,

            // Odoo Integration
            'odoo_order_id' => $this->odoo_order_id,
            'odoo_order_name' => $this->odoo_order_name,
            'odoo_status' => $this->odoo_status,
            'odoo_synced_at' => $this->odoo_synced_at?->format('Y-m-d H:i:s'),
            
            // Dates
            'validity_date' => $this->validity_date?->format('Y-m-d'),
            'accepted_at' => $this->accepted_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Notes
            'notes' => $this->notes,

            // ✅ QR Code - URL publique pour accès au PDF
            'public_token' => $this->public_token,
            'public_pdf_url' => $this->public_pdf_url,
            
            // Relations
            'rooms' => QuotationRoomResource::collection($this->whenLoaded('rooms')),
            
            // Computed
            'rooms_count' => $this->when($this->rooms, fn() => $this->rooms->count()),
            'works_count' => $this->when($this->rooms, fn() => $this->rooms->sum(fn($r) => $r->works->count())),
        ];
    }
}