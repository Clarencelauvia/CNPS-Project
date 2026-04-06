<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\AdminFailedLoginAttempt;
use App\Models\AdminLoginLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // Admin Login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'two_factor_code' => 'nullable|string|size:6',
        ]);

        // Rate limiting
        $key = 'admin-login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => ['Trop de tentatives. Réessayez dans ' . RateLimiter::availableIn($key) . ' secondes.'],
            ]);
        }

        // Find admin
        $admin = Admin::where('email', $request->email)->first();

        //  CASE 1: Admin NOT found
        if (!$admin) {
            RateLimiter::hit($key, 60);

            AdminFailedLoginAttempt::create([
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempted_at' => now(),
            ]);

            Log::error('Admin login failed - email not found', [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Email introuvable.'],
            ]);
        }

        //  CASE 2: Wrong password
        if (!Hash::check($request->password, $admin->password)) {
            RateLimiter::hit($key, 60);

            AdminFailedLoginAttempt::create([
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempted_at' => now(),
            ]);

            AdminLoginLog::create([
                'admin_id' => $admin->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'successful' => false,
                'failure_reason' => 'Mot de passe incorrect',
            ]);

            Log::warning('Admin login failed - wrong password', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Mot de passe incorrect.'],
            ]);
        }

        // CASE 3: Inactive account
        if (!$admin->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Votre compte est désactivé.'],
            ]);
        }

        //  2FA
        if ($admin->two_factor_enabled) {
            if (!$request->two_factor_code) {
                return response()->json([
                    'requires_2fa' => true,
                    'message' => 'Code 2FA requis',
                ]);
            }

            if (!$admin->verifyTwoFactorCode($request->two_factor_code)) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['Code 2FA invalide.'],
                ]);
            }
        }

        //  SUCCESS

        RateLimiter::clear($key);

        AdminLoginLog::create([
            'admin_id' => $admin->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'successful' => true,
        ]);

        $token = $admin->createToken('admin-token', ['admin:access'])->plainTextToken;

        $admin->logActivity('login', null, null, [
            'ip' => $request->ip()
        ]);

        return response()->json([
            'admin' => $admin,
            'token' => $token,
            'message' => 'Connexion réussie',
        ]);
    }
}