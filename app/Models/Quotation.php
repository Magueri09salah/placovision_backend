<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_id',
        'project_id',
        'reference',
        'client_name',
        'client_email',
        'client_phone',
        'site_address',
        'site_city',
        'site_postal_code',
        'total_ht',
        'total_tva',
        'total_ttc',
        'tva_rate',
        'discount_percent',
        'discount_amount',
        'status',
        'public_token',
        'validity_date',
        'accepted_at',
        'notes',
        'internal_notes',
    ];

    protected $casts = [
        'total_ht' => 'decimal:2',
        'total_tva' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'tva_rate' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'validity_date' => 'date',
        'accepted_at' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quotation) {
            if (empty($quotation->reference)) {
                $quotation->reference = static::generateReference($quotation->user_id);
            }
            if (empty($quotation->validity_date)) {
                $quotation->validity_date = now()->addDays(30);
            }
        });
    }

    /**
     * Générer une référence unique pour le devis
     */
    public static function generateReference(int $userId): string
    {
        $year = date('Y');
        $count = static::
            // where('user_id', $userId)
            whereYear('created_at', $year)
            ->count() + 1;
        
        return sprintf('DE-%s-%04d', $year, $count);
    }

    // ========== RELATIONS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(QuotationRoom::class)->orderBy('sort_order');
    }

    public function works(): HasManyThrough
    {
        return $this->hasManyThrough(QuotationWork::class, QuotationRoom::class);
    }

     // ========== QR CODE METHODS ==========

    /**
     * Obtenir l'URL publique du PDF pour le QR code
     */
    public function getPublicPdfUrlAttribute(): string
    {
        return url("/api/pdf/{$this->public_token}");
    }

    /**
     * Générer un nouveau token public
     */
    public function regeneratePublicToken(): void
    {
        $this->update(['public_token' => Str::random(32)]);
    }

    // ========== METHODES ==========

    /**
     * Recalculer les totaux du devis
     */
    public function recalculateTotals(): void
    {
        $totalHt = 0;

        foreach ($this->rooms as $room) {
            $room->recalculateSubtotal();
            $totalHt += $room->subtotal_ht;
        }

        // Appliquer la remise
        if ($this->discount_percent > 0) {
            $this->discount_amount = $totalHt * ($this->discount_percent / 100);
        }
        
        $totalHtAfterDiscount = $totalHt - $this->discount_amount;
        $totalTva = $totalHtAfterDiscount * ($this->tva_rate / 100);
        $totalTtc = $totalHtAfterDiscount + $totalTva;

        $this->update([
            'total_ht' => round($totalHt, 2),
            'total_tva' => round($totalTva, 2),
            'total_ttc' => round($totalTtc, 2),
        ]);
    }

    /**
     * Dupliquer le devis
     */
    public function duplicate(): self
    {
        $newQuotation = $this->replicate(['reference', 'status', 'accepted_at']);
        $newQuotation->status = 'draft';
        $newQuotation->save();

        foreach ($this->rooms as $room) {
            $newRoom = $room->replicate();
            $newRoom->quotation_id = $newQuotation->id;
            $newRoom->save();

            foreach ($room->works as $work) {
                $newWork = $work->replicate();
                $newWork->quotation_room_id = $newRoom->id;
                $newWork->save();

                foreach ($work->items as $item) {
                    $newItem = $item->replicate();
                    $newItem->quotation_work_id = $newWork->id;
                    $newItem->save();
                }
            }
        }

        return $newQuotation;
    }

    /**
     * Labels des statuts
     */
    public static function getStatusLabels(): array
    {
        return [
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
            'accepted' => 'Accepté',
            'rejected' => 'Refusé',
            'expired' => 'Expiré',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        // return self::getStatusLabels()[$this->status] ?? $this->status;
        $status = $this->status;
        if (empty($status)) {
            return 'Brouillon';
        }
        $labels = self::getStatusLabels();
        return $labels[$status] ?? ucfirst($status);
    }
}