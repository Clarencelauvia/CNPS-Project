<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\ApartmentController;
use App\Http\Controllers\Admin\AdminRegistrationManagementController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\UserStatsController;
use Illuminate\Http\Request;
// Admin login
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated Admin Routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // BUILDING MANAGEMENT
    Route::prefix('buildings')->group(function() {
        Route::get('/regions', [BuildingController::class, 'getRegions']);
        Route::get('/cities', [BuildingController::class, 'getCities']);
        Route::get('/stats', [BuildingController::class, 'statistics']);
        Route::get('/', [BuildingController::class, 'index']);
        Route::post('/', [BuildingController::class, 'store']);
        Route::get('/{id}', [BuildingController::class, 'show']);
        Route::post('/{id}', [BuildingController::class, 'update']);
        Route::delete('/{id}', [BuildingController::class, 'destroy']);
        Route::put('/{id}/toggle-status', [BuildingController::class, 'toggleStatus']);
        Route::get('/{id}/details', [BuildingController::class, 'getBuildingDetails']);
        Route::post('/{id}/personalize', [BuildingController::class, 'personalizeBuilding']);
        Route::put('/buildings/{buildingId}/apartments/{apartmentId}/price', [BuildingController::class, 'updateApartmentPrice']);
        Route::put('/buildings/{buildingId}/parking/prices', [BuildingController::class, 'bulkUpdateParkingPrices']);
        Route::post('/{buildingId}/apartment-video', [BuildingController::class, 'uploadApartmentVideo']);
        
        // APARTMENT MANAGEMENT
        Route::prefix('/{buildingId}/apartments')->group(function() {
            Route::get('/', [ApartmentController::class, 'index']);
            Route::get('/{id}', [ApartmentController::class, 'show']);
            Route::post('/', [ApartmentController::class, 'store']);
            Route::put('/{id}', [ApartmentController::class, 'update']);
            Route::delete('/{id}', [ApartmentController::class, 'destroy']);
            Route::post('/{id}/assign-tenant', [ApartmentController::class, 'assignTenant']);
            Route::post('/{id}/remove-tenant', [ApartmentController::class, 'removeTenant']);
            Route::post('/bulk-delete', [ApartmentController::class, 'bulkDelete']);
        });
    });
    
    // TENANT MANAGEMENT
    Route::get('/tenants', [ApartmentController::class, 'getAllTenants']);
    
    // REGISTRATION MANAGEMENT
    Route::get('/registrations/pending', [AdminRegistrationManagementController::class, 'pendingRegistrationRequests']);
    Route::get('/registrations/approved', [AdminRegistrationManagementController::class, 'approvedRegistrations']);
    Route::get('/registrations/rejected', [AdminRegistrationManagementController::class, 'rejectedRegistrations']);
    Route::get('/registrations/{id}', [AdminRegistrationManagementController::class, 'showRegistration']);
    Route::post('/registrations/{id}/approve', [AdminRegistrationManagementController::class, 'approve']);
    Route::post('/registrations/{id}/reject', [AdminRegistrationManagementController::class, 'reject']);
    Route::prefix('registrations')->group(function() {
    // Account creation requests (Step 1)
    Route::get('/account-creation/pending', [AdminRegistrationManagementController::class, 'pendingAccountCreations']);
    Route::get('/account-creation/approved', [AdminRegistrationManagementController::class, 'approvedAccountCreations']);
    Route::get('/account-creation/rejected', [AdminRegistrationManagementController::class, 'rejectedAccountCreations']);
    Route::post('/account-creation/{id}/approve', [AdminRegistrationManagementController::class, 'approveAccountCreation']);
    Route::post('/account-creation/{id}/reject', [AdminRegistrationManagementController::class, 'rejectAccountCreation']);
    
    // Rental requests (Step 2 - after account is active)
    Route::get('/rental-requests/pending', [AdminRegistrationManagementController::class, 'pendingRentalRequests']);
    Route::get('/rental-requests/approved', [AdminRegistrationManagementController::class, 'approvedRentalRequests']);
    Route::get('/rental-requests/rejected', [AdminRegistrationManagementController::class, 'rejectedRentalRequests']);
    Route::post('/rental-requests/{id}/approve', [AdminRegistrationManagementController::class, 'approveRentalRequest']);
    Route::post('/rental-requests/{id}/reject', [AdminRegistrationManagementController::class, 'rejectRentalRequest']);
    Route::get('/{id}', [AdminRegistrationManagementController::class, 'showRegistration']);
});
    
    // USER MANAGEMENT
    Route::delete('/users/{id}', [AdminRegistrationManagementController::class, 'deleteUser']);
    Route::post('/users/{id}/toggle-suspend', [AdminRegistrationManagementController::class, 'toggleSuspend']);
    Route::get('/users', [AdminRegistrationManagementController::class, 'getAllUsers']);
    
    // DOCUMENT MANAGEMENT
    Route::get('/documents/pending', [AdminRegistrationManagementController::class, 'pendingDocumentSubmissions']);
    Route::get('/documents/{id}', [AdminRegistrationManagementController::class, 'showDocumentSubmission']);
    Route::post('/documents/{id}/approve', [AdminRegistrationManagementController::class, 'approveDocuments']);
    Route::post('/documents/{id}/reject', [AdminRegistrationManagementController::class, 'rejectDocuments']);
    Route::get('/documents/{id}/download', [AdminRegistrationManagementController::class, 'downloadDocument']);
});

// User Registration & Login
Route::post('/login', [App\Http\Controllers\User\UserController::class, 'login']);
Route::post('/register', [App\Http\Controllers\User\UserController::class, 'register']);
Route::post('/user/logout', [App\Http\Controllers\User\UserController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/user/complete-profile', [App\Http\Controllers\User\UserController::class, 'completeProfile'])->middleware('auth:sanctum');
Route::middleware(['auth:sanctum'])->group(function () {
Route::get('/me', function (Request $request) {
    $user = $request->user();

    if(!$user){
        return response()->json([
            'message' => 'User not authenticated'
         ], 401);
    }
    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'telephone' => $user->telephone,
        'phone' => $user->telephone,
        'approval_status' => $user->approval_status,
        'has_completed_profile' => $user->has_completed_profile ?? false,
        'user_type' => $user->user_type,
        'status' => $user->status,
        'is_activated' => $user->is_activated,
    ]);
});
});

// User Stats & Dashboard Routes
Route::middleware(['auth:sanctum'])->prefix('user')->group(function () {
    Route::get('/stats', [App\Http\Controllers\User\UserStatsController::class, 'getStats']);
    Route::get('/active-rental', [App\Http\Controllers\User\UserStatsController::class, 'getActiveRental']);
    Route::get('/recent-payments', [App\Http\Controllers\User\UserStatsController::class, 'getRecentPayments']);
    Route::get('/documents-status', [App\Http\Controllers\User\UserStatsController::class, 'getDocumentsStatus']);
    Route::get('/profile', [App\Http\Controllers\User\UserStatsController::class, 'getProfile']);
    Route::put('/profile', [App\Http\Controllers\User\UserStatsController::class, 'updateProfile']);
    Route::post('/change-password', [App\Http\Controllers\User\UserStatsController::class, 'changePassword']);
    Route::get('/rental-history', [App\Http\Controllers\User\UserStatsController::class, 'getRentalHistory']);
    Route::get('/payment-history', [App\Http\Controllers\User\UserStatsController::class, 'getPaymentHistory']);
    Route::post('/maintenance', [App\Http\Controllers\User\UserStatsController::class, 'createMaintenanceRequest']);
    Route::get('/maintenance', [App\Http\Controllers\User\UserStatsController::class, 'getMaintenanceRequests']);
    Route::post('/rental-request', [App\Http\Controllers\User\UserRentalController::class, 'submitRentalRequest']);
});

// ============ PUBLIC BUILDING ROUTES ============
Route::prefix('buildings')->group(function () {
    Route::get('/public', [App\Http\Controllers\Admin\BuildingController::class, 'publicIndex']);
    Route::get('/regions', [App\Http\Controllers\Admin\BuildingController::class, 'getRegions']);
    Route::get('/cities', [App\Http\Controllers\Admin\BuildingController::class, 'getCities']);
    Route::get('/{id}', [App\Http\Controllers\Admin\BuildingController::class, 'publicShow']);
});