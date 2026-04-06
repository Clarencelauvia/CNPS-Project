<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminActivityLog extends Model
{
      use HasFactory;

    protected $fillable = [
        'admin_id',
        'action',
        'model_type',
        'model_id',
        'changes',
        'ip_address',
    ];
    protected $casts = [
        'changes' => 'array',
    ];
       public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
