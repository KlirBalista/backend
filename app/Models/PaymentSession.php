<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSession extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'paymongo_checkout_id',
        'paymongo_payment_intent_id',
        'reference_number',
        'amount',
        'currency',
        'status',
        'payment_method',
        'checkout_url',
        'paid_at',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function subscription()
    {
        return $this->hasOne(BirthCareSubscription::class, 'payment_session_id');
    }
}
