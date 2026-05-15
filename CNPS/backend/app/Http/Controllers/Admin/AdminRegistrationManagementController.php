<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Document;
use App\Models\UserRegistrationRequest;
use App\Models\Appartment;
use App\Mail\AccountCreationApprovedMail;
use App\Mail\AccountCreationRejectedMail;
use App\Mail\RentalRequestApprovedMail;
use App\Mail\RentalRequestRejectedMail;
use Illuminate\Http\Request;
use App\Models\RentalRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminRegistrationManagementController extends Controller
{
    // ============ ACCOUNT CREATION REQUESTS (Step 1) ============
    
    public function pendingAccountCreations()
    {
        try {
            $pendingUsers = User::where('approval_status', 'pending')
                ->where('is_activated', false)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'registrations' => $pendingUsers->map(function ($user) {
                    $demandeLocation = Document::where('user_id', $user->id)
                        ->where('type', 'demandeLocation')
                        ->first();
                        
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->telephone,
                        'id_number' => $user->id_number,
                        'submitted_at' => $user->created_at,
                        'type' => 'account_creation',
                        'status' => 'pending',
                        'demande_location' => $demandeLocation ? [
                            'file_name' => $demandeLocation->file_name,
                            'file_path' => Storage::url($demandeLocation->file_path),
                        ] : null,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('pendingAccountCreations error: ' . $e->getMessage());
            return response()->json(['registrations' => []]);
        }
    }

    public function approvedAccountCreations()
    {
        try {
            $approvedUsers = User::where('approval_status', 'approved')
                ->where('is_activated', true)
                ->orderBy('approved_at', 'desc')
                ->get();

            return response()->json([
                'registrations' => $approvedUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->telephone,
                        'id_number' => $user->id_number,
                        'submitted_at' => $user->created_at,
                        'approved_at' => $user->approved_at,
                        'type' => 'account_creation',
                        'status' => 'approved',
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('approvedAccountCreations error: ' . $e->getMessage());
            return response()->json(['registrations' => []]);
        }
    }

    public function rejectedAccountCreations()
    {
        try {
            $rejectedUsers = User::where('approval_status', 'rejected')
                ->orderBy('approved_at', 'desc')
                ->get();

            return response()->json([
                'registrations' => $rejectedUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->telephone,
                        'id_number' => $user->id_number,
                        'submitted_at' => $user->created_at,
                        'rejected_at' => $user->approved_at,
                        'rejection_reason' => $user->rejection_reason,
                        'type' => 'account_creation',
                        'status' => 'rejected',
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('rejectedAccountCreations error: ' . $e->getMessage());
            return response()->json(['registrations' => []]);
        }
    }

    public function approveAccountCreation(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            
            $tempPassword = Str::random(10);
            
            $user->update([
                'approval_status' => 'approved',
                'is_activated' => true,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'password' => Hash::make($tempPassword),
                'status' => 'active'
            ]);

            Document::where('user_id', $user->id)
                ->where('type', 'demandeLocation')
                ->update(['status' => 'approved']);

            Mail::to($user->email)->send(new AccountCreationApprovedMail($user, $tempPassword));

            return response()->json([
                'message' => 'Account created successfully. Temporary credentials sent to user email.'
            ]);
        } catch (\Exception $e) {
            Log::error('approveAccountCreation error: ' . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function rejectAccountCreation(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            $user = User::findOrFail($id);

            $user->update([
                'approval_status' => 'rejected',
                'approved_at' => now(), 
                'approved_by' => $request->user()->id,  
                'rejection_reason' => $request->reason
            ]);

            Document::where('user_id', $user->id)->update(['status' => 'rejected']);
            Mail::to($user->email)->send(new AccountCreationRejectedMail($user, $request->reason));

            return response()->json(['message' => 'Account creation request rejected successfully.']);
        } catch (\Exception $e) {
            Log::error('rejectAccountCreation error: ' . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ============ RENTAL REQUESTS (Step 2) ============

public function pendingRentalRequests()
{
    try {
        $rentalRequests = RentalRequest::with(['user', 'apartment', 'building'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
        
        \Log::info('Fetched ' . $rentalRequests->count() . ' pending rental requests');

        return response()->json([
            'registrations' => $rentalRequests->map(function ($rentalRequest) {  // ← Fixed: use $rentalRequest
                return [
                    'id' => $rentalRequest->id,  // ← Fixed
                    'name' => $rentalRequest->user->name,  // ← Fixed
                    'email' => $rentalRequest->user->email,  // ← Fixed
                    'phone' => $rentalRequest->user->telephone ?? 'Non renseigné',
                    'id_number' => $rentalRequest->user->id_number ?? 'Non renseigné',
                    'user_type' => $rentalRequest->user->user_type,
                    'apartment' => $rentalRequest->apartment->apartment_number ?? 'N/A',
                    'building' => $rentalRequest->building->name ?? 'N/A',
                    'start_date' => $rentalRequest->start_date ? $rentalRequest->start_date->format('Y-m-d') : 'N/A',
                    'duration' => $rentalRequest->duration,
                    'message' => $rentalRequest->message,
                    'submitted_at' => $rentalRequest->created_at,
                    'type' => 'rental_request',
                    'status' => 'pending',
                ];
            })
        ]);
    } catch (\Exception $e) {
        \Log::error('pendingRentalRequests error: ' . $e->getMessage());
        return response()->json(['registrations' => []]);
    }
}

public function approvedRentalRequests()
{
    try {
        $rentalRequests = RentalRequest::with(['user', 'apartment', 'building'])
            ->where('status', 'approved')
            ->orderBy('reviewed_at', 'desc')
            ->get();
            
        return response()->json([
            'registrations' => $rentalRequests->map(function ($rentalRequest) {
                return [
                    'id' => $rentalRequest->id,
                    'name' => $rentalRequest->user->name,
                    'email' => $rentalRequest->user->email,
                    'phone' => $rentalRequest->user->telephone ?? 'Non renseigné',
                    'id_number' => $rentalRequest->user->id_number ?? 'Non renseigné',
                    'user_type' => $rentalRequest->user->user_type,
                    'apartment' => $rentalRequest->apartment->apartment_number ?? 'N/A',
                    'building' => $rentalRequest->building->name ?? 'N/A',
                    'submitted_at' => $rentalRequest->created_at,
                    'reviewed_at' => $rentalRequest->reviewed_at,
                    'type' => 'rental_request',
                    'status' => 'approved',
                ];
            })
        ]);
    } catch (\Exception $e) {
        \Log::error('approvedRentalRequests error: ' . $e->getMessage());
        return response()->json(['registrations' => []]);
    }
}

public function rejectedRentalRequests()
{
    try {
        $rentalRequests = RentalRequest::with(['user', 'apartment', 'building'])
            ->where('status', 'rejected')
            ->orderBy('reviewed_at', 'desc')
            ->get();

        return response()->json([
            'registrations' => $rentalRequests->map(function ($rentalRequest) {
                return [
                    'id' => $rentalRequest->id,
                    'name' => $rentalRequest->user->name,
                    'email' => $rentalRequest->user->email,
                    'phone' => $rentalRequest->user->telephone ?? 'Non renseigné',
                    'id_number' => $rentalRequest->user->id_number ?? 'Non renseigné',
                    'user_type' => $rentalRequest->user->user_type,
                    'apartment' => $rentalRequest->apartment->apartment_number ?? 'N/A',
                    'building' => $rentalRequest->building->name ?? 'N/A',
                    'submitted_at' => $rentalRequest->created_at,
                    'reviewed_at' => $rentalRequest->reviewed_at,
                    'rejection_reason' => $rentalRequest->admin_notes,
                    'type' => 'rental_request',
                    'status' => 'rejected',
                ];
            })
        ]);
    } catch (\Exception $e) {
        \Log::error('rejectedRentalRequests error: ' . $e->getMessage());
        return response()->json(['registrations' => []]);
    }
}

    public function approveRentalRequest(Request $request, $id)
    {
        try {
           $rentalRequest = RentalRequest::with(['user', 'apartment'])->findOrFail($id);

        //    Update rental request status
        $rentalRequest->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'admin_notes' => $request->admin_notes ?? null
        ]);

        // Update document status for this rental request
        if($rentalRequest->document_ids){
            Document::whereIn('id', $rentalRequest->document_ids)
                ->where('status', 'pending')
                ->update(['status' => 'approved']);
         
        }
        // Mark apartment as occupied
        $apartment = Appartment::find($rentalRequest->apartment_id);
        if ($apartment) {
            $apartment->update(['is_occupied' => true]);}
            return response()->json([
                'message' => 'Rental request approved successfully. Apartment marked as occupied.'
            ]);
        } catch (\Exception $e) {
            Log::error('approveRentalRequest error: ' . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function rejectRentalRequest(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            $rentalRequest = RentalRequest::with(['user'])->findOrFail($id);
            // Update rental request status
            $rentalRequest->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'admin_notes' => $request->reason
            ]);

            // Update document status for this rental request
            if($rentalRequest->document_ids){
                Document::whereIn('id', $rentalRequest->document_ids)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected',
                    'rejection_reason' => $request->reason
                ]);
            }

            // Mail::to($user->email)->send(new RentalRequestRejectedMail($user, $request->reason));

            return response()->json(['message' => 'Rental request rejected successfully.']);
        } catch (\Exception $e) {
            Log::error('rejectRentalRequest error: ' . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ============ SHARED METHODS ============

    public function downloadDocument($documentId)
    {
        try {
            $document = Document::findOrFail($documentId);
            $disk = Storage::disk('public');
            
            if (!$disk->exists($document->file_path)) {
                return response()->json(['message' => 'File not found'], 404);
            }
            
            return $disk->download($document->file_path, $document->file_name);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
public function pendingDocumentSubmissions()
{
    try {
        // Get all pending documents with their rental request info
        $documents = Document::with(['user', 'rentalRequest'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'documents' => $documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'user_id' => $document->user->id,
                    'user_name' => $document->user->name,
                    'user_email' => $document->user->email,
                    'rental_request_id' => $document->rental_request_id ?? null,
                    'apartment_number' => $document->rentalRequest?->apartment?->apartment_number ?? 'N/A',
                    'building_name' => $document->rentalRequest?->building?->name ?? 'N/A',
                    'type' => $document->type,
                    'type_label' => $this->getDocumentLabel($document->type),
                    'file_name' => $document->file_name,
                    'file_url' => $document->file_path ? Storage::url($document->file_path) : null,
                    'submitted_at' => $document->created_at,
                ];
            })
        ]);
    } catch (\Exception $e) {
        \Log::error('pendingDocumentSubmissions error: ' . $e->getMessage());
        return response()->json(['documents' => []], 500);
    }
}

public function showDocumentSubmission($id){
    try {
        $document = Document::with('user')->findOrFail($id);
        return response()->json([
            'id' => $document->id,
            'user_name' => $document->user ->name,
            'user_email' => $document->user->email,
            'type' =>$document->type,
            'type_label' => $this->getDocumentLabel($document->type),
            'file_name' => $document->file_name,
            'file_url' => Storage::url($document->file_path),
            'submitted_at' => $document->created_at,
            'status' => $document->status,
        ]);
    } catch (\Exception $e) {
        \Log::error('showDocumentSubmission error: ' . $e->getMessage());
        return response()->json(['message' => 'Document not found'], 404);
    }
}

public function approveDocuments(Request $request, $id){
    try {
        $document = Document::findOrFail($id);
        $document->update([
            'status' => 'approved'
        ]);
        return response()->json(['message' => 'Document approved successfully.']);
    } catch (\Exception $e) {
        \Log::error('approveDocuments error: ' . $e->getMessage());
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}
public function rejectDocuments(Request $request, $id){
    try {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $document = Document::findOrFail($id);
        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason
        ]);
        return response()->json(['message' => 'Document rejected successfully.']);
    } catch (\Exception $e) {
        \Log::error('rejectDocuments error: ' . $e->getMessage());
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}
public function showRegistration($id)
{
    try {
        // First try to find as RentalRequest
        $rentalRequest = RentalRequest::with(['user', 'documents'])->find($id);
        
        if ($rentalRequest) {
            // It's a rental request
            $user = $rentalRequest->user;
            $documents = $rentalRequest->documents;
            
            return response()->json([
                'id' => $rentalRequest->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->telephone,
                'id_number' => $user->id_number,
                'submitted_at' => $rentalRequest->created_at,
                'approval_status' => $rentalRequest->status,
                'user_type' => $user->user_type,
                'type' => 'rental_request',
                'apartment' => $rentalRequest->apartment ? $rentalRequest->apartment->apartment_number : 'N/A',
                'building' => $rentalRequest->building ? $rentalRequest->building->name : 'N/A',
                'start_date' => $rentalRequest->start_date,
                'duration' => $rentalRequest->duration,
                'message' => $rentalRequest->message,
                'documents' => $documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'type' => $this->getDocumentLabel($doc->type),
                        'file_name' => $doc->file_name,
                        'file_path' => Storage::url($doc->file_path),
                        'status' => $doc->status,
                    ];
                }),
            ]);
        }
        
        // If not found as rental request, try as user (account creation)
        $user = User::with(['documents'])->findOrFail($id);
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->telephone,
            'id_number' => $user->id_number,
            'submitted_at' => $user->created_at,
            'approval_status' => $user->approval_status,
            'user_type' => $user->user_type,
            'type' => 'account_creation',
            'documents' => $user->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => $this->getDocumentLabel($doc->type),
                    'file_name' => $doc->file_name,
                    'file_path' => Storage::url($doc->file_path),
                    'status' => $doc->status,
                ];
            }),
        ]);
        
    } catch (\Exception $e) {
        \Log::error('showRegistration error: ' . $e->getMessage());
        return response()->json(['message' => 'Request not found'], 404);
    }
}

    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            
            foreach ($user->documents as $document) {
                if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }
                $document->delete();
            }
            
            $user->delete();
            
            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function getDocumentLabel($type)
    {
        $labels = [
            'demandeLocation' => 'Demande de création de compte',
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

    // Legacy methods for backward compatibility
    public function pendingRegistrationRequests() { return $this->pendingAccountCreations(); }
    public function approvedRegistrations() { return $this->approvedAccountCreations(); }
    public function rejectedRegistrations() { return $this->rejectedAccountCreations(); }
    public function approve(Request $request, $id) { return $this->approveAccountCreation($request, $id); }
    public function reject(Request $request, $id) { return $this->rejectAccountCreation($request, $id); }
}