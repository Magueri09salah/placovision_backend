<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'icon',
        'link',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Types de notifications
     */
    public const TYPE_ODOO_SENT = 'odoo_sent';
    public const TYPE_ODOO_SALE = 'odoo_sale';
    public const TYPE_ODOO_CANCEL = 'odoo_cancel';
    public const TYPE_COMMANDE_CREATED = 'commande_created';

    /**
     * Configuration des types de notifications
     */
    public const TYPE_CONFIG = [
        self::TYPE_ODOO_SENT => [
            'icon' => '📤',
            'color' => 'blue',
        ],
        self::TYPE_ODOO_SALE => [
            'icon' => '✅',
            'color' => 'green',
        ],
        self::TYPE_ODOO_CANCEL => [
            'icon' => '❌',
            'color' => 'red',
        ],
        self::TYPE_COMMANDE_CREATED => [
            'icon' => '📦',
            'color' => 'purple',
        ],
    ];

    // ============ RELATIONS ============

    /**
     * L'utilisateur propriétaire de la notification
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ============ ACCESSORS ============

    /**
     * Vérifier si la notification est lue
     */
    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Obtenir la config du type
     */
    public function getTypeConfigAttribute(): array
    {
        return self::TYPE_CONFIG[$this->type] ?? [
            'icon' => '🔔',
            'color' => 'gray',
        ];
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
     * Notifications non lues
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Notifications lues
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Trier par date décroissante
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ============ METHODS ============

    /**
     * Marquer comme lue
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Marquer comme non lue
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Créer une notification pour un événement Odoo "sent"
     */
    public static function createOdooSent(Quotation $quotation): self
    {
        return self::create([
            'user_id' => $quotation->user_id,
            'type' => self::TYPE_ODOO_SENT,
            'title' => 'Devis envoyé',
            'message' => "Le devis {$quotation->reference} a été envoyé.",
            'icon' => '📤',
            'link' => "/quotations/{$quotation->id}",
            'data' => [
                'quotation_id' => $quotation->id,
                'reference' => $quotation->reference,
                'client_name' => $quotation->client_name,
            ],
        ]);
    }

    /**
     * Créer une notification pour un événement Odoo "sale" (accepté)
     */
    public static function createOdooSale(Quotation $quotation): self
    {
        return self::create([
            'user_id' => $quotation->user_id,
            'type' => self::TYPE_ODOO_SALE,
            'title' => 'Devis accepté ! 🎉',
            'message' => "Le devis {$quotation->reference} a été confirmé.",
            'icon' => '✅',
            'link' => "/quotations/{$quotation->id}",
            'data' => [
                'quotation_id' => $quotation->id,
                'reference' => $quotation->reference,
                'client_name' => $quotation->client_name,
            ],
        ]);
    }

    /**
     * Créer une notification pour un événement Odoo "cancel" (refusé)
     */
    public static function createOdooCancel(Quotation $quotation): self
    {
        return self::create([
            'user_id' => $quotation->user_id,
            'type' => self::TYPE_ODOO_CANCEL,
            'title' => 'Devis refusé',
            'message' => "Le devis {$quotation->reference} a été refusé.",
            'icon' => '❌',
            'link' => "/quotations/{$quotation->id}",
            'data' => [
                'quotation_id' => $quotation->id,
                'reference' => $quotation->reference,
                'client_name' => $quotation->client_name,
            ],
        ]);
    }

    /**
     * Créer une notification pour une commande créée
     */
    public static function createCommandeCreated(Commande $commande): self
    {
        $quotation = $commande->quotation;
        
        return self::create([
            'user_id' => $commande->user_id,
            'type' => self::TYPE_COMMANDE_CREATED,
            'title' => 'Nouvelle commande créée',
            'message' => "La commande {$commande->numero} a été créée.",
            'icon' => '📦',
            'link' => "/commandes/{$commande->id}",
            'data' => [
                'commande_id' => $commande->id,
                'numero' => $commande->numero,
                'quotation_id' => $quotation->id,
                'client_name' => $quotation->client_name,
            ],
        ]);
    }
}