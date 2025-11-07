<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenatalVisit extends Model
{
    protected $fillable = [
        'patient_id',
        'visit_number',
        'visit_name',
        'recommended_week',
        'scheduled_date',
        'status',
        'notes'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'visit_number' => 'integer'
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
