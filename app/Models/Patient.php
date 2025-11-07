<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Patient extends Model
{
    protected $fillable = [
        'birth_care_id',
        'facility_name',
        'first_name',
        'middle_name', 
        'last_name',
        'date_of_birth',
        'age',
        'civil_status',
        'religion',
        'occupation',
        'address',
        'contact_number',
        'emergency_contact_name',
        'emergency_contact_number',
        'husband_first_name',
        'husband_middle_name',
        'husband_last_name',
        'husband_date_of_birth',
        'husband_age',
        'husband_occupation',
        'lmp',
        'edc',
        'gravida',
        'para',
        'term',
        'preterm',
        'abortion',
        'living_children',
        'philhealth_number',
        'philhealth_category',
        'philhealth_dependent_name',
        'philhealth_dependent_relation',
        'philhealth_dependent_id',
        'medical_history',
        'allergies',
        'current_medications',
        'previous_pregnancies',
        'status',
        'notes'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'age' => 'integer',
        'husband_date_of_birth' => 'date',
        'husband_age' => 'integer',
        'lmp' => 'date',
        'edc' => 'date',
        'gravida' => 'integer',
        'para' => 'integer',
        'term' => 'integer',
        'preterm' => 'integer',
        'abortion' => 'integer',
        'living_children' => 'integer'
    ];

    // Relationships
    public function birthCare(): BelongsTo
    {
        return $this->belongsTo(BirthCare::class);
    }

    public function prenatalVisits(): HasMany
    {
        return $this->hasMany(PrenatalVisit::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(PatientAdmission::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name);
    }

}
