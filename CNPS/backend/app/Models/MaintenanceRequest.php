<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'apartment_id',
        'title',
        'description',
        'priority',
        'status',
        'requested_at',
        'resolved_at',
        'assigned_to',
        'resolution_notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apartment()
    {
        return $this->belongsTo(Appartment::class, 'apartment_id');
    }

    public function assignee()
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }
}