<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Appartment;
use App\Models\RentalRequest;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserRentalController extends Controller
{
    public function submitRentalRequest(Request $request)
    {
        Log::info('=== RENTAL REQUEST SUBMISSION ===');
        Log::info('User: ' . $request->user()->id);
        Log::info('User Email: ' . $request->user()->email);
        Log::info('Data: ' . json_encode($request->all()));
        
        $user = $request->user();

        // VALIDATION 1: Check if user is eligible to submit a rental request
        if($user->approval_status !== 'approved') {
            return response()->json([
                'message' => 'Votre compte n\'a pas encore été approuvé pour soumettre une demande de location. Veuillez attendre l\'approbation de l\'administration.'
            ], 403);
        }

        // VALIDATION 2: Check if user is not suspended
        if($user->status === 'suspended') {
            return response()->json([
                'message' => 'Votre compte est actuellement suspendu. Veuillez contacter l\'administration pour plus d\'informations.'
            ], 403);
        }
        
        // VALIDATION 3: Check if user is activated
        if(!$user->is_activated) {
            return response()->json([
                'message' => 'Votre compte n\'est pas activé. Veuillez vérifier vos emails.'
            ], 403);
        }
        
        // VALIDATION 4: Check if user has completed profile
        if(!$user->has_completed_profile) {
            return response()->json([
                'message' => 'Vous devez d\'abord compléter votre profil avec les documents requis.',
                'redirect' => '/user/complete-profile'
            ], 403);
        }
        
        // Validate request
        $request->validate([
            'apartment_id' => 'required|exists:appartments,id',
            'building_id' => 'required|exists:buildings,id',
            'start_date' => 'required|date',
            'duration' => 'required|integer|min:1|max:60',
            'message' => 'nullable|string',
            'documents' => 'required|array',
            'documents.*' => 'required|file|max:10240' // Max 10MB per file
        ]);
        
        $apartment = Appartment::findOrFail($request->apartment_id);
        
        // Check if apartment is still available
        if ($apartment->is_occupied) {
            return response()->json([
                'message' => 'Cet appartement n\'est plus disponible'
            ], 422);
        }
        
        // Check if THIS USER already has a pending request for this apartment
        $existingRequest = RentalRequest::where('user_id', $user->id)
            ->where('apartment_id', $request->apartment_id)
            ->where('status', 'pending')
            ->first();
            
        if ($existingRequest) {
            return response()->json([
                'message' => 'Vous avez déjà une demande en cours pour cet appartement'
            ], 422);
        }
        
        // Save documents and get their IDs
        $documentIds = [];
        foreach ($request->file('documents') as $type => $file) {
            $path = $file->store('rental_requests/' . $user->id . '/' . date('Y/m/d'), 'public');
            
            $document = Document::create([
                'user_id' => $user->id,
                'type' => $type,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'status' => 'pending'
                // rental_request_id will be set after rental request is created
            ]);
            
            $documentIds[] = $document->id;
        }
        
        // Create rental request
        $rentalRequest = RentalRequest::create([
            'user_id' => $user->id,
            'apartment_id' => $request->apartment_id,
            'building_id' => $request->building_id,
            'start_date' => $request->start_date,
            'duration' => $request->duration,
            'message' => $request->message,
            'document_ids' => json_encode($documentIds),
            'status' => 'pending'
        ]);
        
        // Update documents with the rental_request_id
        Document::whereIn('id', $documentIds)->update(['rental_request_id' => $rentalRequest->id]);
        
        Log::info('Rental request created: ' . $rentalRequest->id);
        Log::info('Documents saved: ' . json_encode($documentIds));
        Log::info('Documents linked to rental request: ' . $rentalRequest->id);
        
        return response()->json([
            'message' => 'Demande de location soumise avec succès. L\'administration examinera votre demande et vous contactera pour les étapes suivantes.',
            'request_id' => $rentalRequest->id
        ], 201);
    }
}