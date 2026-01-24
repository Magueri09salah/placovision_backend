<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'ice',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'country',
        'logo',
        'odoo_partner_id',
        'odoo_synced_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'odoo_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot('role', 'is_active', 'joined_at')
            ->withTimestamps();
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

}
