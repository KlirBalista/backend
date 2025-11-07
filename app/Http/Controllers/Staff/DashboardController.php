<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\Room;
use App\Models\Bed;
use App\Models\BirthCare;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for a birth care facility
     */
    public function getStatistics(Request $request, $birthcare_id): JsonResponse
    {
        try {
            // Verify birth care exists
            $birthCare = BirthCare::findOrFail($birthcare_id);

            // Get current date for filtering
            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();
            $startOfWeek = Carbon::now()->startOfWeek();

            // Patient Statistics
            $totalPatients = Patient::where('birth_care_id', $birthcare_id)->count();
            $totalActiveAdmissions = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereIn('status', ['in-labor', 'delivered'])
                ->count();

            // Status-specific counts
            $patientsInLabor = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->where('status', 'in-labor')
                ->count();

            $patientsDelivered = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->where('status', 'delivered')
                ->count();

            $patientsDischargedToday = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->where('status', 'discharged')
                ->whereDate('updated_at', $today)
                ->count();

            // Recent admissions (today)
            $todaysAdmissions = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->whereDate('admission_date', $today)
                ->count();

            // Room and Bed Statistics
            $totalRooms = Room::where('birth_care_id', $birthcare_id)->count();
            $totalBeds = Bed::whereHas('room', function($query) use ($birthcare_id) {
                $query->where('birth_care_id', $birthcare_id);
            })->count();

            $occupiedBeds = Bed::whereHas('room', function($query) use ($birthcare_id) {
                $query->where('birth_care_id', $birthcare_id);
            })->whereHas('patientAdmissions', function($query) {
                $query->whereIn('status', ['in-labor', 'delivered']);
            })->count();

            $availableBeds = $totalBeds - $occupiedBeds;
            $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

            // Weekly and Monthly Trends
            $weeklyAdmissions = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->where('admission_date', '>=', $startOfWeek)
                ->count();

            $monthlyAdmissions = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->where('admission_date', '>=', $startOfMonth)
                ->count();

            $monthlyDeliveries = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->where('status', 'delivered')
                ->where('updated_at', '>=', $startOfMonth)
                ->count();

            // Recent Activity - Last 5 admissions
            $recentAdmissions = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->with(['patient', 'room', 'bed'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($admission) {
                    return [
                        'id' => $admission->id,
                        'patient_name' => $admission->patient->full_name,
                        'admission_date' => $admission->admission_date->format('M d, Y'),
                        'admission_time' => $admission->admission_time ? $admission->admission_time->format('H:i') : null,
                        'status' => $admission->status,
                        'room_name' => $admission->room->name ?? 'N/A',
                        'bed_no' => $admission->bed->bed_no ?? 'N/A',
                        'status_color' => $admission->status_color,
                    ];
                });

            // Room occupancy details
            $roomOccupancy = Room::where('birth_care_id', $birthcare_id)
                ->with(['beds.currentPatientAdmission.patient'])
                ->get()
                ->map(function ($room) {
                    $totalBeds = $room->beds->count();
                    $occupiedBeds = $room->beds->filter(function ($bed) {
                        return $bed->is_occupied;
                    })->count();

                    return [
                        'id' => $room->id,
                        'name' => $room->name,
                        'total_beds' => $totalBeds,
                        'occupied_beds' => $occupiedBeds,
                        'available_beds' => $totalBeds - $occupiedBeds,
                        'occupancy_rate' => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0,
                        'is_fully_occupied' => $occupiedBeds >= $totalBeds && $totalBeds > 0,
                    ];
                });

            // Critical alerts
            $alerts = [];

            // Check for overdue discharges (patients delivered more than 3 days ago)
            $overdueDischarges = PatientAdmission::where('birth_care_id', $birthcare_id)
                ->where('status', 'delivered')
                ->where('updated_at', '<=', Carbon::now()->subDays(3))
                ->count();

            if ($overdueDischarges > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "{$overdueDischarges} patient(s) may be overdue for discharge",
                    'count' => $overdueDischarges
                ];
            }

            // Check for high occupancy
            if ($occupancyRate >= 90) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'High occupancy rate - consider capacity planning',
                    'count' => $occupancyRate
                ];
            }

            // Check for rooms at full capacity
            $fullRooms = $roomOccupancy->filter(function ($room) {
                return $room['is_fully_occupied'];
            })->count();

            if ($fullRooms > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'message' => "{$fullRooms} room(s) at full capacity",
                    'count' => $fullRooms
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_patients' => $totalPatients,
                        'active_admissions' => $totalActiveAdmissions,
                        'patients_in_labor' => $patientsInLabor,
                        'patients_delivered' => $patientsDelivered,
                        'todays_admissions' => $todaysAdmissions,
                        'todays_discharges' => $patientsDischargedToday,
                    ],
                    'capacity' => [
                        'total_rooms' => $totalRooms,
                        'total_beds' => $totalBeds,
                        'occupied_beds' => $occupiedBeds,
                        'available_beds' => $availableBeds,
                        'occupancy_rate' => $occupancyRate,
                    ],
                    'trends' => [
                        'weekly_admissions' => $weeklyAdmissions,
                        'monthly_admissions' => $monthlyAdmissions,
                        'monthly_deliveries' => $monthlyDeliveries,
                    ],
                    'recent_activity' => $recentAdmissions,
                    'room_occupancy' => $roomOccupancy,
                    'alerts' => $alerts,
                ],
                'generated_at' => Carbon::now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get quick stats for mini dashboard widgets
     */
    public function getQuickStats(Request $request, $birthcare_id): JsonResponse
    {
        try {
            $today = Carbon::today();

            $stats = [
                'active_admissions' => PatientAdmission::where('birth_care_id', $birthcare_id)
                    ->whereIn('status', ['in-labor', 'delivered'])
                    ->count(),
                'in_labor' => PatientAdmission::where('birth_care_id', $birthcare_id)
                    ->where('status', 'in-labor')
                    ->count(),
                'delivered_today' => PatientAdmission::where('birth_care_id', $birthcare_id)
                    ->where('status', 'delivered')
                    ->whereDate('updated_at', $today)
                    ->count(),
                'available_beds' => Bed::whereHas('room', function($query) use ($birthcare_id) {
                    $query->where('birth_care_id', $birthcare_id);
                })->whereDoesntHave('patientAdmissions', function($query) {
                    $query->whereIn('status', ['in-labor', 'delivered']);
                })->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quick statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily admission trends for charts
     */
    public function getAdmissionTrends(Request $request, $birthcare_id): JsonResponse
    {
        try {
            $days = $request->get('days', 7); // Default to 7 days
            $startDate = Carbon::now()->subDays($days - 1);

            $trends = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $admissionCount = PatientAdmission::where('birth_care_id', $birthcare_id)
                    ->whereDate('admission_date', $date)
                    ->count();

                $trends[] = [
                    'date' => $date->format('M d'),
                    'admissions' => $admissionCount,
                    'full_date' => $date->format('Y-m-d'),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $trends,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve admission trends',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}