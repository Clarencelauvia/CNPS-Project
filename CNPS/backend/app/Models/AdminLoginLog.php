<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminLoginLog extends Model
{
     use HasFactory;

    protected $fillable = [
        'admin_id',
        'ip_address',
        'user_agent',
        'successful',
        'failure_reason',
    ];
    
      protected $casts = [
        'successful' => 'boolean',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

}
