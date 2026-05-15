<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'apartment_id',
        'start_date',
        'end_date',
        'monthly_rent',
        'status',
        'terminated_at',
        'termination_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'terminated_at' => 'datetime',
        'monthly_rent' => 'decimal:0',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apartment()
    {
        return $this->belongsTo(Appartment::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}