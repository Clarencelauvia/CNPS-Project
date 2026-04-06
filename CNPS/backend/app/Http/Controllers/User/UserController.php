<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\User;
use App\Models\UserRegistrationRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
      public function register(Request $request)
    {
        $validated = $request->validate([
            'user_type' => 'required|in:morale,salarie,non_salarie',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string',
            'id_number' => 'required|string',
            'password' => 'required|min:8|confirmed',
            'documents' => 'required|array',
        ]);

        // create user with pending approval status
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telephone' => $validated['phone'],
            'id_number' => $validated['id_number'],
            'user_type' => $validated['user_type'],
            'password' => Hash::make($validated['password']),
            'approval_status' => 'pending',
        ]);

        // save documents
        foreach ($request->documents as $docType => $file) {
                $path = $file->store('user_documents/' . $user->id, 'public');
            
            Document::create([
                'user_id' => $user->id,
                'type' => $docType,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending'
            ]);
        }

        // create  registration request record
           UserRegistrationRequest::create([
            'user_id' => $user->id,
            'user_type' => $validated['user_type'],
            'personal_info' => [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'id_number' => $validated['id_number'],
            ],
            'status' => 'pending'
        ]);
        return response()->json([
            'message' => 'Registration submitted successfully. Awaiting admin approval.',
            'user_id' => $user->id
        ], 201);

        return response()->json(['message' => 'User registered successfully'], 201);
    }

        public function checkStatus($id)
    {
        $user = User::findOrFail($id);
        
        return response()->json([
            'approval_status' => $user->approval_status,
            'rejection_reason' => $user->rejection_reason
        ]);
    }

}
