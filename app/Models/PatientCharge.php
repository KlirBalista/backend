<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientCharge extends Model
{
    use HasFactory;
    
    protected $table = 'patient_charges';
    
    protected $fillable = [
        'birthcare_id',
        'service_name',
        'category',
        'description',
        'price',
        'is_active'
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean'
    ];
    
    // Relationship with birthcare
    public function birthcare()
    {
        return $this->belongsTo(BirthCare::class, 'birthcare_id');
    }
}