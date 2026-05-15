<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Appartment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ApartmentController extends Controller
{
    public function index(Request $request, $buildingId)
    {
        $building = Building::findOrFail($buildingId);
        $query = $building->apartments()->with('currentTenant');
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('is_occupied')) {
            $query->where('is_occupied', $request->is_occupied === 'true');
        }
        
        $apartments = $query->orderBy('apartment_number')->get();
        
        return response()->json([
            'building' => [
                'id' => $building->id,
                'name' => $building->name,
            ],
            'apartments' => $apartments->map(function($apartment) {
                return [
                    'id' => $apartment->id,
                    'apartment_number' => $apartment->apartment_number,
                    'floor' => $apartment->floor,
                    'rooms' => $apartment->rooms,
                    'bathrooms' => $apartment->bathrooms,
                    'surface_area' => $apartment->surface_area,
                    'rent_amount' => $apartment->rent_amount,
                    'is_occupied' => $apartment->is_occupied,
                    'is_furnished' => $apartment->is_furnished,
                    'status' => $apartment->status,
                    'video_url' => $apartment->video_url ? Storage::url($apartment->video_url) : null,
                    'current_tenant' => $apartment->currentTenant ? [
                        'id' => $apartment->currentTenant->id,
                        'name' => $apartment->currentTenant->name,
                        'email' => $apartment->currentTenant->email,
                        'phone' => $apartment->currentTenant->telephone,
                    ] : null,
                ];
            }),
        ]);
    }

    public function show($buildingId, $id)
    {
        $apartment = Apartment::with(['building', 'currentTenant', 'rentalContracts.user'])->findOrFail($id);
        
        return response()->json([
            'id' => $apartment->id,
            'apartment_number' => $apartment->apartment_number,
            'floor' => $apartment->floor,
            'rooms' => $apartment->rooms,
            'bathrooms' => $apartment->bathrooms,
            'surface_area' => $apartment->surface_area,
            'rent_amount' => $apartment->rent_amount,
            'is_occupied' => $apartment->is_occupied,
            'is_furnished' => $apartment->is_furnished,
            'description' => $apartment->description,
            'status' => $apartment->status,
            'video_url' => $apartment->video_url ? Storage::url($apartment->video_url) : null,
            'building' => [
                'id' => $apartment->building->id,
                'name' => $apartment->building->name,
            ],
            'current_tenant' => $apartment->currentTenant ? [
                'id' => $apartment->currentTenant->id,
                'name' => $apartment->currentTenant->name,
                'email' => $apartment->currentTenant->email,
            ] : null,
        ]);
    }

    public function store(Request $request, $buildingId)
    {
        $building = Building::findOrFail($buildingId);
        
        $validator = Validator::make($request->all(), [
            'apartment_number' => 'required|string|unique:apartments,apartment_number,NULL,id,building_id,' . $buildingId,
            'floor' => 'nullable|integer',
            'rooms' => 'required|integer|min:1',
            'bathrooms' => 'required|integer|min:1',
            'surface_area' => 'nullable|numeric|min:0',
            'rent_amount' => 'required|numeric|min:0',
            'is_furnished' => 'boolean',
            'description' => 'nullable|string',
            'status' => 'in:available,maintenance,reserved,occupied',
            'video' => 'nullable|file|mimes:mp4,mov,avi,mkv,webm|max:204800' // Max 200MB
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle video upload
        $videoPath = null;
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoPath = $video->store('apartments/' . date('Y/m/d') . '/videos', 'public');
        }

        $apartment = Apartment::create([
            'building_id' => $buildingId,
            'apartment_number' => $request->apartment_number,
            'floor' => $request->floor,
            'rooms' => $request->rooms,
            'bathrooms' => $request->bathrooms,
            'surface_area' => $request->surface_area,
            'rent_amount' => $request->rent_amount,
            'is_furnished' => $request->is_furnished ?? false,
            'is_occupied' => false,
            'description' => $request->description,
            'status' => $request->status ?? 'available',
            'video_url' => $videoPath,
        ]);
        
        $building->increment('total_apartments');
        $building->increment('available_apartments');
        
        return response()->json([
            'message' => 'Apartment created successfully',
            'apartment' => $apartment
        ], 201);
    }

    public function update(Request $request, $buildingId, $id)
    {
        $apartment = Apartment::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'apartment_number' => 'sometimes|string|unique:apartments,apartment_number,' . $id . ',id,building_id,' . $buildingId,
            'floor' => 'nullable|integer',
            'rooms' => 'sometimes|integer|min:1',
            'bathrooms' => 'sometimes|integer|min:1',
            'surface_area' => 'nullable|numeric|min:0',
            'rent_amount' => 'sometimes|numeric|min:0',
            'is_furnished' => 'boolean',
            'description' => 'nullable|string',
            'status' => 'in:available,maintenance,reserved,occupied',
            'video'=>'nullable|file|mimes:mp4,mov,avi, mkv, webm|max:204800' // Max 200MB
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // Handle video upload
        if($request->hasFile('video')){
            // Delete old video if exists
            if ($apartment->video_url) {
                Storage::disk('public')->delete($apartment->video_url);
            }
            $video = $request->file('video');
            $videoPath = $video->store('apartments/' . date('Y/m/d') . '/videos', 'public');
            $request->merge(['video_url' => $videoPath]);
        }
        $apartment->update($request->except(['video']));
        
        return response()->json([
            'message' => 'Apartment updated successfully',
            'apartment' => $apartment
        ]);
    }

    public function destroy($buildingId, $id)
    {
        $apartment = Apartment::findOrFail($id);
        
        if ($apartment->is_occupied) {
            return response()->json(['message' => 'Cannot delete occupied apartment'], 422);
        }
        
        $apartment->delete();
        
        $building = Building::find($buildingId);
        $building->decrement('total_apartments');
        $building->updateAvailabilityCount();
        
        return response()->json(['message' => 'Apartment deleted successfully']);
    }

    public function assignTenant(Request $request, $buildingId, $id)
    {
        $apartment = Apartment::findOrFail($id);
        
        if ($apartment->is_occupied) {
            return response()->json(['message' => 'Apartment is already occupied'], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = User::findOrFail($request->user_id);
        
        if ($user->currentApartment) {
            return response()->json(['message' => 'User already has an apartment'], 422);
        }
        
        $contract = $apartment->assignTenant($user, $request->start_date, $request->end_date);
        
        return response()->json([
            'message' => 'Tenant assigned successfully',
            'apartment' => $apartment->fresh('currentTenant'),
            'contract' => $contract
        ]);
    }

    public function removeTenant(Request $request, $buildingId, $id)
    {
        $apartment = Apartment::findOrFail($id);
        
        if (!$apartment->is_occupied) {
            return response()->json(['message' => 'Apartment is not occupied'], 422);
        }
        
        $apartment->removeTenant($request->reason ?? 'Removed by admin');
        
        return response()->json([
            'message' => 'Tenant removed successfully',
            'apartment' => $apartment->fresh()
        ]);
    }

    public function getAllTenants(Request $request)
    {
        $tenants = User::whereHas('currentApartment')
            ->with('currentApartment.building')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->telephone,
                    'apartment' => [
                        'id' => $user->currentApartment->id,
                        'number' => $user->currentApartment->apartment_number,
                        'building_name' => $user->currentApartment->building->name,
                        'monthly_rent' => $user->currentApartment->rent_amount,
                    ],
                ];
            });
        
        return response()->json([
            'total_tenants' => $tenants->count(),
            'tenants' => $tenants
        ]);
    }

    public function bulkDelete(Request $request, $buildingId)
    {
        $request->validate([
            'apartment_ids' => 'required|array',
            'apartment_ids.*' => 'exists:apartments,id'
        ]);
        
        $apartments = Apartment::whereIn('id', $request->apartment_ids)
            ->where('building_id', $buildingId)
            ->get();
        
        $deleted = 0;
        foreach ($apartments as $apartment) {
            if (!$apartment->is_occupied) {
                $apartment->delete();
                $deleted++;
            }
        }
        
        $building = Building::find($buildingId);
        $building->updateAvailabilityCount();
        
        return response()->json([
            'message' => $deleted . ' apartments deleted successfully',
            'deleted_count' => $deleted
        ]);
    }
}