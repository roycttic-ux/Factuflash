<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'mercadopago_id',
        'amount',
        'status',
        'start_date',
        'next_payment_date',
        'plan',
        'external_reference',
        'checkout_url',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'next_payment_date' => 'date',
            'last_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
