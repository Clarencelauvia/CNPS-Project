<?php
// app/Http/Controllers/Admin/DashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Models\Building;
use App\Models\Apartment;
use App\Models\RentalContract;
use App\Models\Payment;
use App\Models\MaintenanceRequest;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get all dashboard statistics
     */
    public function index(Request $request)
    {
        $admin = $request->user();
        
        // Log that admin viewed dashboard
        $admin->logActivity('view_dashboard');
        
        // Get all statistics
        $stats = $this->getStatistics();
        $revenueData = $this->getRevenueData();
        $recentActivities = $this->getRecentActivities();
        $occupancyData = $this->getOccupancyData();
        $paymentStatus = $this->getPaymentStatus();
        $pendingDocuments = $this->getPendingDocuments();
        
        return response()->json([
            'admin' => $admin,
            'stats' => $stats,
            'revenue_data' => $revenueData,
            'occupancy_data' => $occupancyData,
            'payment_status' => $paymentStatus,
            'recent_activities' => $recentActivities,
            'pending_documents' => $pendingDocuments,
        ]);
    }
    
    /**
     * Get main statistics for dashboard cards
     */
    private function getStatistics()
    {
        // Total users (tenants)
        $totalUsers = User::count();
        
        // Active rental contracts
        $activeContracts = RentalContract::where('status', 'active')
            ->where('end_date', '>=', Carbon::now())
            ->count();
        
        // Total buildings
        $totalBuildings = Building::count();
        
        // Total apartments
        $totalApartments = Apartment::count();
        
        // Occupied apartments
        $occupiedApartments = RentalContract::where('status', 'active')
            ->where('end_date', '>=', Carbon::now())
            ->distinct('apartment_id')
            ->count('apartment_id');
        
        // Monthly revenue (current month)
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereYear('payment_date', Carbon::now()->year)
            ->whereMonth('payment_date', Carbon::now()->month)
            ->sum('amount');
        
        // Pending payments (overdue or pending)
        $pendingPayments = Payment::whereIn('status', ['pending', 'overdue'])
            ->sum('amount');
        
        // Pending document approvals
        $pendingDocuments = Document::where('status', 'pending')->count();
        
        // Active maintenance requests
        $activeMaintenance = MaintenanceRequest::whereIn('status', ['pending', 'in_progress'])->count();
        
        // Total revenue (all time)
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        
        // Occupancy rate
        $occupancyRate = $totalApartments > 0 
            ? round(($occupiedApartments / $totalApartments) * 100, 1) 
            : 0;
        
        return [
            'total_users' => $totalUsers,
            'active_contracts' => $activeContracts,
            'total_buildings' => $totalBuildings,
            'total_apartments' => $totalApartments,
            'occupied_apartments' => $occupiedApartments,
            'available_apartments' => $totalApartments - $occupiedApartments,
            'occupancy_rate' => $occupancyRate,
            'monthly_revenue' => $monthlyRevenue,
            'pending_payments' => $pendingPayments,
            'total_revenue' => $totalRevenue,
            'pending_documents' => $pendingDocuments,
            'active_maintenance' => $activeMaintenance,
        ];
    }
    
    /**
     * Get revenue data for charts (last 12 months)
     */
    private function getRevenueData()
    {
        $months = [];
        $revenues = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = $date->format('M Y');
            
            $revenue = Payment::where('status', 'completed')
                ->whereYear('payment_date', $date->year)
                ->whereMonth('payment_date', $date->month)
                ->sum('amount');
            
            $revenues[] = $revenue;
        }
        
        // Get revenue by building
        $revenueByBuilding = Building::with(['apartments.rentalContracts.payments' => function($query) {
            $query->where('status', 'completed')
                  ->whereYear('payment_date', Carbon::now()->year)
                  ->whereMonth('payment_date', Carbon::now()->month);
        }])->get()->map(function($building) {
            $revenue = 0;
            foreach ($building->apartments as $apartment) {
                foreach ($apartment->rentalContracts as $contract) {
                    if ($contract->status === 'active') {
                        $revenue += $contract->monthly_rent;
                    }
                }
            }
            return [
                'name' => $building->name,
                'revenue' => $revenue,
            ];
        });
        
        return [
            'labels' => $months,
            'values' => $revenues,
            'by_building' => $revenueByBuilding,
        ];
    }
    
    /**
     * Get occupancy data by building
     */
    private function getOccupancyData()
    {
        $buildings = Building::with('apartments.rentalContracts')->get();
        
        $occupancyData = [];
        foreach ($buildings as $building) {
            $totalApartments = $building->apartments->count();
            $occupiedApartments = 0;
            
            foreach ($building->apartments as $apartment) {
                $hasActiveContract = $apartment->rentalContracts
                    ->where('status', 'active')
                    ->where('end_date', '>=', Carbon::now())
                    ->isNotEmpty();
                    
                if ($hasActiveContract) {
                    $occupiedApartments++;
                }
            }
            
            $occupancyData[] = [
                'building' => $building->name,
                'total' => $totalApartments,
                'occupied' => $occupiedApartments,
                'available' => $totalApartments - $occupiedApartments,
                'occupancy_rate' => $totalApartments > 0 
                    ? round(($occupiedApartments / $totalApartments) * 100, 1) 
                    : 0,
            ];
        }
        
        return $occupancyData;
    }
    
    /**
     * Get payment status distribution
     */
    private function getPaymentStatus()
    {
        $thisMonth = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();
        
        $completed = Payment::where('status', 'completed')
            ->whereYear('payment_date', $thisMonth->year)
            ->whereMonth('payment_date', $thisMonth->month)
            ->count();
            
        $pending = Payment::where('status', 'pending')
            ->whereYear('due_date', $thisMonth->year)
            ->whereMonth('due_date', $thisMonth->month)
            ->count();
            
        $overdue = Payment::where('status', 'overdue')
            ->where('due_date', '<', Carbon::now())
            ->count();
        
        // Get payment trends (comparison with last month)
        $lastMonthCompleted = Payment::where('status', 'completed')
            ->whereYear('payment_date', $lastMonth->year)
            ->whereMonth('payment_date', $lastMonth->month)
            ->count();
        
        $trend = $lastMonthCompleted > 0 
            ? round((($completed - $lastMonthCompleted) / $lastMonthCompleted) * 100, 1)
            : 0;
        
        return [
            'labels' => ['Payés', 'En attente', 'En retard'],
            'data' => [$completed, $pending, $overdue],
            'total_payments' => $completed + $pending + $overdue,
            'completion_rate' => ($completed + $pending + $overdue) > 0
                ? round(($completed / ($completed + $pending + $overdue)) * 100, 1)
                : 0,
            'trend_vs_last_month' => $trend,
        ];
    }
    
    /**
     * Get recent activities (last 10 activities)
     */
    private function getRecentActivities()
    {
        $activities = collect();
        
        // Recent user registrations
        $recentUsers = User::with('documents')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($user) {
                return [
                    'type' => 'user_registered',
                    'title' => 'Nouvel utilisateur inscrit',
                    'description' => "{$user->name} ({$user->email}) s'est inscrit",
                    'icon' => 'user-plus',
                    'color' => 'green',
                    'created_at' => $user->created_at,
                ];
            });
        
        // Recent payments
        $recentPayments = Payment::with('user')
            ->orderBy('payment_date', 'desc')
            ->take(5)
            ->get()
            ->map(function($payment) {
                return [
                    'type' => 'payment_received',
                    'title' => 'Paiement reçu',
                    'description' => "{$payment->user->name} a payé " . number_format($payment->amount, 0, ',', ' ') . " FCFA",
                    'icon' => 'money-bill',
                    'color' => 'blue',
                    'created_at' => $payment->payment_date,
                ];
            });
        
        // Recent document submissions
        $recentDocuments = Document::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($document) {
                $statusText = $document->status === 'pending' ? 'en attente' : 
                             ($document->status === 'approved' ? 'approuvé' : 'rejeté');
                return [
                    'type' => 'document_submitted',
                    'title' => 'Document soumis',
                    'description' => "{$document->user->name} a soumis des documents ({$statusText})",
                    'icon' => 'file-alt',
                    'color' => 'yellow',
                    'created_at' => $document->created_at,
                ];
            });
        
        // Recent maintenance requests
        $recentMaintenance = MaintenanceRequest::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($request) {
                $statusText = $request->status === 'pending' ? 'en attente' :
                             ($request->status === 'in_progress' ? 'en cours' : 'terminé');
                return [
                    'type' => 'maintenance_request',
                    'title' => 'Demande de maintenance',
                    'description' => "{$request->user->name}: {$request->title} ({$statusText})",
                    'icon' => 'tools',
                    'color' => 'orange',
                    'created_at' => $request->created_at,
                ];
            });
        
        // Recent rental contracts
        $recentContracts = RentalContract::with('user', 'apartment.building')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($contract) {
                return [
                    'type' => 'contract_signed',
                    'title' => 'Nouveau contrat',
                    'description' => "{$contract->user->name} a signé un contrat pour {$contract->apartment->building->name}",
                    'icon' => 'file-signature',
                    'color' => 'purple',
                    'created_at' => $contract->created_at,
                ];
            });
        
        // Merge all activities and sort by date
        $activities = $recentUsers
            ->concat($recentPayments)
            ->concat($recentDocuments)
            ->concat($recentMaintenance)
            ->concat($recentContracts)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
        
        return $activities;
    }
    
    /**
     * Get pending documents for approval
     */
    private function getPendingDocuments()
    {
        return Document::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function($document) {
                return [
                    'id' => $document->id,
                    'user' => [
                        'id' => $document->user->id,
                        'name' => $document->user->name,
                        'email' => $document->user->email,
                    ],
                    'type' => $document->type,
                    'file_name' => $document->file_name,
                    'submitted_at' => $document->created_at,
                    'status' => $document->status,
                ];
            });
    }
    
    /**
     * Get detailed revenue analytics
     */
    public function revenueAnalytics(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);
        
        $monthlyRevenue = [];
        $buildingRevenue = [];
        
        // Monthly revenue for the year
        for ($month = 1; $month <= 12; $month++) {
            $revenue = Payment::where('status', 'completed')
                ->whereYear('payment_date', $year)
                ->whereMonth('payment_date', $month)
                ->sum('amount');
                
            $monthlyRevenue[] = [
                'month' => Carbon::create($year, $month, 1)->format('F'),
                'revenue' => $revenue,
            ];
        }
        
        // Revenue by building
        $buildings = Building::with(['apartments.rentalContracts.payments' => function($query) use ($year) {
            $query->where('status', 'completed')
                  ->whereYear('payment_date', $year);
        }])->get();
        
        foreach ($buildings as $building) {
            $total = 0;
            foreach ($building->apartments as $apartment) {
                foreach ($apartment->rentalContracts as $contract) {
                    $total += $contract->payments->sum('amount');
                }
            }
            
            $buildingRevenue[] = [
                'building' => $building->name,
                'revenue' => $total,
            ];
        }
        
        return response()->json([
            'year' => $year,
            'monthly_revenue' => $monthlyRevenue,
            'building_revenue' => $buildingRevenue,
            'total_revenue' => array_sum(array_column($monthlyRevenue, 'revenue')),
        ]);
    }
    
    /**
     * Get user growth analytics
     */
    public function userGrowth(Request $request)
    {
        $months = $request->get('months', 12);
        
        $growth = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
                
            $growth[] = [
                'month' => $date->format('M Y'),
                'new_users' => $count,
                'cumulative' => User::where('created_at', '<=', $date->endOfMonth())->count(),
            ];
        }
        
        return response()->json([
            'growth_data' => $growth,
            'total_users' => User::count(),
        ]);
    }
    
    /**
     * Get export data for reports
     */
    public function exportReport(Request $request)
    {
        $type = $request->get('type', 'monthly'); // monthly, quarterly, yearly
        $period = $request->get('period', Carbon::now()->format('Y-m'));
        
        $report = [];
        
        switch ($type) {
            case 'monthly':
                $date = Carbon::createFromFormat('Y-m', $period);
                $report = $this->generateMonthlyReport($date);
                break;
            case 'quarterly':
                // Implement quarterly report
                break;
            case 'yearly':
                // Implement yearly report
                break;
        }
        
        return response()->json($report);
    }
    
    /**
     * Generate monthly report
     */
    private function generateMonthlyReport(Carbon $date)
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        
        $newUsers = User::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $newContracts = RentalContract::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $revenue = Payment::where('status', 'completed')
            ->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $paymentsCount = Payment::whereBetween('payment_date', [$startOfMonth, $endOfMonth])->count();
        $maintenanceRequests = MaintenanceRequest::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        
        return [
            'period' => $date->format('F Y'),
            'new_users' => $newUsers,
            'new_contracts' => $newContracts,
            'revenue' => $revenue,
            'payments_count' => $paymentsCount,
            'maintenance_requests' => $maintenanceRequests,
            'occupancy_rate' => $this->getStatistics()['occupancy_rate'],
        ];
    }

    
}