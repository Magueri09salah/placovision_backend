<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $fillable = [
        'reference',
        'client_name',
        'client_email',
        'client_phone',
        'site_address',
        'site_city',
        'work_date',
        'work_type',
        'measurements',
        'total_surface',
        'estimated_amount',
        'assumptions',
        'pdf_path',
        'status'
    ];

    protected $casts = [
        'measurements' => 'array',
        'assumptions' => 'array',
        'work_date' => 'date',
        'total_surface' => 'decimal:2',
        'estimated_amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($q) {
            $year = now()->year;
            $last = self::whereYear('created_at', $year)->count() + 1;
            $q->reference = "DEV-$year-" . str_pad($last, 4, '0', STR_PAD_LEFT);
        });
    }
}

