<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'description_snapshot',
        'iva_id',
        'net_amount',
        'vat_amount',
        'invoice_id',
        'product_id',
        'variant_index',
        'variant_id',
        'variant_snapshot',
        'stock_reserved',
        'quantity',
        'unit_price_usd',
    ];

    public function save(array $options = [])
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($options) {
            $this->assertEditable();
            return parent::save($options);
        });
    }

    public function delete()
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $this->assertEditable();
            return parent::delete();
        });
    }

    private function assertEditable(): void
    {
        $ids = array_filter([$this->invoice_id, $this->getOriginal('invoice_id')]);
        $invoices = Invoice::whereIn('id', $ids)->lockForUpdate()->get();
        if ($invoices->contains(fn ($invoice) => (bool) $invoice->cae)) {
            throw new \LogicException('No se pueden modificar los productos de una factura autorizada.');
        }
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'variant_snapshot' => 'array',
            'stock_reserved' => 'boolean',
            'unit_price_usd' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
