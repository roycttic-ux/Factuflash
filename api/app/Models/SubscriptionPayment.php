<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subscription_id', 'provider_payment_id', 'amount', 'paid_at', 'period_ends_at', 'revoked',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }
}
