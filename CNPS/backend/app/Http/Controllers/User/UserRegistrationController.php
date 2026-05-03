<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Document;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRegistrationController extends Controller
{
    // Step 1: Initial registration (basic info + demande location only)
    public function initialRegister(Request $request)
    {
        \Log::info('Initial registration request received', [
            'email' => $request->email,
        ]);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'phone' => 'required|string',
                'id_number' => 'required|string',
                'demandeLocation' => 'required|file|mimes:pdf,jpeg,png,jpg|max:5120', // 5MB max
            ]);

            // Generation of temporary activation token
            $activationToken = Str::random(60);

            // Creation user with pending activation status
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'telephone' => $validated['phone'],
                'id_number' => $validated['id_number'],
                'approval_status' => 'pending',
                'is_activated' => false,
                'activation_token' => $activationToken,
                'password' => Hash::make(Str::random(40)), // Random temporary password
            ]);

            // Save the demande location document
            $file = $request->file('demandeLocation');
            $path = $file->store('user_documents/' . $user->id . '/initial', 'public');
            
            Document::create([
                'user_id' => $user->id,
                'type' => 'demandeLocation',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending'
            ]);

            \Log::info('Initial registration successful', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'Registration submitted successfully. Awaiting admin approval.',
                'user_id' => $user->id,
                'status' => 'pending'
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Initial registration error', ['message' => $e->getMessage()]);
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    // Check activation status
    public function checkActivationStatus($id)
    {
        $user = User::findOrFail($id);
        
        return response()->json([
            'is_activated' => $user->is_activated,
            'approval_status' => $user->approval_status,
            'message' => $user->is_activated ? 'Your account is activated. Please set your password.' : 'Awaiting activation.'
        ]);
    }

    // Set password after activation
    public function setPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
            'activation_token' => 'required|string'
        ]);

        $user = User::where('id', $id)
            ->where('activation_token', $request->activation_token)
            ->where('is_activated', true)
            ->firstOrFail();

        $user->update([
            'password' => Hash::make($request->password),
            'activation_token' => null
        ]);

        return response()->json([
            'message' => 'Password set successfully. You can now login.'
        ]);
    }
}