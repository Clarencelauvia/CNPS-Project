<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;

class UserDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Upload additional documents after activation
    public function uploadDocuments(Request $request)
    {
        $user = Auth::user();

        // Check if user is activated
        if (!$user->is_activated) {
            return response()->json([
                'message' => 'Your account is not activated yet.'
            ], 403);
        }

        $request->validate([
            'user_type' => 'required|in:morale,salarie,non_salarie',
            'documents' => 'required|array',
        ]);

        $requiredDocs = $this->getRequiredDocuments($request->user_type);

        foreach ($request->documents as $docType => $file) {
            if (in_array($docType, $requiredDocs)) {
                $path = $file->store('user_documents/' . $user->id . '/additional', 'public');
                
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
        }

        // Update user type
        $user->update(['user_type' => $request->user_type]);

        return response()->json([
            'message' => 'Documents submitted successfully. Awaiting admin approval.'
        ]);
    }

    private function getRequiredDocuments($userType)
    {
        $documents = [
            'morale' => [
                'preuveVersement', 'dossierFiscale', 'dsfPrecedent', 
                'rib', 'extraitCompte'
            ],
            'salarie' => [
                'preuveVersement', 'troisBulletinsPaie', 
                'attestationFiscale', 'rib'
            ],
            'non_salarie' => [
                'preuveVersement', 'rib', 'attestationFiscale', 
                'extraitCompte', 'carteProfessionnelle'
            ],
        ];

        return $documents[$userType] ?? [];
    }
}