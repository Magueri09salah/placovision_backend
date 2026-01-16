<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'reference',
        'description',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'start_date',
        'end_date',
        'estimated_completion_date',
        'status',
        'priority',
        'estimated_budget',
        'actual_cost',
        'client_name',
        'client_email',
        'client_phone',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'estimated_completion_date' => 'date',
            'estimated_budget' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    // Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->isProfessionnel()) {
            $companyId = $user->companies()->first()?->id;
            return $query->where('company_id', $companyId);
        }
        return $query->where('created_by', $user->id);
    }
}
