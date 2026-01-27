<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_room_id',
        'work_type',
        'surface',
        'unit',
        'subtotal_ht',
        'sort_order',
    ];

    protected $casts = [
        'surface' => 'decimal:2',
        'subtotal_ht' => 'decimal:2',
    ];

    // ========== CONSTANTES ==========

    public const WORK_TYPES = [
        'habillage_mur' => [
            'label' => 'Habillage de mur',
            'unit' => 'm2',
            'base_surface' => 10, // Règles basées sur 10 m²
        ],
        'plafond_ba13' => [
            'label' => 'Plafond BA13',
            'unit' => 'm2',
            'base_surface' => 10,
        ],
        'cloison' => [
            'label' => 'Cloison',
            'unit' => 'm2',
            'base_surface' => 10,
        ],
        'gaine_creuse' => [
            'label' => 'Gaine creuse',
            'unit' => 'ml',
            'base_surface' => 10, // 10 mètres linéaires
        ],
    ];

    /**
     * Règles de calcul des matériaux pour 10 m² ou 10 ml
     * Prix estimés en DH
     */
    public const MATERIAL_RULES = [
        'habillage_mur' => [
            ['designation' => 'Plaque BA13', 'quantity' => 3, 'unit' => 'unité', 'unit_price' => 85],
            ['designation' => 'Montant 48', 'quantity' => 12, 'unit' => 'unité', 'unit_price' => 25],
            ['designation' => 'Rail 48', 'quantity' => 3, 'unit' => 'unité', 'unit_price' => 22],
            ['designation' => 'Fourrure', 'quantity' => 2, 'unit' => 'unité', 'unit_price' => 18],
            ['designation' => 'Isolant (laine de verre)', 'quantity' => 10, 'unit' => 'm²', 'unit_price' => 35],
            ['designation' => 'Vis TTPC 25 mm', 'quantity' => 90, 'unit' => 'unité', 'unit_price' => 0.15],
            ['designation' => 'Vis TTPC 9 mm', 'quantity' => 30, 'unit' => 'unité', 'unit_price' => 0.12],
            ['designation' => 'Cheville à frapper', 'quantity' => 12, 'unit' => 'unité', 'unit_price' => 1.50],
            ['designation' => 'Bande à joint', 'quantity' => 15, 'unit' => 'm', 'unit_price' => 1.20],
            ['designation' => 'Enduit', 'quantity' => 5, 'unit' => 'sacs', 'unit_price' => 8],
        ],
        'plafond_ba13' => [
            ['designation' => 'Plaque BA13', 'quantity' => 3, 'unit' => 'unité', 'unit_price' => 85],
            ['designation' => 'Fourrure', 'quantity' => 7, 'unit' => 'unité', 'unit_price' => 18],
            ['designation' => 'Tige filetée + pivot + cheville béton', 'quantity' => 16, 'unit' => 'ensemble', 'unit_price' => 8],
            ['designation' => 'Vis TTPC 25 mm', 'quantity' => 70, 'unit' => 'unité', 'unit_price' => 0.15],
            ['designation' => 'Vis TTPC 9 mm', 'quantity' => 20, 'unit' => 'unité', 'unit_price' => 0.12],
            ['designation' => 'Bande à joint', 'quantity' => 15, 'unit' => 'm', 'unit_price' => 1.20],
            ['designation' => 'Enduit', 'quantity' => 5, 'unit' => 'sacs', 'unit_price' => 8],
        ],
        'cloison' => [
            ['designation' => 'Plaque BA13', 'quantity' => 6, 'unit' => 'unité', 'unit_price' => 85],
            ['designation' => 'Montant 70', 'quantity' => 12, 'unit' => 'unité', 'unit_price' => 32],
            ['designation' => 'Rail 70', 'quantity' => 3, 'unit' => 'unité', 'unit_price' => 28],
            ['designation' => 'Isolant (laine de verre)', 'quantity' => 10, 'unit' => 'm²', 'unit_price' => 35],
            ['designation' => 'Vis TTPC 25 mm', 'quantity' => 150, 'unit' => 'unité', 'unit_price' => 0.15],
            ['designation' => 'Vis TTPC 9 mm', 'quantity' => 30, 'unit' => 'unité', 'unit_price' => 0.12],
            ['designation' => 'Cheville à frapper', 'quantity' => 12, 'unit' => 'unité', 'unit_price' => 1.50],
            ['designation' => 'Bande à joint', 'quantity' => 30, 'unit' => 'm', 'unit_price' => 1.20],
            ['designation' => 'Enduit', 'quantity' => 10, 'unit' => 'sacs', 'unit_price' => 8],
        ],
        'gaine_creuse' => [
            ['designation' => 'Plaque BA13', 'quantity' => 2, 'unit' => 'unité', 'unit_price' => 85],
            ['designation' => 'Cornière', 'quantity' => 8, 'unit' => 'unité', 'unit_price' => 15],
            ['designation' => 'Fourrure', 'quantity' => 3, 'unit' => 'unité', 'unit_price' => 18],
            ['designation' => 'Vis TTPC 25 mm', 'quantity' => 120, 'unit' => 'unité', 'unit_price' => 0.15],
            ['designation' => 'Vis TTPC 9 mm', 'quantity' => 30, 'unit' => 'unité', 'unit_price' => 0.12],
            ['designation' => 'Tige filetée + pivot + cheville béton', 'quantity' => 10, 'unit' => 'ensemble', 'unit_price' => 8],
            ['designation' => 'Bande à joint', 'quantity' => 20, 'unit' => 'm', 'unit_price' => 1.20],
            ['designation' => 'Enduit', 'quantity' => 4, 'unit' => 'sacs', 'unit_price' => 8],
        ],
    ];

    // ========== RELATIONS ==========

    public function room(): BelongsTo
    {
        return $this->belongsTo(QuotationRoom::class, 'quotation_room_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    // ========== METHODES ==========

    public function getWorkTypeLabelAttribute(): string
    {
        return self::WORK_TYPES[$this->work_type]['label'] ?? $this->work_type;
    }

    public function getUnitLabelAttribute(): string
    {
        return $this->unit === 'm2' ? 'm²' : 'ml';
    }

    /**
     * Calculer les matériaux nécessaires selon la surface
     */
    public function calculateMaterials(): array
    {
        $rules = self::MATERIAL_RULES[$this->work_type] ?? [];
        $baseSurface = self::WORK_TYPES[$this->work_type]['base_surface'] ?? 10;
        
        // Coefficient multiplicateur basé sur la surface
        $coefficient = $this->surface / $baseSurface;
        
        $materials = [];
        foreach ($rules as $index => $rule) {
            $calculatedQuantity = ceil($rule['quantity'] * $coefficient);
            $total = $calculatedQuantity * $rule['unit_price'];
            
            $materials[] = [
                'designation' => $rule['designation'],
                'quantity_calculated' => $calculatedQuantity,
                'quantity_adjusted' => $calculatedQuantity,
                'unit' => $rule['unit'],
                'unit_price' => $rule['unit_price'],
                'total_ht' => round($total, 2),
                'is_modified' => false,
                'sort_order' => $index,
            ];
        }
        
        return $materials;
    }

    /**
     * Générer les lignes de matériaux
     */
    public function generateItems(): void
    {
        // Supprimer les anciens items
        $this->items()->delete();
        
        // Calculer et créer les nouveaux items
        $materials = $this->calculateMaterials();
        
        foreach ($materials as $material) {
            $this->items()->create($material);
        }
        
        // Recalculer le sous-total
        $this->recalculateSubtotal();
    }

    /**
     * Recalculer le sous-total du travail
     */
    public function recalculateSubtotal(): void
    {
        $subtotal = $this->items()->sum('total_ht');
        $this->update(['subtotal_ht' => round($subtotal, 2)]);
    }
}