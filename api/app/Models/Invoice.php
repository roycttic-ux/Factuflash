<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cashier_id',
        'recipient_id',
        'invoice_number',
        'cae',
        'cae_expiry',
        'dollar_rate',
        'total_usd',
        'total_local',
        'status',
        'notify_afip',
        'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'dollar_rate' => 'decimal:4',
            'total_usd' => 'decimal:4',
            'total_local' => 'decimal:2',
            'cae_expiry' => 'date',
            'notify_afip' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function getFullData()
    {
        return Invoice::with(['recipient', 'items.product'])
            ->where('id', $this->id)
            ->where('user_id', $this->user_id)
            ->first();
    }
}
