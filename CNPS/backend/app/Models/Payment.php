<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contract_id',
        'amount',
        'payment_date',
        'due_date',
        'status',
        'reference',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'due_date' => 'date',
        'amount' => 'decimal:0',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contract()
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
}