<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\UserActivationMail;

class UserController extends Controller
{
    // STEP 1: Register with only basic info + demande location
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string',
            'id_number' => 'required|string',
            'demandeLocation' => 'required|file|mimes:pdf,jpeg,png,jpg|max:5120',
        ]);

        $tempPassword = Str::random(10);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'telephone' => $request->phone,
            'id_number' => $request->id_number,
            'password' => Hash::make($tempPassword),
            'approval_status' => 'pending',
            'is_activated' => false,
        ]);

        // Save demande location
        // $path = $request->file('demandeLocation')->store('user_documents/' . $user->id, 'public');
        $file = $request->file('demandeLocation');
        $path = $file->store('user_documents/' . $user->id, 'public');
        Document::create([
            'user_id' => $user->id,
            'type' => 'demandeLocation',
            'file_path' => $path,
            'file_name' => $request->file('demandeLocation')->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),        
            'file_size' => $file->getSize(),
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Registration submitted. Check your email for login credentials.',
            'user_id' => $user->id
        ], 201);
    }

    // STEP 2: Login (check if approved)
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->approval_status !== 'approved') {
            return response()->json([
                'message' => $user->approval_status === 'pending' 
                    ? 'Your account is pending approval. You will receive an email once approved.'
                    : 'Your account was rejected. Contact administration.',
                'status' => $user->approval_status
            ], 403);
        }

        // Delete old tokens and create new one
        $user->tokens()->delete();
        $token = $user->createToken('user-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->telephone,
                'approval_status' => $user->approval_status,
                'has_completed_profile' => $user->has_completed_profile,
            ],
            'token' => $token,
            'needs_profile_completion' => !$user->has_completed_profile
        ]);
    }

    // STEP 3: Complete profile (upload other documents)
    public function completeProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'user_type' => 'required|in:morale,salarie,non_salarie',
            'documents' => 'required|array',
        ]);

        $requiredDocs = [
            'morale' => ['preuveVersement', 'dossierFiscale', 'dsfPrecedent', 'rib', 'extraitCompte'],
            'salarie' => ['preuveVersement', 'troisBulletinsPaie', 'attestationFiscale', 'rib'],
            'non_salarie' => ['preuveVersement', 'rib', 'attestationFiscale', 'extraitCompte', 'carteProfessionnelle'],
        ];

        foreach ($request->documents as $type => $file) {
            if (in_array($type, $requiredDocs[$request->user_type])) {
                $path = $file->store('user_documents/' . $user->id, 'public');
                Document::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'status' => 'pending'
                ]);
            }
        }

        $user->update([
            'user_type' => $request->user_type,
            'has_completed_profile' => true
        ]);

        return response()->json(['message' => 'Documents submitted for approval']);
    }

    

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}