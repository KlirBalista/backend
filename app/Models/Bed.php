<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bed extends Model
{
    protected $fillable = [
        'bed_no',
        'room_id',
    ];

    /**
     * Get the room that owns the bed.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the patient admissions for the bed.
     */
    public function patientAdmissions(): HasMany
    {
        return $this->hasMany(PatientAdmission::class);
    }

    /**
     * Get the current patient admission (if any).
     */
    public function currentPatientAdmission(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PatientAdmission::class)
            ->whereIn('status', ['in-labor', 'delivered'])
            ->latest();
    }

    /**
     * Check if the bed is currently occupied.
     */
    public function getIsOccupiedAttribute(): bool
    {
        return $this->currentPatientAdmission()->exists();
    }

    /**
     * Get the current patient (if any).
     */
    public function getCurrentPatientAttribute(): ?Patient
    {
        $admission = $this->currentPatientAdmission;
        return $admission ? $admission->patient : null;
    }

    /**
     * Get the bed status (occupied or available).
     */
    public function getStatusAttribute(): string
    {
        return $this->is_occupied ? 'occupied' : 'available';
    }
}
