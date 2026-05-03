<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Building;
use App\Models\Appartment;
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
        $cities = Building::where('region', $region)->whereNotNull('city')
        ->where('city', '!=', '')->select('city')->distinct()->pluck('city')->values();
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
}

