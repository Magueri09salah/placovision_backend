<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_work_id',
        // 'product_id',
        'designation',
        'description',
        'quantity_calculated',
        'quantity_adjusted',
        'unit',
        'unit_price',
        'total_ht',
        'is_modified',
        'sort_order',
    ];

    protected $casts = [
        'quantity_calculated' => 'decimal:2',
        'quantity_adjusted' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_ht' => 'decimal:2',
        'is_modified' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // Recalculer le total quand la quantité ajustée change
        static::saving(function ($item) {
            $item->total_ht = round($item->quantity_adjusted * $item->unit_price, 2);
            
            // Marquer comme modifié si la quantité diffère
            if ($item->quantity_adjusted != $item->quantity_calculated) {
                $item->is_modified = true;
            }
        });

        // Mettre à jour les totaux parents après sauvegarde
        static::saved(function ($item) {
            $item->work->recalculateSubtotal();
        });
    }

    // ========== RELATIONS ==========

    public function work(): BelongsTo
    {
        return $this->belongsTo(QuotationWork::class, 'quotation_work_id');
    }

    // public function product(): BelongsTo
    // {
    //     return $this->belongsTo(Product::class);
    // }

    // ========== METHODES ==========

    /**
     * Réinitialiser à la quantité calculée
     */
    public function resetToCalculated(): void
    {
        $this->update([
            'quantity_adjusted' => $this->quantity_calculated,
            'is_modified' => false,
        ]);
    }

    /**
     * Ajuster la quantité
     */
    public function adjustQuantity(float $newQuantity): void
    {
        $this->update([
            'quantity_adjusted' => $newQuantity,
            'is_modified' => $newQuantity != $this->quantity_calculated,
        ]);
    }
}