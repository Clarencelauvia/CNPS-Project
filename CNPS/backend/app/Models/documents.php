<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class documents extends Model
{
        use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'status'
    ];

        protected $casts = [
        'file_size' => 'integer',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

       // Get document URL
    public function getUrlAttribute()
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    // Check if document is approved
    public function isApproved()
    {
        return $this->status === 'approved';
    }

       // Check if document is pending
    public function isPending()
    {
        return $this->status === 'pending';
    }

    // Check if document is rejected
    public function isRejected()
    {
        return $this->status === 'rejected';
    }
}
