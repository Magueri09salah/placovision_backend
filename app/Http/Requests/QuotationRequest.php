<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Client info
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            
            // Site info
            'site_address' => 'required|string|max:255',
            'site_city' => 'required|string|max:100',
            'site_postal_code' => 'nullable|string|max:20',
            
            // Options
            'tva_rate' => 'nullable|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            
            // Rooms (pièces)
            'rooms' => 'required|array|min:1',
            'rooms.*.room_type' => 'required|in:salon_sejour,chambre,cuisine,salle_de_bain,wc,bureau,garage,exterieur,autre',
            'rooms.*.room_name' => 'nullable|string|max:100',
            
            // Works (travaux par pièce)
            'rooms.*.works' => 'required|array|min:1',
            'rooms.*.works.*.work_type' => 'required|in:habillage_mur,plafond_ba13,cloison,gaine_creuse',
            'rooms.*.works.*.surface' => 'required|numeric|min:0.1|max:10000',
        ];
    }

    public function messages(): array
    {
        return [
            'client_name.required' => 'Le nom du client est obligatoire.',
            'site_address.required' => 'L\'adresse du chantier est obligatoire.',
            'site_city.required' => 'La ville est obligatoire.',
            'rooms.required' => 'Vous devez ajouter au moins une pièce.',
            'rooms.min' => 'Vous devez ajouter au moins une pièce.',
            'rooms.*.room_type.required' => 'Le type de pièce est obligatoire.',
            'rooms.*.room_type.in' => 'Type de pièce invalide.',
            'rooms.*.works.required' => 'Chaque pièce doit avoir au moins un travail.',
            'rooms.*.works.min' => 'Chaque pièce doit avoir au moins un travail.',
            'rooms.*.works.*.work_type.required' => 'Le type de travail est obligatoire.',
            'rooms.*.works.*.work_type.in' => 'Type de travail invalide.',
            'rooms.*.works.*.surface.required' => 'La surface est obligatoire.',
            'rooms.*.works.*.surface.numeric' => 'La surface doit être un nombre.',
            'rooms.*.works.*.surface.min' => 'La surface doit être supérieure à 0.',
        ];
    }
}