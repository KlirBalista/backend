<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAdmission extends Model
{
    protected $fillable = [
        'patient_id',
        'birth_care_id',
        'admission_date',
        'discharge_date',
        'admission_time',
        'admission_type',
        'chief_complaint',
        'reason_for_admission',
        'present_illness',
        'medical_history',
        'allergies',
        'current_medications',
        'vital_signs_temperature',
        'vital_signs_blood_pressure',
        'vital_signs_heart_rate',
        'vital_signs_respiratory_rate',
        'vital_signs_oxygen_saturation',
        'weight',
        'height',
        'physical_examination',
        'initial_diagnosis',
        'treatment_plan',
        'attending_physician',
        'primary_nurse',
        'room_id',
        'bed_id',
        'ward_section',
        'admission_source',
        'insurance_information',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'patient_belongings',
        'special_dietary_requirements',
        'mobility_assistance_needed',
        'fall_risk_assessment',
        'isolation_precautions',
        'patient_orientation_completed',
        'family_notification_completed',
        'advance_directives',
        'discharge_planning_needs',
        'status',
        'notes',
        'admitted_by',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'discharge_date' => 'date',
        'admission_time' => 'datetime:H:i',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'mobility_assistance_needed' => 'boolean',
        'patient_orientation_completed' => 'boolean',
        'family_notification_completed' => 'boolean',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function birthCare(): BelongsTo
    {
        return $this->belongsTo(BirthCare::class);
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    // Scopes
    public function scopeInLabor($query)
    {
        return $query->where('status', 'in-labor');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeDischarged($query)
    {
        return $query->where('status', 'discharged');
    }

    public function scopeByBirthCare($query, $birthCareId)
    {
        return $query->where('birth_care_id', $birthCareId);
    }

    // Helper methods
    public function getFormattedAdmissionDateTimeAttribute()
    {
        return $this->admission_date->format('M d, Y') . ' at ' . $this->admission_time->format('H:i');
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'in-labor' => 'bg-yellow-100 text-yellow-800',
            'delivered' => 'bg-blue-100 text-blue-800',
            'discharged' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Calculate the number of days between admission and discharge (or current date if not discharged)
     */
    public function getAdmissionDaysAttribute()
    {
        $endDate = $this->discharge_date ?? now()->toDateString();
        $days = $this->admission_date->diffInDays($endDate);
        
        // For same day admission/discharge, count as 1 day
        // For multi-day stays, add 1 to include both admission and discharge day
        return $days == 0 ? 1 : $days + 1;
    }
}
