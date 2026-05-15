<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ParkingSpot extends Model
{
        use HasFactory;

    protected $fillable = [
        'building_id',
        'spot_number',
        'type',
        'is_occupied',
        'current_tenant_id',
        'monthly_price',
        'status'
    ];

     protected $casts = [
        'is_occupied' => 'boolean',
        'monthly_price' => 'decimal:0',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function currentTenant()
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

      public function assignToTenant(User $tenant)
    {
        $this->update([
            'current_tenant_id' => $tenant->id,
            'is_occupied' => true,
            'status' => 'occupied'
        ]);
        
        $this->building->updateAvailabilityCounts();
    }

        public function removeTenant()
    {
        $this->update([
            'current_tenant_id' => null,
            'is_occupied' => false,
            'status' => 'available'
        ]);
        
        $this->building->updateAvailabilityCounts();
    }
}
