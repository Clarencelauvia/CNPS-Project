<?php

namespace App\Models;


use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'address',
        'user_type',
        'id_number',
        'approval_status',
        'rejection_reason',
        'approved_at',
        'approved_by',
        'activated_at',
        'activation_token',
        'is_activated',
        'has_completed_profile',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'activated_at' => 'datetime',
        'is_activated' => 'boolean',
        'has_completed_profile' => 'boolean',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
    
    public function registrationRequest()
    {
        return $this->hasOne(UserRegistrationRequest::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }
        public function isApproved()
    {
        return $this->approval_status === 'approved';
    }

    public function isPending()
    {
        return $this->approval_status === 'pending';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }



public function rentalContracts()
{
    return $this->hasMany(RentalContract::class);
}

public function activeRentalContract()
{
    return $this->hasOne(RentalContract::class)->where('status', 'active');
}

public function currentApartment()
{
   return $this->hasOne(Apartment::class, 'current_tenant_id');
}

public function isTenant()
{
    return $this->activeRentalContract()->exists();
}


// Get current appartment details
public function getCurrentApartmentDetailsAttribute()
{
    if (!$this->currentApartment) {
        return null;
    }
    
    return [
        'apartment_id' => $this->currentApartment->id,
        'apartment_number' => $this->currentApartment->apartment_number,
        'building_name' => $this->currentApartment->building->name,
        'building_id' => $this->currentApartment->building->id,
        'monthly_rent' => $this->currentApartment->rent_amount,
        'move_in_date' => $this->currentApartment->activeContract?->start_date,
    ];
}

}