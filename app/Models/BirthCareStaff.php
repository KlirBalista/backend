<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthCareStaff extends Model
{
    protected $fillable = ['user_id', 'birth_care_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function birthCare()
    {
        return $this->belongsTo(BirthCare::class);
    }
}