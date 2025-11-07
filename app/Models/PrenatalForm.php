<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenatalForm extends Model
{
    protected $fillable = [
        'patient_id',
        'form_date',
        'gestational_age',
        'weight',
        'blood_pressure',
        'notes',
        'next_appointment',
        'examined_by',
    ];

    protected $casts = [
        'form_date' => 'date',
        'next_appointment' => 'date',
    ];

    // Relationship to Patient
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
