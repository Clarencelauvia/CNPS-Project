<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Document;
use App\Models\RentalContract;
use App\Models\Payment;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserStatsController extends Controller
{
  
    
    /**
     * Get user statistics for dashboard
     */
    public function getStats(Request $request)
    {
        $user = $request->user();

        // Get active rental contracts
        $activeContracts = RentalContract::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', Carbon::now())
            ->count();

        // Get total payments made
        $totalPayments = Payment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');

        // Get pending payments
        $pendingPayments = Payment::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        // Get maintenance requests count
        $maintenanceRequests = MaintenanceRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        return response()->json([
            'activeContracts' => $activeContracts,
            'totalPayments' => (float) $totalPayments,
            'pendingPayments' => (float) $pendingPayments,
            'maintenanceRequests' => $maintenanceRequests,
        ]);
    }

    /**
     * Get user's active rental information
     */
    public function getActiveRental(Request $request)
    {
        $user = $request->user();

        $activeContract = RentalContract::with(['apartment.building'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_date', '>=', Carbon::now())
            ->first();

        if (!$activeContract) {
            return response()->json(['rental' => null]);
        }

        return response()->json([
            'rental' => [
                'id' => $activeContract->id,
                'apartment_id' => $activeContract->apartment_id,
                'apartment_number' => $activeContract->apartment->appartment_number ?? $activeContract->apartment->apartment_number,
                'building_id' => $activeContract->apartment->building_id,
                'building_name' => $activeContract->apartment->building->name,
                'rent_amount' => (float) $activeContract->monthly_rent,
                'start_date' => $activeContract->start_date,
                'end_date' => $activeContract->end_date,
            ]
        ]);
    }

    /**
     * Get recent payments for user
     */
    public function getRecentPayments(Request $request)
    {
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'month' => Carbon::parse($payment->payment_date)->format('F Y'),
                    'date' => $payment->payment_date,
                    'status' => $payment->status,
                    'reference' => $payment->reference,
                ];
            });

        return response()->json([
            'payments' => $payments
        ]);
    }

    /**
     * Get user's documents status
     */
    public function getDocumentsStatus(Request $request)
    {
        $user = $request->user();

        // Check demandeLocation document status
        $demandeLocation = Document::where('user_id', $user->id)
            ->where('type', 'demandeLocation')
            ->first();

        // Check other documents status (rental application documents)
        $otherDocuments = Document::where('user_id', $user->id)
            ->where('type', '!=', 'demandeLocation')
            ->get();

        $hasPending = $otherDocuments->contains('status', 'pending');
        $hasApproved = $otherDocuments->contains('status', 'approved');
        $hasRejected = $otherDocuments->contains('status', 'rejected');

        $status = 'none';
        if ($hasPending) {
            $status = 'pending';
        } elseif ($hasApproved) {
            $status = 'approved';
        } elseif ($hasRejected) {
            $status = 'rejected';
        }

        return response()->json([
            'status' => $status,
            'demande_location_status' => $demandeLocation ? $demandeLocation->status : 'none',
            'documents' => $otherDocuments->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'status' => $doc->status,
                    'file_name' => $doc->file_name,
                    'uploaded_at' => $doc->created_at,
                ];
            }),
        ]);
    }

    /**
     * Get user's complete profile information
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telephone' => $user->telephone,
            'id_number' => $user->id_number,
            'address' => $user->address,
            'user_type' => $user->user_type,
            'approval_status' => $user->approval_status,
            'has_completed_profile' => $user->has_completed_profile,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'telephone' => 'sometimes|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    /**
     * Get user's rental history
     */
    public function getRentalHistory(Request $request)
    {
        $user = $request->user();

        $contracts = RentalContract::with(['apartment.building'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($contract) {
                return [
                    'id' => $contract->id,
                    'apartment_number' => $contract->apartment->appartment_number ?? $contract->apartment->apartment_number,
                    'building_name' => $contract->apartment->building->name,
                    'building_city' => $contract->apartment->building->city,
                    'monthly_rent' => (float) $contract->monthly_rent,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'status' => $contract->status,
                    'terminated_at' => $contract->terminated_at,
                    'termination_reason' => $contract->termination_reason,
                ];
            });

        return response()->json([
            'contracts' => $contracts
        ]);
    }

    /**
     * Get payment history
     */
    public function getPaymentHistory(Request $request)
    {
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->orderBy('payment_date', 'desc')
            ->paginate(20)
            ->through(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'month' => Carbon::parse($payment->payment_date)->format('F Y'),
                    'date' => $payment->payment_date,
                    'status' => $payment->status,
                    'reference' => $payment->reference,
                    'method' => $payment->payment_method ?? 'N/A',
                ];
            });

        return response()->json($payments);
    }

    /**
     * Create a maintenance request
     */
    public function createMaintenanceRequest(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'apartment_id' => 'required|exists:appartments,id',
            'priority' => 'required|in:low,medium,high',
        ]);

        $maintenance = MaintenanceRequest::create([
            'user_id' => $request->user()->id,
            'apartment_id' => $request->apartment_id,
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return response()->json([
            'message' => 'Maintenance request submitted successfully',
            'maintenance' => $maintenance
        ], 201);
    }

    /**
     * Get user's maintenance requests
     */
    public function getMaintenanceRequests(Request $request)
    {
        $user = $request->user();

        $requests = MaintenanceRequest::where('user_id', $user->id)
            ->with('apartment.building')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'title' => $request->title,
                    'description' => $request->description,
                    'priority' => $request->priority,
                    'status' => $request->status,
                    'apartment_number' => $request->apartment->appartment_number ?? $request->apartment->apartment_number,
                    'building_name' => $request->apartment->building->name,
                    'requested_at' => $request->requested_at,
                    'resolved_at' => $request->resolved_at,
                ];
            });

        return response()->json([
            'maintenance_requests' => $requests
        ]);
    }
}