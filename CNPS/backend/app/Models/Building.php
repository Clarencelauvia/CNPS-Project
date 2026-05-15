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
        'floor_configuration',
        'is_furnished',
        'has_parking',
        'total_appartments',
        'available_appartments',
        'rent_price',
        'description',
        'images',
        'video_url',
        'status',
        'total_parking_spots',
        'available_parking_spots',
        'total_floors',
        'created_by'
    ];

    protected $casts = [
        'is_furnished' => 'boolean',
        'has_parking' => 'boolean',
        'images' => 'array',
        'rent_price' => 'decimal:0',
        'floor_configuration' => 'array'
    ];

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function apartments()
    {
        return $this->hasMany(Appartment::class);
    }

    public function parkingSpots()
    {
        return $this->hasMany(ParkingSpot::class);
    }

    public function getAppartmentsByFloor()
    {
    return $this->apartments()->orderBy('floor_number')->orderBy('apartment_number')->get()->groupBy('floor_number');      
    }

    public function getFloorWithAppartments()
    {
              $floors = [];
        for ($floor = 1; $floor <= $this->total_floors; $floor++) {
            $floors[] = [
                'floor_number' => $floor,
                'apartments' => $this->apartments()->where('floor', $floor)->get()
            ];
        }
        return $floors;
    }
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
        if ($this->total_apartments == 0) return 0;
        return round(($this->occupied_apartments_count / $this->total_apartments) * 100, 1);
    }

    public function getMonthlyRevenueAttribute()
    {
        return $this->apartments()->where('is_occupied', true)->sum('rent_amount');
    }

    public function getImageUrlsAttribute()
    {
        if (!$this->images) return [];
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

    public function getStatistics()
    {
        return [
          'total_apartments' => $this->apartments()->count(),
            'furnished_apartments' => $this->apartments()->where('furnishing_status', 'furnished')->count(),
            'unfurnished_apartments' => $this->apartments()->where('furnishing_status', 'unfurnished')->count(),
            'occupied_apartments' => $this->apartments()->where('is_occupied', true)->count(),    
                      'available_apartments' => $this->apartments()->where('is_occupied', false)->count(),
            'occupied_parking_spots' => $this->parkingSpots()->where('is_occupied', true)->count(),
            'available_parking_spots' => $this->parkingSpots()->where('is_occupied', false)->count(),
            'min_rent' => $this->apartments()->min('rent_amount'),
            'max_rent' => $this->apartments()->max('rent_amount'),
            'avg_rent' => $this->apartments()->avg('rent_amount'),   
        ];
    }

       public function updateAvailabilityCounts()
    {
        $availableApartments = $this->apartments()->where('is_occupied', false)->count();
        $availableParking = $this->parkingSpots()->where('is_occupied', false)->count();
        
        $this->update([
            'available_appartments' => $availableApartments,
            'available_parking_spots' => $availableParking
        ]);
    }

    
        public function getFloorsWithApartments()
        {
         return $this->getFloorWithAppartments();
        }
}