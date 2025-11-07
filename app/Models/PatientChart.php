<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientChart extends Model
{
    protected $fillable = [
        'patient_id',
        'birth_care_id',
        'admission_id',
        'patient_info',
        'medical_history',
        'admission_assessment',
        'delivery_record',
        'newborn_care',
        'postpartum_notes',
        'discharge_summary',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'patient_info' => 'array',
        'medical_history' => 'array',
        'admission_assessment' => 'array',
        'delivery_record' => 'array',
        'newborn_care' => 'array',
        'postpartum_notes' => 'array',
        'discharge_summary' => 'array',
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

    public function admission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    // Scopes
    public function scopeForBirthcare($query, $birthcareId)
    {
        return $query->where('birth_care_id', $birthcareId);
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Accessors
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === 'draft';
    }

    public function getIsDischargedAttribute(): bool
    {
        return $this->status === 'discharged';
    }
}