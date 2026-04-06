<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class userRegistrationRequest extends Model
{
        
  protected $table = 'user_registration_requests';
    
    protected $fillable = [
        'user_id',
        'user_type',
        'personal_info',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at'
    ];
        protected $casts = [
        'personal_info' => 'array',
        'reviewed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

        // Check if request is pending
    public function isPending()
    {
        return $this->status === 'pending';
    }

    // Check if request is approved
    public function isApproved()
    {
        return $this->status === 'approved';
    }

        // Check if request is rejected
    public function isRejected()
    {
        return $this->status === 'rejected';
    }
}
