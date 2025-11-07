<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = [
        'firstname',
        'middlename',
        'lastname',
        'contact_number',
        'phone',
        'address',
        'email',
        'password',
        'status',
        'is_active',
        'system_role_id',
        'email_verified_at',
        'date_of_birth',
        'gender',
        'last_login'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'date_of_birth' => 'date',
            'last_login' => 'datetime',
        ];
    }

    public function birthCare()
    {
        return $this->hasOne(BirthCare::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(BirthCareSubscription::class);
    }

    public function birthCareRoles()
    {
        return $this->hasMany(UserBirthRole::class);
    }

    public function birthCareStaff()
    {
        return $this->hasOne(BirthCareStaff::class);
    }

    // Helper method to get the role for a specific BirthCare
    public function roleForBirthCare($birthCareId)
    {
        return $this->birthCareRoles()->where('birth_care_id', $birthCareId)->first();
    }

    /**
     * Check if the user has an active subscription
     *
     * @return bool
     */
    public function hasActiveSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->exists();
    }

    public function permissions()
    {
        $birthcareId = null;

        if ($this->system_role_id == 3 && $this->birthCareStaff) {
            $birthcareId = $this->birthCareStaff->birth_care_id;
        } elseif ($this->system_role_id == 2 && $this->birthCare) {
            $birthcareId = $this->birthCare->id;
        }

        if ($birthcareId) {
            $role = $this->roleForBirthCare($birthcareId);
            if ($role && $role->role) {
                return $role->role->permissions();
            }
        }

        // Return an empty relationship if no role is found
        return $this->belongsToMany(Permission::class)->where('id', 0);
    }
}
