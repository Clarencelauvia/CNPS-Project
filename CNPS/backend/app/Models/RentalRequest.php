<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RentalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'apartment_id',
        'building_id',
        'start_date',
        'duration',
        'message',
        'document_ids',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'reviewed_at' => 'datetime',
        'document_ids' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apartment()
    {
        return $this->belongsTo(Appartment::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
    
    public function getDocumentsAttribute()
    {
        if (!$this->document_ids) return [];
        return Document::whereIn('id', $this->document_ids)->get();
    }
}