<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'birth_care_id',
        'referring_facility',
        'referring_physician',
        'referring_physician_contact',
        'receiving_facility',
        'receiving_physician',
        'receiving_physician_contact',
        'referral_date',
        'referral_time',
        'urgency_level',
        'reason_for_referral',
        'clinical_summary',
        'current_diagnosis',
        'relevant_history',
        'current_medications',
        'allergies',
        'vital_signs',
        'laboratory_results',
        'imaging_results',
        'treatment_provided',
        'patient_condition',
        'transportation_mode',
        'accompanies_patient',
        'special_instructions',
        'equipment_required',
        'isolation_precautions',
        'anticipated_care_level',
        'expected_duration',
        'insurance_information',
        'family_contact_name',
        'family_contact_phone',
        'family_contact_relationship',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'referral_date' => 'date',
        'referral_time' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the patient that owns the referral.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the birth care facility that owns the referral.
     */
    public function birthCare(): BelongsTo
    {
        return $this->belongsTo(BirthCare::class, 'birth_care_id');
    }

    /**
     * Get the user who created the referral.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the referral.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the patient's full name for the referral.
     */
    public function getPatientNameAttribute(): string
    {
        if (!$this->patient) {
            return 'Unknown Patient';
        }

        return trim($this->patient->first_name . ' ' . ($this->patient->middle_name ?? '') . ' ' . $this->patient->last_name);
    }

    /**
     * Get the formatted referral date and time.
     */
    public function getFormattedDateTimeAttribute(): string
    {
        return $this->referral_date->format('Y-m-d') . ' ' . $this->referral_time;
    }

    /**
     * Scope a query to only include referrals for a specific birth care facility.
     */
    public function scopeForBirthCare($query, $birthCareId)
    {
        return $query->where('birth_care_id', $birthCareId);
    }

    /**
     * Scope a query to only include referrals with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to search referrals by various fields.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('referring_facility', 'like', "%{$search}%")
              ->orWhere('receiving_facility', 'like', "%{$search}%")
              ->orWhere('reason_for_referral', 'like', "%{$search}%")
              ->orWhere('referring_physician', 'like', "%{$search}%")
              ->orWhere('receiving_physician', 'like', "%{$search}%")
              ->orWhereHas('patient', function ($patientQuery) use ($search) {
                  $patientQuery->where('first_name', 'like', "%{$search}%")
                               ->orWhere('last_name', 'like', "%{$search}%")
                               ->orWhere('middle_name', 'like', "%{$search}%");
              });
        });
    }
}
