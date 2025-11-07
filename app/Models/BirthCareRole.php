<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthCareRole extends Model
{
    protected $fillable = ['birth_care_id', 'role_name', 'timestamp'];

    public function birthCare()
    {
        return $this->belongsTo(BirthCare::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_birth_roles', 'role_id', 'user_id')
                    ->withPivot('birth_care_id');
    }
}