<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthCareSubscription extends Model
{
    protected $fillable = ['user_id', 'plan_id', 'payment_session_id', 'start_date', 'end_date', 'status'];

    protected $with = ['paymentSession'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function paymentSession()
    {
        return $this->belongsTo(PaymentSession::class, 'payment_session_id');
    }
}
