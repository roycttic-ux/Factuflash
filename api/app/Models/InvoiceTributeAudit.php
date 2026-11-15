<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceTributeAudit extends Model
{
    protected $fillable = ['invoice_id', 'user_id', 'action', 'before_values', 'after_values'];

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El registro de auditoría es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El registro de auditoría es inmutable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
