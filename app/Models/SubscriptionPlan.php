<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = ['plan_name', 'price', 'duration_in_year', 'description'];

    public function subscriptions()
    {
        return $this->hasMany(BirthCareSubscription::class, 'plan_id');
    }
}