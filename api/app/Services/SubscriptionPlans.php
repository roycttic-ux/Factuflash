<?php

namespace App\Services;

class SubscriptionPlans
{
    public const TRIAL_DAYS = 7;

    public const PLANS = [
        'monthly' => ['name' => 'Plan Mensual', 'amount' => 20000, 'months' => 1, 'period' => 'mes'],
        'annual' => ['name' => 'Plan Anual', 'amount' => 240000, 'months' => 12, 'period' => 'año'],
    ];
}
