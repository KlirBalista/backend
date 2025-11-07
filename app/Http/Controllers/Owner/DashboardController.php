<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\BirthCare;
use App\Models\BirthCareStaff;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\Room;
use App\Models\Bed;
use App\Models\PrenatalVisit;
use App\Models\BillPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics for the owner
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            \Log::info('Owner Dashboard Stats - User:', ['user_id' => $user->id, 'email' => $user->email]);
            
            // Get the owner's birthcare facility
            $birthcare = BirthCare::where('user_id', $user->id)->first();
            \Log::info('Owner Dashboard Stats - Birthcare:', ['birthcare' => $birthcare ? $birthcare->toArray() : null]);
            
            if (!$birthcare) {
                return response()->json([
                    'success' => false,
                    'message' => 'No birthcare facility found for this user'
                ], 404);
            }

            $birthcareId = $birthcare->id;
            $currentYear = Carbon::now()->year;
            $currentMonth = Carbon::now()->month;
            $today = Carbon::today();

            // Basic Statistics
            $totalStaff = BirthCareStaff::where('birth_care_id', $birthcareId)->count();
            $totalPatients = Patient::where('birth_care_id', $birthcareId)->count();
            $totalRooms = Room::where('birth_care_id', $birthcareId)->count();
            $totalBeds = Bed::whereHas('room', function($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->count();

            // Current Active Statistics
            $activeAdmissions = PatientAdmission::where('birth_care_id', $birthcareId)
                ->whereIn('status', ['in-labor', 'delivered'])
                ->count();

            $patientsInLabor = PatientAdmission::where('birth_care_id', $birthcareId)
                ->where('status', 'in-labor')
                ->count();

            $patientsDelivered = PatientAdmission::where('birth_care_id', $birthcareId)
                ->where('status', 'delivered')
                ->count();

            // Today's Activity
            $todaysAdmissions = PatientAdmission::where('birth_care_id', $birthcareId)
                ->whereDate('admission_date', $today)
                ->count();

            $todaysDischarges = PatientAdmission::where('birth_care_id', $birthcareId)
                ->where('status', 'discharged')
                ->whereDate('updated_at', $today)
                ->count();

            // This Month's Statistics
            $monthlyAdmissions = PatientAdmission::where('birth_care_id', $birthcareId)
                ->whereYear('admission_date', $currentYear)
                ->whereMonth('admission_date', $currentMonth)
                ->count();

            $monthlyDeliveries = PatientAdmission::where('birth_care_id', $birthcareId)
                ->where('status', 'delivered')
                ->whereYear('updated_at', $currentYear)
                ->whereMonth('updated_at', $currentMonth)
                ->count();

            // Monthly Birth Statistics for Chart (last 6 months)
            $monthlyBirthData = [];
            $totalBirths = 0;
            
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $birthCount = PatientAdmission::where('birth_care_id', $birthcareId)
                    ->where('status', 'delivered')
                    ->whereYear('updated_at', $date->year)
                    ->whereMonth('updated_at', $date->month)
                    ->count();
                
                $monthlyBirthData[] = [
                    'month' => $date->format('M'),
                    'value' => $birthCount,
                    'full_date' => $date->format('Y-m')
                ];
                
                $totalBirths += $birthCount;
            }

            $avgBirthsPerMonth = $totalBirths > 0 ? round($totalBirths / 6, 1) : 0;

            // Recent Admissions (last 5)
            $recentAdmissions = PatientAdmission::where('birth_care_id', $birthcareId)
                ->with(['patient', 'room', 'bed'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function($admission) {
                    return [
                        'id' => $admission->id,
                        'patient_name' => $admission->patient->full_name,
                        'admission_date' => $admission->admission_date->format('M d, Y'),
                        'status' => $admission->status,
                        'room_bed' => ($admission->room->name ?? 'N/A') . ' / Bed ' . ($admission->bed->bed_no ?? 'N/A')
                    ];
                });

            // Capacity Information
            $occupiedBeds = Bed::whereHas('room', function($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->whereHas('patientAdmissions', function($query) {
                $query->whereIn('status', ['in-labor', 'delivered']);
            })->count();

            $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

            // Revenue Statistics (if billing is implemented)
            $monthlyRevenue = BillPayment::whereHas('bill', function($query) use ($birthcareId) {
                $query->where('birthcare_id', $birthcareId);
            })
            ->whereYear('payment_date', $currentYear)
            ->whereMonth('payment_date', $currentMonth)
            ->sum('amount');

            // Prenatal Visit Statistics
            $totalPrenatalVisits = PrenatalVisit::whereHas('patient', function($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })->count();

            $monthlyPrenatalVisits = PrenatalVisit::whereHas('patient', function($query) use ($birthcareId) {
                $query->where('birth_care_id', $birthcareId);
            })
            ->whereYear('scheduled_date', $currentYear)
            ->whereMonth('scheduled_date', $currentMonth)
            ->count();

            // Facility Performance Indicators
            $averageStayDuration = PatientAdmission::where('birth_care_id', $birthcareId)
                ->where('status', 'discharged')
                ->whereNotNull('updated_at')
                ->whereNotNull('admission_date')
                ->get()
                ->average(function($admission) {
                    return Carbon::parse($admission->admission_date)->diffInDays($admission->updated_at);
                });

            $averageStayDuration = $averageStayDuration ? round($averageStayDuration, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'facility_info' => [
                        'name' => $birthcare->name,
                        'status' => $birthcare->status,
                        'is_public' => $birthcare->is_public,
                        'registration_date' => $birthcare->created_at->format('M d, Y')
                    ],
                    'overview' => [
                        'total_staff' => $totalStaff,
                        'total_patients' => $totalPatients,
                        'total_rooms' => $totalRooms,
                        'total_beds' => $totalBeds,
                        'active_admissions' => $activeAdmissions,
                        'patients_in_labor' => $patientsInLabor,
                        'patients_delivered' => $patientsDelivered
                    ],
                    'today_stats' => [
                        'admissions' => $todaysAdmissions,
                        'discharges' => $todaysDischarges
                    ],
                    'monthly_stats' => [
                        'admissions' => $monthlyAdmissions,
                        'deliveries' => $monthlyDeliveries,
                        'prenatal_visits' => $monthlyPrenatalVisits,
                        'revenue' => $monthlyRevenue
                    ],
                    'capacity' => [
                        'total_beds' => $totalBeds,
                        'occupied_beds' => $occupiedBeds,
                        'available_beds' => $totalBeds - $occupiedBeds,
                        'occupancy_rate' => $occupancyRate
                    ],
                    'birth_statistics' => [
                        'monthly_data' => $monthlyBirthData,
                        'total_births' => $totalBirths,
                        'this_month_births' => $monthlyDeliveries,
                        'avg_births_per_month' => $avgBirthsPerMonth
                    ],
                    'performance' => [
                        'total_prenatal_visits' => $totalPrenatalVisits,
                        'average_stay_duration' => $averageStayDuration
                    ],
                    'recent_activity' => $recentAdmissions
                ],
                'generated_at' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Owner Dashboard Stats - Exception:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get staff members for the facility
     */
    public function getStaffMembers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $birthcare = BirthCare::where('user_id', $user->id)->first();

            if (!$birthcare) {
                return response()->json([
                    'success' => false,
                    'message' => 'No birthcare facility found'
                ], 404);
            }

            $staff = BirthCareStaff::where('birth_care_id', $birthcare->id)
                ->with(['user', 'role'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($staffMember) {
                    return [
                        'id' => $staffMember->id,
                        'name' => $staffMember->user->name,
                        'email' => $staffMember->user->email,
                        'role' => $staffMember->role->name ?? 'N/A',
                        'status' => $staffMember->user->email_verified_at ? 'Active' : 'Pending',
                        'joined_at' => $staffMember->created_at->format('M d, Y')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $staff
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff members',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}