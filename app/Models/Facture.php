<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Facture extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'date_emission',
        'quotation_id',
        'user_id',
        'status',
        'total',
        'portal_url',
        'order',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'total' => 'decimal:2',
        'order' => 'integer',
    ];

public const STATUS_LABELS = [
    'non_payee'    => 'Non payée',
    'partielle'    => 'Paiement partiel',
    'en_cours'     => 'En cours de paiement',
    'payee'        => 'Payée',
    'annulee'      => 'Annulée',
];

    // ============ RELATIONS ============

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ============ ACCESSORS ============

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    // ============ SCOPES ============

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('order', 'desc');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ============ METHODS ============

    public static function generateNumero(): string
    {
        $year = date('Y');
        $prefix = "FAC-{$year}-";

        $lastFacture = self::where('numero', 'like', "{$prefix}%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($lastFacture) {
            $lastNumber = (int) substr($lastFacture->numero, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public static function getNextOrder(): int
    {
        $maxOrder = self::max('order') ?? 0;
        return $maxOrder + 1;
    }

    /**
     * Créer une facture directement à partir d'un devis
     */
    public static function createFromQuotation(Quotation $quotation): self
    {
        return self::create([
            'numero' => self::generateNumero(),
            'date_emission' => now(),
            'quotation_id' => $quotation->id,
            'user_id' => $quotation->user_id,
            'status' => 'non_payee',
            'total' => $quotation->total_price,
            'order' => self::getNextOrder(),
        ]);
    }
}