<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AdminRegistrationManagementController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\UserDocumentController;
use App\Http\Controllers\Admin\BuildingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AppartmentController;

// User routes
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::get('/registration-status/{id}', [UserController::class, 'checkStatus']);

// Admin login
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});


// AUTHENTICATED USER ROUTES 
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/me', [UserController::class, 'me']);
    Route::post('/user/logout', [UserController::class, 'logout']);
    Route::post('/user/complete-profile', [UserController::class, 'completeProfile']);
    Route::get('/user/documents-status', [UserController::class, 'getDocumentsStatus']);
    Route::post('/user/documents', [UserDocumentController::class, 'uploadDocuments']);
});

// ADMIN ROUTES (All admin routes in one place)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    
    // ADMIN AUTH
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // 2FA ROUTES
    Route::post('/2fa/enable', [AuthController::class, 'enableTwoFactor']);
    Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor']);
    Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
    
    // DASHBOARD
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // REGISTRATION MANAGEMENT (SPECIFIC ROUTES FIRST, THEN DYNAMIC)
    Route::get('/registrations/pending', [AdminRegistrationManagementController::class, 'pendingRegistrationRequests']);
    Route::get('/registrations/approved', [AdminRegistrationManagementController::class, 'approvedRegistrations']);
    Route::get('/registrations/rejected', [AdminRegistrationManagementController::class, 'rejectedRegistrations']);
    
    // DYNAMIC ROUTE - MUST come AFTER specific routes
    Route::get('/registrations/{id}', [AdminRegistrationManagementController::class, 'showRegistration']);
    
    // ACTION ROUTES
    Route::post('/registrations/{id}/approve', [AdminRegistrationManagementController::class, 'approve']);
    Route::post('/registrations/{id}/reject', [AdminRegistrationManagementController::class, 'reject']);
    
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
    
    // BUILDING MANAGEMENT
    Route::prefix('buildings')->group(function() {
        Route::get('/regions', [BuildingController::class, 'getRegions']);
        Route::get('/cities', [BuildingController::class, 'getCities']);
        Route::get('/', [BuildingController::class, 'index']);
        Route::get('/stats', [BuildingController::class, 'getStatistics']);
        Route::post('/', [BuildingController::class, 'store']);
        Route::get('/{id}', [BuildingController::class, 'show']);
        Route::post('/{id}', [BuildingController::class, 'update']);
        Route::delete('/{id}', [BuildingController::class, 'destroy']);
        Route::put('/{id}/toggle-status', [BuildingController::class, 'toggleStatus']);
        
        // Apartment management within buildings
        Route::prefix('/{buildingId}/appartments')->group(function() {
            Route::get('/', [AppartmentController::class, 'index']);
            Route::get('/{appartmentId}', [AppartmentController::class, 'show']);
            Route::post('/', [AppartmentController::class, 'store']);
            Route::post('/{appartmentId}', [AppartmentController::class, 'update']);
            Route::delete('/{appartmentId}', [AppartmentController::class, 'destroy']);
            Route::post('/{appartmentId}/assign-tenant', [AppartmentController::class, 'assignTenant']);
            Route::post('/{id}/remove-tenant', [AppartmentController::class, 'removeTenant']);
            Route::get('/{id}/stream-video', [AppartmentController::class, 'streamVideo']);
        });
    });
    
    // TENANT MANAGEMENT
    Route::get('/tenants', [AppartmentController::class, 'getAllTenants']);
});

//SUPER ADMIN ROUTES 
Route::middleware(['auth:sanctum', 'admin', 'super_admin'])->prefix('admin/super')->group(function () {
    Route::get('/admins', [AdminRegistrationManagementController::class, 'index']);
    Route::get('/admins/{id}', [AdminRegistrationManagementController::class, 'show']);
    Route::post('/admins', [AdminRegistrationManagementController::class, 'store']);
    Route::put('/admins/{id}', [AdminRegistrationManagementController::class, 'update']);
    Route::delete('/admins/{id}', [AdminRegistrationManagementController::class, 'destroy']);
    Route::post('/admins/{id}/toggle-status', [AdminRegistrationManagementController::class, 'toggleStatus']);
    Route::post('/admins/{id}/reset-password', [AdminRegistrationManagementController::class, 'resetPassword']);
    Route::get('/statistics', [AdminRegistrationManagementController::class, 'statistics']);
    Route::get('/logs', [AdminRegistrationManagementController::class, 'logs']);
    Route::get('/settings', [AdminRegistrationManagementController::class, 'getSettings']);
    Route::post('/settings', [AdminRegistrationManagementController::class, 'updateSettings']);
});