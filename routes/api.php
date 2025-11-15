<?php

use App\Http\Controllers\Admin\BirthcareApplicationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Owner\BirthcareController;
use App\Http\Controllers\PublicApiController;
use App\Http\Controllers\Staff\BirthCareRoleController;
use App\Http\Controllers\Staff\PermissionController;
use App\Http\Controllers\Staff\RoomController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SubscriptionPaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated or user not found'], 401);
    }

    if ($user->system_role_id == 3) {
        $user->load('birthCareStaff.birthCare');
        $user->permissions = $user->permissions()->get()->pluck('name')->toArray();
    }

    if ($user->system_role_id == 2) {
        $user->load('birthCare');
    }
    return $user;
});

// User profile update route
Route::middleware(['auth:sanctum'])->put('/user/profile', [\App\Http\Controllers\UserProfileController::class, 'update']);
    
Route::get('/plans', [SubscriptionPlanController::class, 'index']);
Route::get('/plans/{plan}', [SubscriptionPlanController::class, 'show']);

// PayMongo webhook (no auth required)
Route::post('/subscription/webhook', [SubscriptionPaymentController::class, 'webhook']);

// Public API for map - fetch all registered birthcare facilities
Route::get('/birthcares', [BirthcareController::class, 'getAllRegistered']);

// Public API for patient search and consultation history
Route::get('/patients/search', [PublicApiController::class, 'searchPatients']);
Route::get('/patients/{patientId}/consultations', [PublicApiController::class, 'getPatientConsultations']);

// Public feedback routes (no authentication required)
Route::get('/feedback/public', [FeedbackController::class, 'public']);
Route::post('/feedback', [FeedbackController::class, 'store']);
Route::get('/feedback/stats', [FeedbackController::class, 'stats']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/plans', [SubscriptionPlanController::class, 'store']);
    Route::put('/plans/{plan}', [SubscriptionPlanController::class, 'update']);
    Route::delete('/plans/{plan}', [SubscriptionPlanController::class, 'destroy']);
    
    // Subscription payment routes
    Route::post('/subscription/checkout', [SubscriptionPaymentController::class, 'createCheckout']);
    Route::get('/subscription/payment-status/{sessionId}', [SubscriptionPaymentController::class, 'checkStatus']);
    Route::post('/subscription/cancel/{sessionId}', [SubscriptionPaymentController::class, 'cancelSession']);
    
    // Owner routes - require authentication
    Route::prefix('owner')->group(function () {
        // Subscription routes (no subscription middleware needed)
        Route::get('/subscription', [BirthcareController::class, 'getSubscription']);
        
        // Birthcare registration routes (no subscription middleware needed for initial setup)
        Route::get('/birthcare', [BirthcareController::class, 'getBirthcare']);
        Route::post('/register-birthcare', [BirthcareController::class, 'register']);
        Route::get('/birthcare/approval-status', [BirthcareController::class, 'checkApprovalStatus']);
        Route::post('/birthcare/{id}/documents', [BirthcareController::class, 'updateDocuments']);
        Route::post('/birthcare/{id}/resubmit', [BirthcareController::class, 'resubmit']);
        
        // Routes that require active subscription
        Route::middleware(['subscription.active'])->group(function () {
            // Birthcare management
            Route::put('/birthcare/{id}', [BirthcareController::class, 'update']);
            
            // Dashboard Statistics
            Route::get('/dashboard/statistics', [\App\Http\Controllers\Owner\DashboardController::class, 'getStatistics']);
            Route::get('/dashboard/staff', [\App\Http\Controllers\Owner\DashboardController::class, 'getStaffMembers']);
        });
    });
    
    // Admin routes - require authentication and admin role
    Route::prefix('admin')->group(function () {
        // User management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        
        // Birthcare applications
        Route::get('/birthcare-applications', [BirthcareApplicationController::class, 'index']);
        Route::get('/birthcare-applications/{id}', [BirthcareApplicationController::class, 'show']);
        Route::post('/birthcare-applications/{id}/approve', [BirthcareApplicationController::class, 'approve']);
        Route::post('/birthcare-applications/{id}/reject', [BirthcareApplicationController::class, 'reject']);
        
        // Document download
        Route::get('/birthcare-documents/{id}/download', [BirthcareApplicationController::class, 'downloadDocument']);
        
        // Feedback management
        Route::get('/feedback', [FeedbackController::class, 'index']);
        Route::get('/feedback/{feedback}', [FeedbackController::class, 'show']);
        Route::patch('/feedback/{feedback}/status', [FeedbackController::class, 'updateStatus']);
        Route::delete('/feedback/{feedback}', [FeedbackController::class, 'destroy']);
        
        // Subscription transactions
        Route::get('/transactions', [\App\Http\Controllers\Admin\TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [\App\Http\Controllers\Admin\TransactionController::class, 'show']);
    });
    
    // Permission routes
    Route::get('/permissions', [PermissionController::class, 'index']);
    
    // All birthcare operational routes require active subscription
    Route::middleware(['subscription.active'])->group(function () {
        // BirthCare Role management routes
        Route::get('/birthcare/{birthcare_id}/roles', [BirthCareRoleController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/roles', [BirthCareRoleController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/roles/{role}', [BirthCareRoleController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/roles/{role}', [BirthCareRoleController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/roles/{role}', [BirthCareRoleController::class, 'destroy']);

        // Staff management routes
        Route::get('/birthcare/{birthcare_id}/staff', [\App\Http\Controllers\Staff\StaffController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/staff', [\App\Http\Controllers\Staff\StaffController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/staff/{staff}', [\App\Http\Controllers\Staff\StaffController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/staff/{staff}', [\App\Http\Controllers\Staff\StaffController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/staff/{staff}', [\App\Http\Controllers\Staff\StaffController::class, 'destroy']);
        
        // Patient management routes
        Route::get('/birthcare/{birthcare_id}/patients', [\App\Http\Controllers\Staff\PatientController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/patients', [\App\Http\Controllers\Staff\PatientController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/patients/{patient}', [\App\Http\Controllers\Staff\PatientController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/patients/{patient}', [\App\Http\Controllers\Staff\PatientController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/patients/{patient}', [\App\Http\Controllers\Staff\PatientController::class, 'destroy']);
        
        // Prenatal visit management routes
        Route::get('/birthcare/{birthcare_id}/prenatal-visits', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/prenatal-visits', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'store']);
        Route::post('/birthcare/{birthcare_id}/prenatal-visits/batch', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'batchStore']);
        Route::get('/birthcare/{birthcare_id}/prenatal-visits/{visit}', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/prenatal-visits/{visit}', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'update']);
        Route::patch('/birthcare/{birthcare_id}/prenatal-visits/{visit}/status', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'updateStatus']);
        Route::get('/birthcare/{birthcare_id}/prenatal-visits/{visit}/debug-status', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'debugStatus']);
        Route::delete('/birthcare/{birthcare_id}/prenatal-visits/{visit}', [\App\Http\Controllers\Staff\PrenatalVisitController::class, 'destroy']);
        
        // Visit logs routes
        Route::get('/birthcare/{birthcare_id}/visit-logs', [\App\Http\Controllers\Staff\VisitLogController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/visit-logs', [\App\Http\Controllers\Staff\VisitLogController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/visit-logs/export', [\App\Http\Controllers\Staff\VisitLogController::class, 'export']);
        
        // Prenatal form routes
        Route::get('/birthcare/{birthcare_id}/prenatal-forms', [\App\Http\Controllers\Staff\PrenatalFormController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/prenatal-forms', [\App\Http\Controllers\Staff\PrenatalFormController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/prenatal-forms/{form}', [\App\Http\Controllers\Staff\PrenatalFormController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/prenatal-forms/{form}', [\App\Http\Controllers\Staff\PrenatalFormController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/prenatal-forms/{form}', [\App\Http\Controllers\Staff\PrenatalFormController::class, 'destroy']);
        
        // Patient documents routes
        Route::get('/birthcare/{birthcare_id}/patient-documents', [\App\Http\Controllers\PatientDocumentController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/patient-documents', [\App\Http\Controllers\PatientDocumentController::class, 'store']);
        Route::post('/birthcare/{birthcare_id}/patient-documents/from-data', [\App\Http\Controllers\PatientDocumentController::class, 'storeFromData']);
        Route::get('/birthcare/{birthcare_id}/patient-documents/{document}', [\App\Http\Controllers\PatientDocumentController::class, 'show']);
        Route::get('/birthcare/{birthcare_id}/patient-documents/{document}/download', [\App\Http\Controllers\PatientDocumentController::class, 'download']);
        Route::get('/birthcare/{birthcare_id}/patient-documents/{document}/view', [\App\Http\Controllers\PatientDocumentController::class, 'view']);
        Route::delete('/birthcare/{birthcare_id}/patient-documents/{document}', [\App\Http\Controllers\PatientDocumentController::class, 'destroy']);
        
        // Patient-specific document routes
        Route::get('/birthcare/{birthcare_id}/patients/{patient}/documents', [\App\Http\Controllers\PatientDocumentController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/patients/{patient}/documents', [\App\Http\Controllers\PatientDocumentController::class, 'store']);
        
        // Patient admission routes
        Route::get('/birthcare/{birthcare_id}/patient-admissions', [\App\Http\Controllers\PatientAdmissionController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/patient-admissions', [\App\Http\Controllers\PatientAdmissionController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/patient-admissions/{admission}', [\App\Http\Controllers\PatientAdmissionController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/patient-admissions/{admission}', [\App\Http\Controllers\PatientAdmissionController::class, 'update']);
        Route::patch('/birthcare/{birthcare_id}/patient-admissions/{admission}/status', [\App\Http\Controllers\PatientAdmissionController::class, 'updateStatus']);
        Route::delete('/birthcare/{birthcare_id}/patient-admissions/{admission}', [\App\Http\Controllers\PatientAdmissionController::class, 'destroy']);
        
        // Prenatal scheduling routes (legacy)
        Route::get('/birthcare/{birthcare_id}/prenatal-calendar', [\App\Http\Controllers\Staff\PatientController::class, 'getCalendarData']);
        Route::get('/birthcare/{birthcare_id}/todays-visits', [\App\Http\Controllers\Staff\PatientController::class, 'getTodaysVisits']);
        
        // Patient referral routes
        Route::get('/birthcare/{birthcare_id}/referrals', [\App\Http\Controllers\ReferralController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/referrals', [\App\Http\Controllers\ReferralController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/referrals/stats', [\App\Http\Controllers\ReferralController::class, 'stats']);
        Route::get('/birthcare/{birthcare_id}/referrals/{referral}', [\App\Http\Controllers\ReferralController::class, 'show']);
        Route::get('/birthcare/{birthcare_id}/referrals/{referral}/pdf', [\App\Http\Controllers\ReferralController::class, 'generatePDF']);
        Route::put('/birthcare/{birthcare_id}/referrals/{referral}', [\App\Http\Controllers\ReferralController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/referrals/{referral}', [\App\Http\Controllers\ReferralController::class, 'destroy']);
        
        // Patient charges routes
        Route::get('/birthcare/{birthcare_id}/billing', [\App\Http\Controllers\BillingController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/billing', [\App\Http\Controllers\BillingController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/billing/{charge}', [\App\Http\Controllers\BillingController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/billing/{charge}', [\App\Http\Controllers\BillingController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/billing/{charge}', [\App\Http\Controllers\BillingController::class, 'destroy']);
        
        // Patient charging routes
        Route::get('/birthcare/{birthcare_id}/patient-charges/admitted-patients', [\App\Http\Controllers\PatientChargeController::class, 'getAdmittedPatients']);
        Route::get('/birthcare/{birthcare_id}/patient-charges/services', [\App\Http\Controllers\PatientChargeController::class, 'getMedicalServices']);
        Route::post('/birthcare/{birthcare_id}/patient-charges/charge', [\App\Http\Controllers\PatientChargeController::class, 'chargePatient']);
        Route::get('/birthcare/{birthcare_id}/patient-charges/bill-summary/{patient_id}', [\App\Http\Controllers\PatientChargeController::class, 'getPatientBillSummary']);
        
        // Enhanced patient charging routes
        Route::get('/birthcare/{birthcare_id}/patient-charges/patient-bills/{patient_id}', [\App\Http\Controllers\PatientChargeController::class, 'getPatientBills']);
        Route::post('/birthcare/{birthcare_id}/patient-charges/apply-discount', [\App\Http\Controllers\PatientChargeController::class, 'applyDiscount']);
        Route::post('/birthcare/{birthcare_id}/patient-charges/finalize-bill', [\App\Http\Controllers\PatientChargeController::class, 'finalizeBill']);
        Route::post('/birthcare/{birthcare_id}/patient-charges/bulk-charge', [\App\Http\Controllers\PatientChargeController::class, 'bulkChargePatients']);

        // Payments routes
        Route::get('/birthcare/{birthcare_id}/payments/dashboard', [\App\Http\Controllers\PaymentsController::class, 'dashboard']);
        Route::get('/birthcare/{birthcare_id}/payments/patients', [\App\Http\Controllers\PaymentsController::class, 'getPatients']);
        Route::get('/birthcare/{birthcare_id}/payments/services', [\App\Http\Controllers\PaymentsController::class, 'getPatientCharges']);
        Route::post('/birthcare/{birthcare_id}/payments/trigger-room-charges', [\App\Http\Controllers\PaymentsController::class, 'triggerRoomChargeScheduler']);
        Route::get('/birthcare/{birthcare_id}/payments/soa', [\App\Http\Controllers\PaymentsController::class, 'getStatementOfAccount']);
        Route::get('/birthcare/{birthcare_id}/payments/soa/pdf', [\App\Http\Controllers\PaymentsController::class, 'generateSOAPDF']);
        Route::get('/birthcare/{birthcare_id}/payments', [\App\Http\Controllers\PaymentsController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/payments', [\App\Http\Controllers\PaymentsController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/payments/{bill}', [\App\Http\Controllers\PaymentsController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/payments/{bill}', [\App\Http\Controllers\PaymentsController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/payments/{bill}', [\App\Http\Controllers\PaymentsController::class, 'destroy']);
        Route::patch('/birthcare/{birthcare_id}/payments/{bill}/status', [\App\Http\Controllers\PaymentsController::class, 'updateStatus']);
        Route::post('/birthcare/{birthcare_id}/payments/{bill}/payments', [\App\Http\Controllers\PaymentsController::class, 'addPayment']);
        Route::get('/birthcare/{birthcare_id}/payments/{bill}/payments', [\App\Http\Controllers\PaymentsController::class, 'getBillPayments']);

        // Enhanced payment processing routes
        Route::post('/birthcare/{birthcare_id}/payments/process', [\App\Http\Controllers\PaymentsController::class, 'processPayment']); // New route for processing payments
        Route::post('/birthcare/{birthcare_id}/payments/{bill}/partial-payment', [\App\Http\Controllers\PaymentsController::class, 'processPartialPayment']);
        Route::get('/birthcare/{birthcare_id}/payments/reminders', [\App\Http\Controllers\PaymentsController::class, 'generatePaymentReminders']);
        Route::post('/birthcare/{birthcare_id}/payments/bulk-payments', [\App\Http\Controllers\PaymentsController::class, 'processBulkPayments']);
        Route::get('/birthcare/{birthcare_id}/payments/reports', [\App\Http\Controllers\PaymentsController::class, 'getReports']);
        // Note: analytics method is named getPaymentAnalytics in the controller
        Route::get('/birthcare/{birthcare_id}/payments/analytics', [\App\Http\Controllers\PaymentsController::class, 'getPaymentAnalytics']);
        Route::post('/birthcare/{birthcare_id}/payments/create-test-data', [\App\Http\Controllers\PaymentsController::class, 'createTestData']);

        // Birth care facility details route
        Route::get('/birthcare/{id}', [\App\Http\Controllers\BirthCareController::class, 'show']);
        
        // Labor monitoring routes
        Route::get('/birthcare/{birthcare_id}/labor-monitoring', [\App\Http\Controllers\LaborMonitoringController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/labor-monitoring', [\App\Http\Controllers\LaborMonitoringController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/labor-monitoring/{entry}', [\App\Http\Controllers\LaborMonitoringController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/labor-monitoring/{entry}', [\App\Http\Controllers\LaborMonitoringController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/labor-monitoring/{entry}', [\App\Http\Controllers\LaborMonitoringController::class, 'destroy']);
        
        // Room management routes
        Route::get('/birthcare/{birthcare_Id}/rooms', [RoomController::class, 'index']);
        Route::post('/birthcare/{birthcare_Id}/rooms', [RoomController::class, 'store']);
        Route::get('/birthcare/{birthcare_Id}/rooms/{room}', [RoomController::class, 'show']);
        Route::put('/birthcare/{birthcare_Id}/rooms/{room}', [RoomController::class, 'update']);
        Route::delete('/birthcare/{birthcare_Id}/rooms/{room}', [RoomController::class, 'destroy']);
        Route::get('/birthcare/{birthcare_Id}/rooms/{roomId}/beds', [RoomController::class, 'getBeds']);
        
        // Patient chart routes
        Route::get('/birthcare/{birthcare_id}/patient-charts', [\App\Http\Controllers\Staff\PatientChartController::class, 'index']);
        Route::post('/birthcare/{birthcare_id}/patient-charts', [\App\Http\Controllers\Staff\PatientChartController::class, 'store']);
        Route::get('/birthcare/{birthcare_id}/patient-charts/{chart}', [\App\Http\Controllers\Staff\PatientChartController::class, 'show']);
        Route::put('/birthcare/{birthcare_id}/patient-charts/{chart}', [\App\Http\Controllers\Staff\PatientChartController::class, 'update']);
        Route::delete('/birthcare/{birthcare_id}/patient-charts/{chart}', [\App\Http\Controllers\Staff\PatientChartController::class, 'destroy']);
        Route::get('/birthcare/{birthcare_id}/patients/{patient_id}/chart', [\App\Http\Controllers\Staff\PatientChartController::class, 'getByPatient']);
        
        // Dashboard statistics routes
        Route::get('/birthcare/{birthcare_id}/dashboard/statistics', [\App\Http\Controllers\Staff\DashboardController::class, 'getStatistics']);
        Route::get('/birthcare/{birthcare_id}/dashboard/quick-stats', [\App\Http\Controllers\Staff\DashboardController::class, 'getQuickStats']);
        Route::get('/birthcare/{birthcare_id}/dashboard/admission-trends', [\App\Http\Controllers\Staff\DashboardController::class, 'getAdmissionTrends']);
        
        // Discharge management routes
        Route::get('/birthcare/{birthcare_id}/patients/{patient}/admission', [\App\Http\Controllers\DischargeController::class, 'getPatientAdmission']);
        Route::get('/birthcare/{birthcare_id}/patients/{patient}/admission-data', [\App\Http\Controllers\DischargeController::class, 'getPatientAdmissionData']);
        Route::get('/birthcare/{birthcare_id}/discharge/fully-paid-patients', [\App\Http\Controllers\DischargeController::class, 'getFullyPaidPatientsForDischarge']);
        Route::post('/birthcare/{birthcare_id}/discharge/mother', [\App\Http\Controllers\DischargeController::class, 'storeMotherDischarge']);
        Route::post('/birthcare/{birthcare_id}/discharge/newborn', [\App\Http\Controllers\DischargeController::class, 'storeNewbornDischarge']);
        Route::post('/birthcare/{birthcare_id}/discharge/newborn-notes', [\App\Http\Controllers\DischargeController::class, 'storeNewbornDischargeNotes']);
    });
    
});
