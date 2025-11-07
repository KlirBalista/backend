<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBirthRole extends Model
{
    protected $fillable = ['role_id', 'user_id', 'birth_care_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(BirthCareRole::class);
    }

    public function birthCare()
    {
        return $this->belongsTo(BirthCare::class);
    }
}