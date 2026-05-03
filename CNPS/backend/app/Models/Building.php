<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Building extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'region',
        'city',
        'address',
        'is_furnished',
        'has_parking',
        'total_appartments',
        'available_appartments',
        'rent_price',
        'description',
        'images',
        'video_url',
        'status',
    ];

    protected $casts = [
        'is_furnished' => 'boolean',
        'has_parking' => 'boolean',
        'images' => 'array',
        'rent_price' => 'decimal:0'
    ];

      // Relationships
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function apartments()
    {
        return $this->hasMany(Apartment::class);
    }


    // get all tenants currently renting apartments in this building
        public function currentTenants()
    {
        return User::whereHas('rentalContracts', function($query) {
            $query->where('status', 'active')
                  ->whereHas('apartment', function($q) {
                      $q->where('building_id', $this->id);
                  });
        })->get();
    }

       public function getOccupiedApartmentsCountAttribute()
    {
        return $this->apartments()->where('is_occupied', true)->count();
    }

       public function getOccupancyRateAttribute()
    {
        if ($this->total_apartments == 0) {
            return 0;
        }
        return round(($this->occupied_apartments_count / $this->total_apartments) * 100, 1);
    }

     // Get total monthly revenue
    public function getMonthlyRevenueAttribute()
    {
        return $this->apartments()->where('is_occupied', true)->sum('rent_amount');
    }

       // Accessors for images
    public function getImageUrlsAttribute()
    {
        if (!$this->images) {
            return [];
        }
        return array_map(function($image) {
            return Storage::url($image);
        }, $this->images);
    }

        public function isActive()
    {
        return $this->status === 'active';
    }

    public function hasAvailableApartments()
    {
        return $this->available_apartments > 0;
    }

    
    public function updateAvailabilityCount()
    {
        $available = $this->apartments()->where('status', 'available')->count();
        $this->update(['available_apartments' => $available]);
        return $available;
    }
}
