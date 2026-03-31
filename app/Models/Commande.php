<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'status',
        'prix_total',
        'quotation_id',
        'user_id',
        'order',
    ];

    protected $casts = [
        'prix_total' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Status labels pour l'affichage
     */
    public const STATUS_LABELS = [
        'en_attente' => 'En attente',
        'en_cours' => 'En cours',
        'livree' => 'Livrée',
        'annulee' => 'Annulée',
    ];

    // ============ RELATIONS ============

    /**
     * Le devis associé à cette commande
     */
    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * L'utilisateur propriétaire de la commande
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La facture associée à cette commande
     */
    public function facture()
    {
        return $this->hasOne(Facture::class);
    }

    // ============ ACCESSORS ============

    /**
     * Obtenir le label du status
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    // ============ SCOPES ============

    /**
     * Filtrer par utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

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

    // ============ METHODS ============

    /**
     * Générer un numéro de commande unique
     * Format: CMD-2026-0001
     */
    public static function generateNumero(): string
    {
        $year = date('Y');
        $prefix = "CMD-{$year}-";

        // Trouver le dernier numéro de l'année
        $lastCommande = self::where('numero', 'like', "{$prefix}%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($lastCommande) {
            // Extraire le numéro et incrémenter
            $lastNumber = (int) substr($lastCommande->numero, -4);
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
     * Créer une commande à partir d'un devis
     */
    public static function createFromQuotation(Quotation $quotation): self
    {
        return self::create([
            'numero' => self::generateNumero(),
            'status' => 'en_attente',
            'prix_total' => $quotation->total_ttc,
            'quotation_id' => $quotation->id,
            'user_id' => $quotation->user_id,
            'order' => self::getNextOrder(),
        ]);
    }
}