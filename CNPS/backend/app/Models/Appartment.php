<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Appartment extends Model
{
    use HasFactory;

    protected $table = 'appartments';

    protected $fillable = [
        'building_id',
        'appartment_number',
        'floor',
        'rooms',
        'bathrooms',
        'surface_area',
        'rent_amount',
        'is_occupied',
        'is_furnished',
        'description',
        'images',
        'video_url',
        'current_tenant_id',
        'status'
    ];

    protected $casts = [
        'is_occupied' => 'boolean',
        'is_furnished' => 'boolean',
        'surface_area' => 'decimal:2',
        'rent_amount' => 'decimal:0',
        'images' => 'array',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function currentTenant()
    {
        return $this->belongsTo(User::class, 'current_tenant_id');
    }

    public function rentalContracts()
    {
        return $this->hasMany(RentalContract::class);
    }

    public function activeContract()
    {
        return $this->hasOne(RentalContract::class)->where('status', 'active');
    }

    public function getImageUrlsAttribute()
    {
        if (!$this->images) return [];
        return array_map(function($image) {
            return Storage::url($image);
        }, $this->images);
    }

    public function getFullAddressAttribute()
    {
        return "{$this->building->name}, Apartment {$this->apartment_number}, {$this->building->city}";
    }

    public function getTenantInfoAttribute()
    {
        if (!$this->current_tenant_id || !$this->currentTenant) return null;
        
        return [
            'id' => $this->currentTenant->id,
            'name' => $this->currentTenant->name,
            'email' => $this->currentTenant->email,
            'phone' => $this->currentTenant->telephone,
            'id_number' => $this->currentTenant->id_number,
            'move_in_date' => $this->activeContract ? $this->activeContract->start_date : null,
        ];
    }

    public function isAvailable()
    {
        return !$this->is_occupied && $this->status === 'available';
    }

    public function assignTenant(User $tenant, $startDate = null, $endDate = null)
    {
        $this->update([
            'current_tenant_id' => $tenant->id,
            'is_occupied' => true,
            'status' => 'occupied'
        ]);
        
        $contract = RentalContract::create([
            'user_id' => $tenant->id,
            'apartment_id' => $this->id,
            'start_date' => $startDate ?? now(),
            'end_date' => $endDate,
            'monthly_rent' => $this->rent_amount,
            'status' => 'active',
        ]);
        
        $this->building->updateAvailabilityCount();
        
        return $contract;
    }

    public function removeTenant($terminationReason = null)
    {
        $activeContract = $this->activeContract;
        
        if ($activeContract) {
            $activeContract->update([
                'status' => 'terminated',
                'terminated_at' => now(),
                'termination_reason' => $terminationReason
            ]);
        }
        
        $this->update([
            'current_tenant_id' => null,
            'is_occupied' => false,
            'status' => 'available'
        ]);
        
        $this->building->updateAvailabilityCount();
        
        return true;
    }

    public function getApartmentNumberAttribute()
{
    return $this->appartment_number;
}
}