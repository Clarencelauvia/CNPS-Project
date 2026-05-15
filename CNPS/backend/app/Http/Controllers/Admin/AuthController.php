<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'two_factor_code' => 'nullable|string|size:6',
        ]);

        // Attempt login
        if (!Auth::guard('admin')->attempt([
            'email' => $request->email,
            'password' => $request->password,
        ])) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect',
                'errors' => [
                    'email' => ['Les identifiants sont incorrects']
                ]
            ], 422);
        }

        $admin = Auth::guard('admin')->user();

        if (!$admin->is_active) {
            Auth::guard('admin')->logout();
            return response()->json([
                'message' => 'Votre compte est désactivé.',
                'errors' => [
                    'email' => ['Ce compte a été désactivé']
                ]
            ], 422);
        }

        // Check if 2FA is enabled
        if ($admin->two_factor_enabled) {
            if (!$request->two_factor_code) {
                return response()->json([
                    'requires_2fa' => true,
                    'message' => 'Code 2FA requis'
                ], 200);
            }
            
            if (!$admin->verifyTwoFactorCode($request->two_factor_code)) {
                return response()->json([
                    'message' => 'Code 2FA invalide',
                    'errors' => [
                        'two_factor_code' => ['Code invalide']
                    ]
                ], 422);
            }
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
                'is_active' => $admin->is_active,
                'two_factor_enabled' => $admin->two_factor_enabled ?? false,
            ],
            'token' => $token,
            'message' => 'Login successful',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('admin')->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request)
    {
        $admin = $request->user('admin');
        
        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'is_active' => $admin->is_active,
        ]);
    }
}