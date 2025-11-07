<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'title_institution',
        'email',
        'rating',
        'feedback_text',
        'consent_given',
        'status',
        'submitted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'rating' => 'integer',
        'consent_given' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePublic($query)
    {
        return $query->where('consent_given', true)->where('status', 'approved');
    }
}