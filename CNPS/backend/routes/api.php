
<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminRegistrationManagementController;
use App\Http\Controllers\User\UserController;
// test route 
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working!',
        'timestamp' => now()->toDateTimeString()
    ]);
});


// Public routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected admin routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // 2FA routes
    Route::post('/2fa/enable', [AuthController::class, 'enableTwoFactor']);
    Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor']);
    Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Super admin only routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin', 'super_admin'])->group(function () {
    // Add super admin specific routes here
    // Example: Route::get('/admins', [AdminController::class, 'index']);
});

// Public routes
Route::post('/register', [UserController::class, 'register']);
Route::get('/registration-status/{id}', [UserController::class, 'checkStatus']);

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/registrations/pending', [AdminRegistrationManagementController::class, 'pendingRegistrationsRequests']);
    Route::get('/registrations/{id}', [AdminRegistrationManagementController::class, 'showRegistration']);
    Route::post('/registrations/{id}/approve', [AdminRegistrationManagementController::class, 'approve']);
    Route::post('/registrations/{id}/reject', [AdminRegistrationManagementController::class, 'reject']);
    Route::get('/documents/{id}/download', [AdminRegistrationManagementController::class, 'downloadDocument']);
});