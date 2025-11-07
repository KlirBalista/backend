<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthCare extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'longitude',
        'latitude',
        'description',
        'is_public',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function documents()
    {
        return $this->hasMany(BirthCareDocument::class);
    }

    public function roles()
    {
        return $this->hasMany(BirthCareRole::class);
    }

    public function userBirthRoles()
    {
        return $this->hasMany(UserBirthRole::class);
    }

    public function staff()
    {
        return $this->hasMany(BirthCareStaff::class);
    }
}