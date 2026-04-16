<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sku',
        'name',
        'description',
        'cost_usd',
        'margin_percent',
        'recommended_price',
        'stock',
        'min_stock_alert',
        'category',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'margin_percent' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'stock' => 'integer',
            'min_stock_alert' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock_alert');
    }
}
