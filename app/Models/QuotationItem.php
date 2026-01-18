<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'description',
        'reference',
        'quantity',
        'unit',
        'unit_price_dh',
        'total_price_dh',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_dh' => 'decimal:2',
            'total_price_dh' => 'decimal:2',
        ];
    }

    // Relations
    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    // Calculate total
    public function calculateTotal()
    {
        $this->total_price_dh = $this->quantity * $this->unit_price_dh;
        return $this->total_price_dh;
    }
}