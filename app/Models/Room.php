<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'name',
        'price',
        'room_type',
        'birth_care_id',
        'patient_charge_id',
    ];

    /**
     * Get the birth care that owns the room.
     */
    public function birthCare(): BelongsTo
    {
        return $this->belongsTo(BirthCare::class);
    }

    /**
     * Get the patient charge associated with this room.
     */
    public function patientCharge(): BelongsTo
    {
        return $this->belongsTo(PatientCharge::class);
    }

    /**
     * Get the beds for the room.
     */
    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    /**
     * Get the patient admissions for the room.
     */
    public function patientAdmissions(): HasMany
    {
        return $this->hasMany(PatientAdmission::class);
    }

    /**
     * Get the count of beds in this room.
     */
    public function getBedCountAttribute(): int
    {
        return $this->beds()->count();
    }

    /**
     * Get the count of occupied beds (beds with active patient admissions).
     */
    public function getOccupiedBedsCountAttribute(): int
    {
        return $this->beds()
            ->whereHas('patientAdmissions', function ($query) {
                $query->whereIn('status', ['in-labor', 'delivered']);
            })
            ->count();
    }

    /**
     * Get the count of available beds.
     */
    public function getAvailableBedsCountAttribute(): int
    {
        return $this->bed_count - $this->occupied_beds_count;
    }

    /**
     * Check if the room is fully occupied.
     */
    public function getIsFullyOccupiedAttribute(): bool
    {
        return $this->available_beds_count === 0;
    }
}
