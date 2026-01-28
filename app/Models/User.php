<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'avatar',
        'account_type',
        'is_active',
        'last_login_at',
        'preferences',
        'google_id', 
        'email_verified_at', 
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'preferences' => 'array',
        ];
    }

    // Accessors
    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isParticulier(): bool
    {
        return $this->account_type === 'particulier';
    }

    public function isProfessionnel(): bool
    {
        return $this->account_type === 'professionnel';
    }

    // Relations
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role', 'is_active', 'joined_at')
            ->withTimestamps();
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

        public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }
}