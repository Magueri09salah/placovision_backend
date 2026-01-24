<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'room_type',
        'room_name',
        'sort_order',
        'subtotal_ht',
    ];

    protected $casts = [
        'subtotal_ht' => 'decimal:2',
    ];

    // ========== CONSTANTES ==========

    public const ROOM_TYPES = [
        'salon_sejour' => 'Salon / Séjour',
        'chambre' => 'Chambre',
        'cuisine' => 'Cuisine',
        'salle_de_bain' => 'Salle de bain',
        'wc' => 'WC',
        'bureau' => 'Bureau',
        'garage' => 'Garage / Local technique',
        'exterieur' => 'Extérieur',
        'autre' => 'Autre',
    ];

    // ========== RELATIONS ==========

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function works(): HasMany
    {
        return $this->hasMany(QuotationWork::class)->orderBy('sort_order');
    }

    // ========== METHODES ==========

    public function getRoomTypeLabelAttribute(): string
    {
        return self::ROOM_TYPES[$this->room_type] ?? $this->room_type;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->room_name ?: $this->room_type_label;
    }

    /**
     * Recalculer le sous-total de la pièce
     */
    public function recalculateSubtotal(): void
    {
        $subtotal = 0;

        foreach ($this->works as $work) {
            $work->recalculateSubtotal();
            $subtotal += $work->subtotal_ht;
        }

        $this->update(['subtotal_ht' => round($subtotal, 2)]);
    }
}