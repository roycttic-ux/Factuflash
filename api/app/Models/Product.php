<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Services\ProductDescriptionHtml;

class Product extends Model
{
    use HasFactory;

    protected function description(): Attribute
    {
        // Sanitize reads too, so descriptions saved before this fix cannot execute HTML.
        return Attribute::make(
            get: fn ($value) => ProductDescriptionHtml::sanitize($value),
            set: fn ($value) => ProductDescriptionHtml::sanitize($value),
        );
    }

    protected $fillable = [
        'iva_id',
        'user_id',
        'sku',
        'name',
        'description',
        'cost_usd',
        'margin_percent',
        'recommended_price',
        'stock',
        'unlimited_stock',
        'min_stock_alert',
        'category',
        'tags',
        'image_url',
        'images',
        'variants',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'margin_percent' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'stock' => 'integer',
            'unlimited_stock' => 'boolean',
            'min_stock_alert' => 'integer',
            'images' => 'array',
            'variants' => 'array',
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
