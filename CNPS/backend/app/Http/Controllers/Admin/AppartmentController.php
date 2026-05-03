<?php
// app/Http/Controllers/Admin/ApartmentController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Apartment;
use App\Models\User;
use App\Models\RentalContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AppartmentController extends Controller
{
    // Get all apartments for a building (WITH TENANT INFO)
    public function index(Request $request, $buildingId)
    {
        $building = Building::findOrFail($buildingId);
        
        $query = $building->apartments()->with('currentTenant');
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('is_occupied')) {
            $query->where('is_occupied', $request->is_occupied === 'true');
        }
        
        if ($request->has('tenant_id')) {
            $query->where('current_tenant_id', $request->tenant_id);
        }
        
        if ($request->has('min_rent')) {
            $query->where('rent_amount', '>=', $request->min_rent);
        }
        
        if ($request->has('max_rent')) {
            $query->where('rent_amount', '<=', $request->max_rent);
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
                    'images' => $apartment->image_urls,
                    // DIRECT TENANT INFORMATION
                    'current_tenant' => $apartment->currentTenant ? [
                        'id' => $apartment->currentTenant->id,
                        'name' => $apartment->currentTenant->name,
                        'email' => $apartment->currentTenant->email,
                        'phone' => $apartment->currentTenant->telephone,
                        'id_number' => $apartment->currentTenant->id_number,
                    ] : null,
                ];
            }),
            'statistics' => [
                'total' => $apartments->count(),
                'occupied' => $apartments->where('is_occupied', true)->count(),
                'available' => $apartments->where('is_occupied', false)->where('status', 'available')->count(),
                'maintenance' => $apartments->where('status', 'maintenance')->count(),
                'occupancy_rate' => $apartments->count() > 0 
                    ? round(($apartments->where('is_occupied', true)->count() / $apartments->count()) * 100, 1)
                    : 0,
            ]
        ]);
    }
    
    // Get single apartment details (WITH FULL TENANT INFO)
    public function show($buildingId, $id)
    {
        $building = Building::findOrFail($buildingId);
        $apartment = Apartment::with(['building', 'currentTenant', 'rentalContracts.user'])->findOrFail($id);
        
        // Get rental history
        $rentalHistory = $apartment->rentalContracts()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($contract) {
                return [
                    'id' => $contract->id,
                    'tenant_name' => $contract->user->name,
                    'tenant_email' => $contract->user->email,
                    'tenant_phone' => $contract->user->telephone,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'status' => $contract->status,
                ];
            });
        
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
            'images' => $apartment->image_urls,
            'status' => $apartment->status,
            'building' => [
                'id' => $building->id,
                'name' => $building->name,
                'address' => $building->address,
                'city' => $building->city,
                'region' => $building->region,
            ],
            // CURRENT TENANT WITH FULL DETAILS
            'current_tenant' => $apartment->currentTenant ? [
                'id' => $apartment->currentTenant->id,
                'name' => $apartment->currentTenant->name,
                'email' => $apartment->currentTenant->email,
                'phone' => $apartment->currentTenant->telephone,
                'id_number' => $apartment->currentTenant->id_number,
                'user_type' => $apartment->currentTenant->user_type,
                'approval_status' => $apartment->currentTenant->approval_status,
            ] : null,
            'rental_history' => $rentalHistory,
        ]);
    }
    
    // Create new apartment
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
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'status' => 'in:available,maintenance,reserved'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('apartments/' . $buildingId, 'public');
                $imagePaths[] = $path;
            }
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
            'images' => $imagePaths,
            'current_tenant_id' => null, 
            'status' => $request->status ?? 'available',
        ]);
        
        // Update building totals
        $building->increment('total_apartments');
        $building->increment('available_apartments');
        
        return response()->json([
            'message' => 'Apartment created successfully',
            'apartment' => $apartment
        ], 201);
    }
    
    // Assign tenant to appartment
    public function assignTenant(Request $request, $buildingId, $id)
    {
        $building = Building::findOrFail($buildingId);
        $apartment = Apartment::findOrFail($id);
        
        // Check if apartment is available
        if ($apartment->is_occupied) {
            return response()->json([
                'message' => 'Apartment is already occupied by: ' . ($apartment->currentTenant?->name ?? 'Unknown')
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'deposit_amount' => 'nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = User::findOrFail($request->user_id);
        
        // Check if user already has an apartment
        if ($user->currentApartment) {
            return response()->json([
                'message' => "User {$user->name} is already assigned to apartment {$user->currentApartment->apartment_number} in building {$user->currentApartment->building->name}",
                'current_apartment' => [
                    'id' => $user->currentApartment->id,
                    'number' => $user->currentApartment->apartment_number,
                    'building' => $user->currentApartment->building->name,
                ]
            ], 422);
        }
        
        // Assign tenant to apartment 
        $contract = $apartment->assignTenant(
            $user, 
            $request->start_date, 
            $request->end_date
        );
     
        
        // Log activity
        $request->user()->logActivity('assign_tenant', 'Apartment', $apartment->id, [
            'building_name' => $building->name,
            'apartment_number' => $apartment->apartment_number,
            'tenant_name' => $user->name,
            'tenant_email' => $user->email,
            'tenant_id' => $user->id
        ]);
        
        return response()->json([
            'message' => 'Tenant assigned successfully',
            'apartment' => [
                'id' => $apartment->id,
                'apartment_number' => $apartment->apartment_number,
                'is_occupied' => true,
                'current_tenant' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->telephone,
                ]
            ],
            'contract' => $contract
        ]);
    }
    
    // REMOVE TENANT FROM APARTMENT (clears current_tenant_id)
    public function removeTenant(Request $request, $buildingId, $id)
    {
        $building = Building::findOrFail($buildingId);
        $apartment = Apartment::findOrFail($id);
        
        if (!$apartment->is_occupied) {
            return response()->json(['message' => 'Apartment is not occupied'], 422);
        }
        
        $previousTenant = $apartment->currentTenant;
        
        // Remove tenant 
        $apartment->removeTenant($request->reason ?? 'Removed by admin');
        
        // Log activity
        $request->user()->logActivity('remove_tenant', 'Apartment', $apartment->id, [
            'building_name' => $building->name,
            'apartment_number' => $apartment->apartment_number,
            'previous_tenant_name' => $previousTenant?->name,
            'previous_tenant_id' => $previousTenant?->id,
            'reason' => $request->reason
        ]);
        
        return response()->json([
            'message' => 'Tenant removed successfully',
            'apartment' => [
                'id' => $apartment->id,
                'apartment_number' => $apartment->apartment_number,
                'is_occupied' => false,
                'current_tenant' => null
            ],
            'previous_tenant' => $previousTenant ? [
                'id' => $previousTenant->id,
                'name' => $previousTenant->name,
            ] : null
        ]);
    }
    
    // GET ALL TENANTS (Users who are currently assigned to apartments)
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
                    'id_number' => $user->id_number,
                    'apartment' => [
                        'id' => $user->currentApartment->id,
                        'number' => $user->currentApartment->apartment_number,
                        'building_name' => $user->currentApartment->building->name,
                        'building_id' => $user->currentApartment->building->id,
                        'monthly_rent' => $user->currentApartment->rent_amount,
                    ],
                    'move_in_date' => $user->currentApartment->activeContract?->start_date,
                ];
            });
        
        return response()->json([
            'total_tenants' => $tenants->count(),
            'tenants' => $tenants
        ]);
    }
    
    // Update apartment
    public function update(Request $request, $buildingId, $id)
    {
        $building = Building::findOrFail($buildingId);
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
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'status' => 'in:available,occupied,maintenance,reserved'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle new image uploads
        if ($request->hasFile('images')) {
            if ($apartment->images) {
                foreach ($apartment->images as $oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
            
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('apartments/' . $buildingId, 'public');
                $imagePaths[] = $path;
            }
            $request->merge(['images' => $imagePaths]);
        }
        
        $apartment->update($request->except('images') + ($request->has('images') ? ['images' => $request->images] : []));
        
        // Update building availability count if status changed
        if ($request->has('status')) {
            $building->updateAvailabilityCount();
        }
        
        return response()->json([
            'message' => 'Apartment updated successfully',
            'apartment' => $apartment
        ]);
    }
    
    // Delete apartment
    public function destroy(Request $request, $buildingId, $id)
    {
        $building = Building::findOrFail($buildingId);
        $apartment = Apartment::findOrFail($id);
        
        // Check if apartment has a tenant
        if ($apartment->is_occupied) {
            return response()->json([
                'message' => 'Cannot delete occupied apartment. Current tenant: ' . ($apartment->currentTenant?->name ?? 'Unknown')
            ], 422);
        }
        
        // Delete associated images
        if ($apartment->images) {
            foreach ($apartment->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }
        
        $apartment->delete();
        
        // Update building totals
        $building->decrement('total_apartments');
        $building->updateAvailabilityCount();
        
        return response()->json(['message' => 'Apartment deleted successfully']);
    }
}