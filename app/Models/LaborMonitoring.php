<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaborMonitoring extends Model
{
    use HasFactory;

    protected $table = 'labor_monitoring';

    protected $fillable = [
        'patient_id',
        'birth_care_id', 
        'monitoring_date',
        'monitoring_time',
        'temperature',
        'pulse',
        'respiration',
        'blood_pressure',
        'fht_location',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'monitoring_date' => 'date:Y-m-d',
        'monitoring_time' => 'string',
    ];

    /**
     * Get the patient that this monitoring entry belongs to.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the birth care center that this monitoring entry belongs to.
     */
    public function birthCare()
    {
        return $this->belongsTo(BirthCare::class, 'birth_care_id');
    }

    /**
     * Get the user who created this monitoring entry.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}