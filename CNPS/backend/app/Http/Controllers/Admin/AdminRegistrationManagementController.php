<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Document;
use App\Models\UserRegistrationRequest;
use App\Mail\ApprovedUserMail;
use App\Mail\RejectedUserMail;
use App\Mail\DocumentRejectionMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AdminRegistrationManagementController extends Controller
{
    // STEP 1: Get all pending registration requests (users who submitted demande location only)
    public function pendingRegistrationRequests()
    {
        $pendingUsers = User::with(['documents' => function($query) {
                $query->where('type', 'demandeLocation');
            }])
            ->where('approval_status', 'pending')
            ->where('is_activated', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pending_count' => $pendingUsers->count(),
            'registrations' => $pendingUsers->map(function ($user) {
                $demandeLocation = $user->documents->first();
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->telephone,
                    'id_number' => $user->id_number,
                    'submitted_at' => $user->created_at,
                    'demande_location' => $demandeLocation ? [
                    'file_name' => $demandeLocation->file_name,
                    'file_path' => Storage::url($demandeLocation->file_path),
                    ] : null,
                ];
            })
        ]);
    }

    // STEP 1: Get single registration request details
    public function showRegistration($id)
    {
        $user = User::with(['documents' => function($query) {
                $query->where('type', 'demandeLocation');
            }])->findOrFail($id);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->telephone,
                'id_number' => $user->id_number,
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
                    'uploaded_at' => $doc->created_at
                ];
            }),
        ]);
    }

    // STEP 1: Approve user activation (send temp password via email)
    public function approve(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // Generate temporary password
        $tempPassword = Str::random(10);
        
        // Update user status
        $user->update([
            'approval_status' => 'approved',
            'is_activated' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'password' => Hash::make($tempPassword),
            'status' => 'active'
        ]);

        // Update registration request status
        if ($user->registrationRequest) {
            $user->registrationRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now()
            ]);
        } 

        // Update demande location document status
        Document::where('user_id', $user->id)
            ->where('type', 'demandeLocation')
            ->update(['status' => 'approved']);

        // Send activation email with temporary password
        Mail::to($user->email)->send(new ApprovedUserMail($user, $tempPassword));

        // Log activity
        $request->user()->logActivity('approve_registration', $user->id, null, [
            'user_email' => $user->email,
            'user_name' => $user->name
        ]);

        return response()->json([
            'message' => 'User activated successfully. Temporary credentials sent to user email.'
        ]);
    }

    // STEP 1: Reject registration
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

    // STEP 2: Get pending document submissions (users who completed profile)
    public function pendingDocumentSubmissions()
    {
        $users = User::with(['documents' => function($query) {
                $query->where('type', '!=', 'demandeLocation')
                      ->where('status', 'pending');
            }])
            ->where('has_completed_profile', true)
            ->whereHas('documents', function($query) {
                $query->where('type', '!=', 'demandeLocation')
                      ->where('status', 'pending');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pending_count' => $users->count(),
            'submissions' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'submitted_at' => $user->updated_at,
                    'documents' => $user->documents->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'type' => $this->getDocumentLabel($doc->type),
                            'file_name' => $doc->file_name,
                            'file_path' => Storage::url($doc->file_path),
                            'file_size' => $doc->file_size,
                        ];
                    }),
                ];
            })
        ]);
    }

    // STEP 2: Get single document submission details
    public function showDocumentSubmission($id)
    {
        $user = User::with(['documents' => function($query) {
                $query->where('type', '!=', 'demandeLocation');
            }])->findOrFail($id);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->telephone,
                'id_number' => $user->id_number,
                'user_type' => $user->user_type,
            ],
            'documents' => $user->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => $this->getDocumentLabel($doc->type),
                    'file_name' => $doc->file_name,
                    'file_path' => Storage::url($doc->file_path),
                    'file_size' => $doc->file_size,
                    'status' => $doc->status,
                    'uploaded_at' => $doc->created_at
                ];
            }),
        ]);
    }

    // STEP 2: Approve documents (user can now rent)
    public function approveDocuments(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Update all pending documents to approved
        Document::where('user_id', $user->id)
            ->where('type', '!=', 'demandeLocation')
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        // Log activity
        $request->user()->logActivity('approve_documents', $user->id, null, [
            'user_email' => $user->email,
            'user_name' => $user->name
        ]);

        return response()->json([
            'message' => 'Documents approved successfully. User can now rent spaces.'
        ]);
    }

    // STEP 2: Reject documents
    public function rejectDocuments(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $user = User::findOrFail($id);

        // Update all pending documents to rejected
        Document::where('user_id', $user->id)
            ->where('type', '!=', 'demandeLocation')
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason
            ]);

        // Send rejection email
        Mail::to($user->email)->send(new DocumentRejectionMail($user, $request->reason));

        // Log activity
        $request->user()->logActivity('reject_documents', $user->id, null, [
            'user_email' => $user->email,
            'user_name' => $user->name,
            'rejection_reason' => $request->reason
        ]);

        return response()->json(['message' => 'Documents rejected successfully.']);
    }

public function downloadDocument($documentId)
{
    try {
        // Find the document
        $document = Document::find($documentId);
        
        if (!$document) {
            \Log::error('Document not found in database:', ['document_id' => $documentId]);
            return response()->json(['message' => 'Document not found in database'], 404);
        }
        
        // Log document details
        \Log::info('Document found:', [
            'id' => $document->id,
            'file_name' => $document->file_name,
            'file_path' => $document->file_path,
            'user_id' => $document->user_id,
            'type' => $document->type
        ]);
        
        // Check storage disk configuration
        $disk = Storage::disk('public');
        $fullPath = storage_path('app/public/' . $document->file_path);
        
        \Log::info('Storage paths:', [
            'disk_root' => $disk->path(''),
            'relative_path' => $document->file_path,
            'absolute_path' => $fullPath,
            'file_exists_on_disk' => file_exists($fullPath),
            'storage_exists_check' => $disk->exists($document->file_path)
        ]);
        
        // Check if file exists
        if (!$disk->exists($document->file_path)) {
            \Log::error('File does not exist on disk:', [
                'path' => $document->file_path,
                'absolute_path' => $fullPath,
                'directory_contents' => scandir(dirname($fullPath))
            ]);
            return response()->json([
                'message' => 'File not found on server',
                'path' => $document->file_path
            ], 404);
        }
        
        // Download the file
        return $disk->download($document->file_path, $document->file_name);
        
    } catch (\Exception $e) {
        \Log::error('Exception in downloadDocument:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
    }
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

    // Get approved registrations
public function approvedRegistrations()
{
    $approvedUsers = User::with(['documents' => function($query) {
            $query->where('type', 'demandeLocation');
        }])
        ->where('approval_status', 'approved')
        ->where('is_activated', true)
        ->orderBy('approved_at', 'desc')
        ->get();

    return response()->json([
        'registrations' => $approvedUsers->map(function ($user) {
            $demandeLocation = $user->documents->first();
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->telephone,
                'id_number' => $user->id_number,
                'status' => $user->status,
                'submitted_at' => $user->created_at,
                'approved_at' => $user->approved_at,
                'demande_location' => $demandeLocation ? [
                'file_name' => $demandeLocation->file_name,
                'file_path' => Storage::url($demandeLocation->file_path),
                ] : null,
            ];
        })
    ]);
}

// Get rejected registrations
public function rejectedRegistrations()
{
    $rejectedUsers = User::with(['documents' => function($query) {
            $query->where('type', 'demandeLocation');
        }])
        ->where('approval_status', 'rejected')
        ->orderBy('approved_at', 'desc')
        ->get();

    return response()->json([
        'registrations' => $rejectedUsers->map(function ($user) {
            $demandeLocation = $user->documents->first();
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->telephone,
                'id_number' => $user->id_number,
                'status' => $user->status,
                'submitted_at' => $user->created_at,
                'rejected_at' => $user->approved_at,
                'rejection_reason' => $user->rejection_reason,
                'demande_location' => $demandeLocation ? [
                    'file_name' => $demandeLocation->file_name,
                    'file_path' => Storage::url($demandeLocation->file_path),
                ] : null,
            ];
        })
    ]);
}

// Delete user permanently
public function deleteUser($id)
{
    $user = User::findOrFail($id);
    
    // Delete all user documents from storage
    foreach ($user->documents as $document) {
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }
        $document->delete();
    }
    
    // Delete registration request if exists
    if ($user->registrationRequest) {
        $user->registrationRequest->delete();
    }
    
    // Delete the user
    $userName = $user->name;
    $userEmail = $user->email;
    $user->delete();
    
    // Log activity
    if (auth()->user()) {
        auth()->user()->logActivity('delete_user', null, null, [
            'user_name' => $userName,
            'user_email' => $userEmail
        ]);
    }
    
    return response()->json([
        'message' => 'User deleted successfully'
    ]);
}

// Toggle user suspend status
public function toggleSuspend(Request $request, $id)
{
    $user = User::findOrFail($id);
    
    // Toggle between 'active' and 'suspended'
    $newStatus = $user->status === 'active' ? 'suspended' : 'active';
    
    $user->update([
        'status' => $newStatus
    ]);
    
    // Log activity
    $request->user()->logActivity('toggle_user_suspend', $user->id, null, [
        'user_email' => $user->email,
        'user_name' => $user->name,
        'new_status' => $newStatus
    ]);
    
    return response()->json([
        'message' => $newStatus === 'suspended' ? 'User suspended successfully' : 'User reactivated successfully',
        'status' => $newStatus
    ]);
}

// Get all users with filters (for management)
public function getAllUsers(Request $request)
{
    $query = User::query();
    
    // Filter by status
    if ($request->has('status') && $request->status) {
        $query->where('status', $request->status);
    }
    
    // Filter by approval status
    if ($request->has('approval_status') && $request->approval_status) {
        $query->where('approval_status', $request->approval_status);
    }
    
    // Search by name or email
    if ($request->has('search') && $request->search) {
        $search = '%' . $request->search . '%';
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', $search)
              ->orWhere('email', 'like', $search);
        });
    }
    
    $users = $query->orderBy('created_at', 'desc')->paginate(20);
    
    return response()->json([
        'users' => $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->telephone,
                'status' => $user->status,
                'approval_status' => $user->approval_status,
                'user_type' => $user->user_type,
                'created_at' => $user->created_at,
            ];
        }),
        'pagination' => [
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'total' => $users->total()
        ]
    ]);
}
}