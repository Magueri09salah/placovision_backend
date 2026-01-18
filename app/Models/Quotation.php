<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by',
        'reference',
        'client_name',
        'client_email',
        'client_phone',
        'site_address',
        'site_city',
        'site_postal_code',
        'work_date',
        'observations',
        'plan_file',
        'work_type',
        'measurements',
        'total_surface',
        'estimated_amount_dh',
        'assumptions',
        'status',
        'valid_until',
        'sent_at',
        'accepted_at',
        'exported_pdf_at',
        'odoo_quotation_id',
        'odoo_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'measurements' => 'array',
            'assumptions' => 'array',
            'work_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'exported_pdf_at' => 'datetime',
            'odoo_synced_at' => 'datetime',
            'total_surface' => 'decimal:2',
            'estimated_amount_dh' => 'decimal:2',
        ];
    }

    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    // Scopes
    public function scopeForUser($query, User $user)
    {
        if ($user->isProfessionnel()) {
            $companyId = $user->companies()->first()?->id;
            return $query->where('company_id', $companyId);
        }
        return $query->where('created_by', $user->id);
    }

    // Helpers
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until < now();
    }
}