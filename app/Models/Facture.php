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
        'commande_id',
        'status',
        'total',
        'order',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'total' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Status labels pour l'affichage
     */
    public const STATUS_LABELS = [
        'en_attente' => 'En attente',
        'payee' => 'Payée',
        'annulee' => 'Annulée',
    ];

    // ============ RELATIONS ============

    /**
     * La commande associée à cette facture
     */
    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }

    // ============ ACCESSORS ============

    /**
     * Obtenir le label du status
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Accéder au devis via la commande
     */
    public function getQuotationAttribute()
    {
        return $this->commande?->quotation;
    }

    /**
     * Accéder à l'utilisateur via la commande
     */
    public function getUserAttribute()
    {
        return $this->commande?->user;
    }

    // ============ SCOPES ============

    /**
     * Filtrer par status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Trier par order décroissant (dernier ajouté en premier)
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('order', 'desc');
    }

    /**
     * Filtrer par utilisateur (via commande)
     */
    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('commande', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    // ============ METHODS ============

    /**
     * Générer un numéro de facture unique
     * Format: FAC-2026-0001
     */
    public static function generateNumero(): string
    {
        $year = date('Y');
        $prefix = "FAC-{$year}-";

        // Trouver le dernier numéro de l'année
        $lastFacture = self::where('numero', 'like', "{$prefix}%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($lastFacture) {
            // Extraire le numéro et incrémenter
            $lastNumber = (int) substr($lastFacture->numero, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Obtenir le prochain order pour le tri
     */
    public static function getNextOrder(): int
    {
        $maxOrder = self::max('order') ?? 0;
        return $maxOrder + 1;
    }

    /**
     * Créer une facture à partir d'une commande
     */
    public static function createFromCommande(Commande $commande): self
    {
        return self::create([
            'numero' => self::generateNumero(),
            'date_emission' => now(),
            'commande_id' => $commande->id,
            'status' => 'en_attente',
            'total' => $commande->prix_total,
            'order' => self::getNextOrder(),
        ]);
    }
}
