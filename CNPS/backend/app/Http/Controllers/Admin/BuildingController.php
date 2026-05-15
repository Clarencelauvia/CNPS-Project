<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Building;
use App\Models\Appartment as Apartment;
use App\Models\ParkingSpot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class BuildingController extends Controller
{
    // Get all buildings with pagination and filtering
    public function index(Request $request)
    {
        $query = Building::query();

        // Apply filters
        if($request->has('region') && $request->region){
            $query->where('region', $request->region);
        }

        if($request->has('city') && $request->city){
            $query->where('city', $request->city);
        }

        if($request->has('is_furnished') && $request->is_furnished == 'true'){
            $query->where('is_furnished', true);
        }

            if($request->has('has_parking') && $request->has_parking == 'true'){
                $query->where('has_parking', true);
            }
    
            if($request->has('rent_price_min') && $request->rent_price_min){
                $query->where('rent_price', '>=', $request->rent_price_min);
            }
    
            if($request->has('rent_price_max') && $request->rent_price_max){
                $query->where('rent_price', '<=', $request->rent_price_max);
            }
    
            if($request->has('available_only') && $request->available_only == 'true'){
                $query->where('available_appartments', '>', 0);
            }
    
            if($request->has('search') && $request->search){
                $searchTerm = '%' . $request->search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                    ->orWhere('address', 'like', $searchTerm);
                });
            }

        $buildings = $query->orderBy('created_at', 'desc')->paginate(20);
       
        return response()->json([
            'buildings' => $buildings->map(function($buildings){
                return [
                    'id' => $buildings->id,
                    'name' => $buildings->name,
                    'region' => $buildings->region,
                    'city' => $buildings->city,
                    'address' => $buildings->address,
                    'is_furnished' => $buildings->is_furnished,
                    'has_parking' => $buildings->has_parking,
                    'total_appartments' => $buildings->total_appartments,
                    'available_appartments' => $buildings->available_appartments,
                    'rent_price' => $buildings->rent_price,
                    'description' => $buildings->description,
                    'images' => $buildings->images ? array_map(function($image) {
                    return Storage::url($image);
                    }, $buildings->images) : [],
                    'video_url' => $buildings->video_url,
                    'status' => $buildings->status,
                    'created_at' => $buildings->created_at,
                ];
            }),
        ]);
    }

    // Get single building details
    public function show($id)
    {
        $building = Building::with('creator')->findOrFail($id);
        return response()->json([
            'id' => $building->id,
            'name' => $building->name,
            'region' => $building->region,
            'city' => $building->city,
            'address' => $building->address,
            'is_furnished' => $building->is_furnished,
            'has_parking' => $building->has_parking,
            'total_appartments' => $building->total_appartments,
            'available_appartments' => $building->available_appartments,
            'rent_price' => $building->rent_price,
            'description' => $building->description,
            'images' => $building->images ? array_map(function($image) {
                return Storage::url($image);
            }, $building->images) : [],
            'video_url' => $building->video_url,
            'status' => $building->status,
            'apartments'=> $building->apartments->map(function($apartment){
                return [
                    'id' => $apartment->id,
                    'apartment_number' => $apartment->appartment_number,
                    'floor' => $apartment->floor,
                    'rooms' => $apartment->rooms,
                    'bathrooms' => $apartment->bathrooms,
                    'surface_area' => $apartment->surface_area,
                    'rent_amount' => $apartment->rent_amount,
                    'is_occupied' => $apartment->is_occupied,
                    'is_furnished' => $apartment->is_furnished,
                    'description' => $apartment->description,
                    'images' => $apartment->images ? array_map(function($image) {
                        return Storage::url($image);
                    }, $apartment->images) : [],
                    'video_url' => $apartment->video_url ? Storage::url($apartment->video_url) : null,
                    'current_tenant' => $apartment->currentTenant ? [
                    'id' => $apartment->currentTenant->id,
                    'name' => $apartment->currentTenant->name,
                    'email' => $apartment->currentTenant->email,
                    'phone' => $apartment->currentTenant->telephone,
                ] : null,
                ];
            }),
            'created_by' => [
                'id' => $building->creator ? $building->creator->id : null,
                'name' => $building->creator ? $building->creator->name : null
             ],
            'created_at' => $building->created_at,

        ]);

    }

    // Create new building
    public function store(Request $request)
    {

      
    \Log::info('Building store request:', $request->all());
        // Convert string booleans to actual booleans BEFORE validation
            if ($request->has('is_furnished')) {
              $request->merge([
                'is_furnished' => filter_var($request->is_furnished, FILTER_VALIDATE_BOOLEAN)
              ]);
            }

            if ($request->has('has_parking')) {
              $request->merge([
                'has_parking' => filter_var($request->has_parking, FILTER_VALIDATE_BOOLEAN)
              ]);
            }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'region' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'is_furnished' => 'boolean',
            'has_parking' => 'boolean',
            'total_appartments' => 'integer|min:0',
            'available_appartments' => 'integer|min:0',
            'rent_price' => 'numeric|min:0',
            'description' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
            'video_url' => 'nullable|url',
            'status' => 'required|in:active,maintenance,inactive'
        ]);


             if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')){
            foreach ($request->file('images') as $image) {
                $path = $image->store('buildings/' . date('Y/m/d') . '/images', 'public');
                $imagePaths[] = $path;
            }
        }

        // Handle video upload
        $videoPath = null;
        if ($request->hasFile('video')){
            $video = $request->file('video');
            $videoPath = $video->store('buildings/' . date('Y/m/d') . '/videos', 'public');
        }

        $building = Building::create([
            'name' => $request->name,
            'region' => $request->region,
            'city' => $request->city,
            'address' => $request->address,
            'is_furnished' => $request->is_furnished ?? false,
            'has_parking' => $request->has_parking ?? false,
            'total_appartments' => $request->total_appartments ?? 0,
            'available_appartments' => $request->available_appartments ?? 0,
            'rent_price' => $request->rent_price ?? 0.00,
            'description' => $request->description,
            'images' => $imagePaths,
            'video_url' => $videoPath,
            'status' => $request->status ?? 'active',
            
        ]);

        // Log activity
        $request->user()->logActivity('create_building', 'Building', $building->id, [
            'name' => $building->name,
            'region' => $building->region,
            'city' => $building->city,
        ]);

        return response()->json([
            'message' => 'Building created successfully',
            'building_id' => $building->id
         ], 201);

    }

    // update buildings
    public function update(Request $request, $id)
    {
        $building = Building::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'region' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:500',
            'is_furnished' => 'sometimes|boolean',
            'has_parking' => 'sometimes|boolean',
            'total_appartments' => 'sometimes|integer|min:0',
            'available_appartments' => 'sometimes|integer|min:0',
            'rent_price' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
            'video_url' => 'nullable|url',
            'status' => 'in:available,unavailable'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle image uploads
        $imagePaths = $building->images ?? [];
        if ($request->hasFile('images')){
        //    Delete old buildings
        if($building->images){
             foreach ($building->images as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        $imagePaths = [];
        foreach ($request->file('images') as $image) {
            $path = $image->store('buildings/' . date('Y/m/d') . '/images', 'public');
            $imagePaths[] = $path;
        }

        $request->merge(['images' => $imagePaths]);
        }

        // Handle video upload
        $videoPath = $building->video_url;
        if ($request->hasFile('video')){
        //    Delete old video
        if($building->video_url){
            Storage::disk('public')->delete($building->video_url);
        }

        $video = $request->file('video');
        $videoPath = $video->store('buildings/' . date('Y/m/d') . '/videos', 'public');
        $request->merge(['video_url' => $videoPath]);
        }

        $building->update($request->except(['images', 'video_url'])
        + ($request->has('images') ? ['images' => $request->images]:
        []) + ($request->has('video_url') ? ['video_url' => $request->video_url] : [])
        );

        // Log activity
        $request->user()->logActivity('update_building', 'Building', $building->id, [
            'name' => $building->name
        ]);

        return response()->json([
            'message' => 'Building updated successfully',
            'building' => $building
         ], 200);
        
    }

    // Delete building
    public function destroy(Request $request, $id)
    {
        $building =  Building::findOrFail($id);
        // check if building had appartments
        if($building->apartments()->count() > 0){
            return response()->json([
                'message' => 'Cannot delete building with existing apartments. Please delete or reassign apartments first.'
             ], 400);
        }

        // Delete associated images
        if($building->images){
            foreach ($building->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        // Delete associated video
        if($building->video_url){
            Storage::disk('public')->delete($building->video_url);
        }

        // Delete the building
        $buildingName = $building->name;
        $building->delete();

        // Log activity
        $request->user()->logActivity('delete_building', 'Building', $id, [
            'name' => $buildingName
         ]);

         return response()->json([
            'message' => 'Building deleted successfully'
         ], 200);
    }

     
  // Toggle building status in a defined cycle: active -> inactive -> maintenance -> active
public function toggleStatus(Request $request, $id)
{
    $building = Building::findOrFail($id);
    
    // Define the cycle order
    $statusCycle = ['active', 'inactive', 'maintenance'];
    
    // Find current index and get next status
    $currentIndex = array_search($building->status, $statusCycle);
    $nextStatus = $statusCycle[($currentIndex + 1) % count($statusCycle)];
    
    $building->update(['status' => $nextStatus]);
    
    // Log activity
    $request->user()->logActivity('toggle_building_status', 'Building', $building->id, [
        'old_status' => $building->status,
        'new_status' => $nextStatus
    ]);
    
    return response()->json([
        'message' => "Building status changed to {$nextStatus}",
        'status' => $nextStatus
    ], 200);
}

    // Get regionsd list for filters
    public function getRegions()
    {
        $regions = Building::select('region')->distinct()->pluck('region');
        return response()->json(['regions' => $regions]);
    }

    //  Get cities by region
    public function getCities($region)
    {
        $region = $request->query('region');
    if (!$region) {
        return response()->json(['cities' => []]);
    }
        $cities = Building::where('region', $region)
        ->whereNotNull('city')
        ->where('city', '!=', '')
        ->select('city')
        ->distinct()
        ->pluck('city')
        ->values();
        return response()->json(['cities' => $cities]);
    }

    // Get building statistics for dashboard
    public function statistics()
    {
        $stats = [
            'total_buildings' => Building::count(),
            'active_buildings' => Building::where('status', 'available')->count(),
            'total_apartments' => Building::sum('total_apartments'),
            'available_apartments' => Building::sum('available_apartments'),
            'occupied_apartments' => Building::sum('total_apartments') - Building::sum('available_apartments'),
            'total_monthly_revenue' => Building::sum('rent_price'),
            'avg_rent' => Building::avg('rent_price'),
        ];

                $stats['occupancy_rate'] = $stats['total_apartments'] > 0 
            ? round(($stats['occupied_apartments'] / $stats['total_apartments']) * 100, 1)
            : 0;
            
        return response()->json(['statistics' => $stats]);
    }

    public function getBuildingDetails($id)
    {
          $building = Building::with(['apartments', 'parkingSpots', 'creator'])->findOrFail($id);

          return response() ->json([
            'building' => [
            'id' => $building->id,
            'name' => $building->name,
            'region' => $building->region,
            'city' => $building->city,
            'address' => $building->address,
            'total_floors' => $building->total_floors,
            'total_parking_spots' => $building->total_parking_spots,
    
            'available_parking_spots' => $building->available_parking_spots,
            'is_furnished' => $building->is_furnished,
            'has_parking' => $building->has_parking,
            'rent_price' => $building->rent_price,
            'description' => $building->description,
            'images' => $building->images ? array_map(function($image) {
            
            return Storage::url($image);
            }, $building->images) : [],
            'video_url' => $building->video_url ? Storage::url($building->video_url) : null,
            'status' => $building->status,
            'statistics' => $building->getStatistics(),
            'floors_with_apartments' => $building->getFloorsWithApartments(),
            'parking_spots' => $building->parkingSpots,
            'created_at' => $building->created_at,
        ]
          ]);
    }

public function personalizeBuilding(Request $request, $id)
{
      // Debug: Log everything
    \Log::info('Request Data:', $request->all());
    \Log::info('Floor Configuration:', $request->floor_configuration ?? []);
        // Test if building exists
    $building = Building::find($id);
    if (!$building) {
        return response()->json(['message' => 'Building not found'], 404);
    }

       try {
        $testApartment = new \App\Models\Appartment();
        \Log::info('Apartment model loaded successfully');
    } catch (\Exception $e) {
        \Log::error('Apartment model failed to load: ' . $e->getMessage());
        return response()->json(['message' => 'Model error: ' . $e->getMessage()], 500);
    }
    

    try {

    
        Log::info('Starting personalizeBuilding', ['building_id' => $id, 'request_data' => $request->all()]);
        
        $building = Building::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'total_floors' => 'required|integer|min:1|max:50',
            'floor_configuration' => 'required|array',
            'floor_configuration.*.floor_number' => 'required|integer',
            'floor_configuration.*.apartments_per_floor' => 'required|integer|min:0|max:20',
            'floor_configuration.*.furnished_count' => 'required|integer|min:0',
            'floor_configuration.*.unfurnished_count' => 'required|integer|min:0',
            'total_parking_spots' => 'nullable|integer|min:0',
            'parking_price' => 'nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Delete existing apartments and parking spots
        Log::info('Deleting existing apartments and parking spots');
        $building->apartments()->delete();
        $building->parkingSpots()->delete();
        
        $totalApartments = 0;
        $totalFurnished = 0;
        $totalUnfurnished = 0;
        $minRentPrice = PHP_INT_MAX;
        
        // Create apartments based on floor configuration
        foreach ($request->floor_configuration as $index => $floorConfig) {
            Log::info('Processing floor', ['floor_config' => $floorConfig]);
            
            $floorNumber = $floorConfig['floor_number'];
            $apartmentsPerFloor = $floorConfig['apartments_per_floor'];
            $furnishedCount = $floorConfig['furnished_count'];
            $unfurnishedCount = $floorConfig['unfurnished_count'];
            
            // Get prices - default to 0 if not provided
            $furnishedPrice = $floorConfig['furnished_rent_price'] ?? 0;
            $unfurnishedPrice = $floorConfig['unfurnished_rent_price'] ?? 0;
            
            // Validation: If there are furnished apartments, price must be > 0
            if ($furnishedCount > 0 && $furnishedPrice <= 0) {
                return response()->json([
                    'message' => "For floor {$floorNumber}, furnished apartments require a rent price greater than 0"
                ], 422);
            }
            
            // Validation: If there are unfurnished apartments, price must be > 0
            if ($unfurnishedCount > 0 && $unfurnishedPrice <= 0) {
                return response()->json([
                    'message' => "For floor {$floorNumber}, unfurnished apartments require a rent price greater than 0"
                ], 422);
            }
            
            if ($apartmentsPerFloor > 0 && $furnishedCount + $unfurnishedCount != $apartmentsPerFloor) {
                Log::error('Validation failed for floor', [
                    'floor' => $floorNumber,
                    'apartments_per_floor' => $apartmentsPerFloor,
                    'furnished_count' => $furnishedCount,
                    'unfurnished_count' => $unfurnishedCount
                ]);
                return response()->json([
                    'message' => "For floor {$floorNumber}, furnished_count + unfurnished_count must equal apartments_per_floor"
                ], 422);
            }
            
            $totalApartments += $apartmentsPerFloor;
            $totalFurnished += $furnishedCount;
            $totalUnfurnished += $unfurnishedCount;
            
            // Track min rent price for building (only consider prices > 0)
            if ($apartmentsPerFloor > 0) {
                $pricesToConsider = [];
                if ($furnishedCount > 0 && $furnishedPrice > 0) {
                    $pricesToConsider[] = $furnishedPrice;
                }
                if ($unfurnishedCount > 0 && $unfurnishedPrice > 0) {
                    $pricesToConsider[] = $unfurnishedPrice;
                }
                if (!empty($pricesToConsider)) {
                    $minRentPrice = min($minRentPrice, min($pricesToConsider));
                }
            }
            
            // Create furnished apartments
            for ($i = 1; $i <= $furnishedCount; $i++) {
                $apartmentNumber = $this->generateApartmentNumber($floorNumber, $i, $apartmentsPerFloor);
                
                try {
                    $apartment = new Apartment();
                    $apartment->building_id = $building->id;
                    $apartment->appartment_number = $apartmentNumber;
                    $apartment->floor = $floorNumber;
                    $apartment->rooms = $floorConfig['rooms'] ?? 2;
                    $apartment->bathrooms = $floorConfig['bathrooms'] ?? 1;
                    $apartment->surface_area = $floorConfig['surface_area'] ?? 50;
                    $apartment->rent_amount = $furnishedPrice;
                    $apartment->is_furnished = true;
                    $apartment->is_occupied = false;
                    $apartment->status = 'available';
                    $apartment->description = "Appartement meublé - Étage {$floorNumber}";
                    $apartment->save();
                    Log::info('Created furnished apartment', ['number' => $apartmentNumber]);
                } catch (\Exception $e) {
                    Log::error('Failed to create furnished apartment', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
            
            // Create unfurnished apartments
            for ($i = $furnishedCount + 1; $i <= $apartmentsPerFloor; $i++) {
                $apartmentNumber = $this->generateApartmentNumber($floorNumber, $i, $apartmentsPerFloor);
                
                try {
                    $apartment = new Apartment();
                    $apartment->building_id = $building->id;
                    $apartment->appartment_number = $apartmentNumber;
                    $apartment->floor = $floorNumber;
                    $apartment->rooms = $floorConfig['rooms'] ?? 2;
                    $apartment->bathrooms = $floorConfig['bathrooms'] ?? 1;
                    $apartment->surface_area = $floorConfig['surface_area'] ?? 50;
                    $apartment->rent_amount = $unfurnishedPrice;
                    $apartment->is_furnished = false;
                    $apartment->is_occupied = false;
                    $apartment->status = 'available';
                    $apartment->description = "Appartement non meublé - Étage {$floorNumber}";
                    $apartment->save();
                    Log::info('Created unfurnished apartment', ['number' => $apartmentNumber]);
                } catch (\Exception $e) {
                    Log::error('Failed to create unfurnished apartment', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
        }
        
        // Create parking spots (only if total parking spots > 0)
        $parkingPrice = $request->parking_price ?? 50000;
        $totalParkingSpots = $request->total_parking_spots ?? 0;
        
        for ($i = 1; $i <= $totalParkingSpots; $i++) {
            try {
                $parkingSpot = new ParkingSpot();
                $parkingSpot->building_id = $building->id;
                $parkingSpot->spot_number = "P-{$i}";
                $parkingSpot->type = $request->input('parking_type', 'open');
                $parkingSpot->monthly_price = $parkingPrice;
                $parkingSpot->is_occupied = false;
                $parkingSpot->status = 'available';
                $parkingSpot->save();
                Log::info('Created parking spot', ['spot' => "P-{$i}"]);
            } catch (\Exception $e) {
                Log::error('Failed to create parking spot', ['error' => $e->getMessage()]);
                throw $e;
            }
        }
        
        // Set minRentPrice to 0 if no valid prices were found
        if ($minRentPrice === PHP_INT_MAX) {
            $minRentPrice = 0;
        }
        
        // Update building
        $updateData = [
            'total_floors' => $request->total_floors,
            'total_parking_spots' => $totalParkingSpots,
            'available_parking_spots' => $totalParkingSpots, // ADD THIS
            'total_appartments' => $totalApartments,
            'available_appartments' => $totalApartments,
            'rent_price' => $minRentPrice,
            'is_furnished' => $totalFurnished > 0,
            'has_parking' => $totalParkingSpots > 0,
            'floor_configuration' => $request->floor_configuration
        ];
        
        Log::info('Updating building', $updateData);
        $building->update($updateData);
        
        return response()->json([
            'message' => 'Building personalized successfully',
            'building' => $building->fresh(['apartments', 'parkingSpots'])
        ]);
        
    } catch (\Exception $e) {
        Log::error('Personalize building failed', [
            'building_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'An error occurred while personalizing the building',
            'error' => $e->getMessage()
        ], 500);
    }
}
   


    private function generateApartmentNumber($floor, $position, $totalPerFloor)
    {
        // Format: F{floor}AP{position} (e.g., F1AP01)
    return sprintf("E%dAP%02d", $floor, $position);
    }

    public function updateAppartmentPrice(Request $request, $buildingId, $appartmentId)
    {

     $apartment = Apartment::findOrFail($apartmentId);
    
    $validator = Validator::make($request->all(), [
        'rent_amount' => 'required|numeric|min:0',
        'furnished_rent_price' => 'nullable|numeric|min:0',
        'unfurnished_rent_price' => 'nullable|numeric|min:0'
    ]);
        if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
    
    $apartment->update($request->only([
        'rent_amount', 'furnished_rent_price', 'unfurnished_rent_price'
    ]));

        // Update building's min rent price
    $building = Building::find($buildingId);
    $minRent = $building->apartments()->min('rent_amount');
    $building->update(['rent_price' => $minRent]);
    
    return response()->json([
        'message' => 'Apartment price updated successfully',
        'apartment' => $apartment
    ]);

    }

    public function bulkUpdateParkingPrices(Request $request, $buildingId)
{
    $validator = Validator::make($request->all(), [
        'price' => 'required|numeric|min:0'
    ]);
    
    $building = Building::findOrFail($buildingId);
    $building->parkingSpots()->update(['monthly_price' => $request->price]);
    
    return response()->json([
        'message' => 'All parking spot prices updated successfully'
    ]);
}

public function uploadApartmentVideo(Request $request, $buildingId)
{
    try {
        \Log::info('=== UPLOAD APARTMENT VIDEO DEBUG ===');
        \Log::info('Building ID: ' . $buildingId);
        \Log::info('Request all: ' . json_encode($request->all()));
        \Log::info('Has file video: ' . $request->hasFile('video'));
        \Log::info('Apartment number: ' . $request->apartment_number);
        
        $request->validate([
            'apartment_number' => 'required|string',
            'video' => 'required|file|mimes:mp4,mov,avi,mkv,webm|max:204800',
        ]);
        
        // Find the apartment
        $apartment = \App\Models\Appartment::where('building_id', $buildingId)
            ->where('appartment_number', $request->apartment_number)
            ->first();
        
        \Log::info('Apartment found: ' . ($apartment ? 'Yes' : 'No'));
        if ($apartment) {
            \Log::info('Apartment ID: ' . $apartment->id);
            \Log::info('Apartment number: ' . $apartment->appartment_number);
        }
        
        if (!$apartment) {
            \Log::error('Apartment not found!');
            return response()->json(['message' => 'Apartment not found'], 404);
        }
        
        // Delete old video if exists
        if ($apartment->video_url) {
            \Log::info('Deleting old video: ' . $apartment->video_url);
            Storage::disk('public')->delete($apartment->video_url);
        }
        
        // Upload new video
        $video = $request->file('video');
        $videoPath = $video->store('apartments/' . date('Y/m/d') . '/videos', 'public');
        \Log::info('Video saved at: ' . $videoPath);
        
        // Update apartment
        $apartment->update(['video_url' => $videoPath]);
        \Log::info('Apartment updated with video_url: ' . $videoPath);
        
        return response()->json([
            'message' => 'Video uploaded successfully',
            'video_url' => Storage::url($videoPath),
            'apartment_id' => $apartment->id
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Upload apartment video error: ' . $e->getMessage());
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}


   // Public index for users (no auth)
public function publicIndex(Request $request)
{
    $query = Building::where('status', 'active');
    
    if($request->has('region') && $request->region){
        $query->where('region', $request->region);
    }
    if($request->has('city') && $request->city){
        $query->where('city', $request->city);
    }
    if($request->has('min_rent') && $request->min_rent){
        $query->where('rent_price', '>=', $request->min_rent);
    }
    if($request->has('max_rent') && $request->max_rent){
        $query->where('rent_price', '<=', $request->max_rent);
    }
    
    $buildings = $query->orderBy('created_at', 'desc')->get();
    
    return response()->json([
        'buildings' => $buildings->map(function($building){
            return [
                'id' => $building->id,
                'name' => $building->name,
                'region' => $building->region,
                'city' => $building->city,
                'address' => $building->address,
                'is_furnished' => $building->is_furnished,
                'has_parking' => $building->has_parking,
                'total_appartments' => $building->total_appartments,
                'available_appartments' => $building->available_appartments,
                'rent_price' => $building->rent_price,
                'description' => $building->description,
                'images' => $building->images ? array_map(function($image) {
                    return Storage::url($image);
                }, $building->images) : [],
                'video_url' => $building->video_url ? Storage::url($building->video_url) : null,
                'status' => $building->status,
            ];
        }),
    ]);
}

// Public show for users
public function publicShow($id)
{
    $building = Building::with('apartments')->findOrFail($id);
    
    return response()->json([
        'id' => $building->id,
        'name' => $building->name,
        'region' => $building->region,
        'city' => $building->city,
        'address' => $building->address,
        'is_furnished' => $building->is_furnished,
        'has_parking' => $building->has_parking,
        'total_appartments' => $building->total_appartments,
        'available_appartments' => $building->available_appartments,
        'rent_price' => $building->rent_price,
        'description' => $building->description,
        'images' => $building->images ? array_map(function($image) {
            return Storage::url($image);
        }, $building->images) : [],
        'video_url' => $building->video_url ? Storage::url($building->video_url) : null,
        'apartments' => $building->apartments->map(function($apartment) {
            return [
                'id' => $apartment->id,
                'apartment_number' => $apartment->appartment_number,
                'floor' => $apartment->floor,
                'rooms' => $apartment->rooms,
                'bathrooms' => $apartment->bathrooms,
                'surface_area' => $apartment->surface_area,
                'rent_amount' => $apartment->rent_amount,
                'is_occupied' => $apartment->is_occupied,
                'is_furnished' => $apartment->is_furnished,
                'description' => $apartment->description,
                'video_url' => $apartment->video_url ? Storage::url($apartment->video_url) : null,
                'images' => $apartment->images ? array_map(function($image) {
                    return Storage::url($image);
                }, $apartment->images) : [],
            ];
        }),
    ]);
}



}

