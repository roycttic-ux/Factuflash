<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'iva_condition_id',
        'user_id',
        'name',
        'email',
        'phone',
        'document_id',
        'address',
        'iibb_alicuota',
        'iibb_exempt',
    ];

    protected function casts(): array
    {
        return [
            'iibb_alicuota' => 'decimal:2',
            'iibb_exempt' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
