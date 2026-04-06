<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Document;
use App\Models\UserRegistrationRequest;
use App\Mail\ApprovedUserMail;
use App\Mail\RejectedUserMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AdminRegistrationManagementController extends Controller
{
    // get all the pending registration requests
    public function pendingRegistrationRequests()
    {
           $pendingUsers = User::with(['documents', 'registrationRequest'])
            ->where('approval_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pending_count' => $pendingUsers->count(),
            'registration' => $pendingUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->telephone,
                    'id_number' => $user->id_number,
                    'user_type' => $user->user_type,
                    'submitted_at' => $user->created_at,
                    'documents' => $user->documents->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'type' => $doc->type,
                            'file_name' => $doc->file_name,
                             'file_path' => Storage::url($doc->file_path),
                            'file_size' => $doc->file_size,
                        ];
                    }),
                  
                ];
            })

        ]);
    }

    // get single registration request details
    public function showRegistration($id)
    {
        $user = User::with(['documents', 'registrationRequest'])->findOrFail($id);

        return response()->json([
     'users' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->telephone,
            'id_number' => $user->id_number,
            'user_type' => $user->user_type,
            'submitted_at' => $user->created_at,
            'approval_status' => $user->approval_status,
     ],
            'documents' => $user->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => $this->getDocumentLabel($doc->type),
                    'file_name' => $doc->file_name,
                     'file_path' => Storage::url($doc->file_path),
                    'file_size' => $doc->file_size,
                    'Uploaded_at' => $doc->created_at
                ];
            }),
           
        ]);
    }

    // approve registration and send email notif
public function approve(Request $request, $id)  // Added Request $request
{
    $request->validate([
        'password' => 'required|string|min:8'
    ]);

    $user = User::findOrFail($id);
    
    // Update user status
    $user->update([
        'approval_status' => 'approved',
        'approved_at' => now(),
        'approved_by' => $request->user()->id,
        'password' => Hash::make($request->password)
    ]);

    // Update registration request status
    if ($user->registrationRequest) {
        $user->registrationRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now()
        ]);
    } 

    // Update documents status
    Document::where('user_id', $user->id)->update(['status' => 'approved']);

    // Send approval email
    Mail::to($user->email)->send(new ApprovedUserMail($user, $request->password));

    // Log activity
    $request->user()->logActivity('approve_registration', $user->id, null, [
        'user_email' => $user->email,
        'user_name' => $user->name
    ]);

    return response()->json([
        'message' => 'User registration approved successfully. Credentials sent to user email.'
    ]);
}

    // reject registration and send email notif 
   public function reject(Request $request, $id)
{
    $request->validate([
        'reason' => 'required|string|max:500'
    ]);

    $user = User::findOrFail($id);

    // Update user status
    $user->update([
        'approval_status' => 'rejected',
        'approved_at' => now(), 
        'approved_by' => $request->user()->id,  
        'rejection_reason' => $request->reason
    ]);

    // Update registration request status
    if ($user->registrationRequest) {
        $user->registrationRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'admin_notes' => $request->reason  
        ]);
    }

    // Update documents status
    Document::where('user_id', $user->id)->update(['status' => 'rejected']);

    // Send rejection email
    Mail::to($user->email)->send(new RejectedUserMail($user, $request->reason));

    // Log activity
    $request->user()->logActivity('reject_registration', $user->id, null, [
        'user_email' => $user->email,
        'user_name' => $user->name,
        'rejection_reason' => $request->reason
    ]);

    return response()->json(['message' => 'User registration rejected successfully.']);
}

    // download document 
    public function downloadDocument($documentId)
    {
        $document = Document::findOrFail($documentId);
  if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

     private function getDocumentLabel($type)
    {
        $labels = [
            'demandeLocation' => 'Demande de location',
            'preuveVersement' => 'Preuve de versement',
            'dossierFiscale' => 'Dossier fiscal',
            'dsfPrecedent' => 'DSF exercice précédent',
            'rib' => 'Relevé d\'identité bancaire',
            'extraitCompte' => 'Extrait de compte',
            'troisBulletinsPaie' => '3 derniers bulletins de paie',
            'attestationFiscale' => 'Attestation fiscale',
            'carteProfessionnelle' => 'Carte professionnelle'
        ];

        return $labels[$type] ?? $type;
    }
}
