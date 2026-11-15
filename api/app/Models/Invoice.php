<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use LogicException;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_snapshot',
        'nc_cae_expiry',
        'nc_issued_at',
        'user_id',
        'cashier_id',
        'recipient_id',
        'invoice_number',
        'cae',
        'cae_expiry',
        'nc_cae',
        'nc_number',
        'cbte_tipo',
        'pto_vta',
        'dollar_rate',
        'total_usd',
        'total_local',
        'status',
        'notify_afip',
        'payment_method',
        'is_multiple_payment',
        'pos_device_id',
        'payment_transaction_id',
        'point_order_id',
        'point_request_key',
        'submission_key',
        'submission_hash',
        'total_tributos',
    ];

    /** Fiscal changes and their audit record commit together, including model callers. */
    public function save(array $options = [])
    {
        return DB::transaction(fn () => parent::save($options));
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice) {
            $authorized = static::whereKey($invoice->id)->lockForUpdate()->value('cae');
            if ($authorized && $invoice->isDirty([
                'fiscal_snapshot', 'total_tributos', 'total_local', 'total_usd',
                'dollar_rate', 'user_id', 'cashier_id', 'recipient_id', 'invoice_number',
                'cae', 'cae_expiry', 'cbte_tipo', 'pto_vta', 'notify_afip',
                'submission_key', 'submission_hash',
            ])) {
                throw new LogicException('No se pueden modificar los datos fiscales de una factura autorizada. Emití una nota de crédito.');
            }
        });
        static::deleting(function (Invoice $invoice) {
            if ($invoice->cae || static::whereKey($invoice->id)->value('cae')) {
                throw new LogicException('No se puede eliminar una factura autorizada. Emití una nota de crédito.');
            }
        });
        static::created(function (Invoice $invoice) {
            $tributes = $invoice->fiscal_snapshot['fiscal']['tributos'] ?? [];
            if ($tributes) $invoice->auditTributes('added', null, $tributes);
        });
        static::updated(function (Invoice $invoice) {
            if ($invoice->wasChanged('fiscal_snapshot')) {
                $before = $invoice->getOriginal('fiscal_snapshot')['fiscal']['tributos'] ?? [];
                $after = $invoice->fiscal_snapshot['fiscal']['tributos'] ?? [];
                if ($before !== $after) $invoice->auditTributes('modified', $before, $after);
            }
            if ($invoice->wasChanged('nc_cae') && $invoice->nc_cae) {
                $tributes = $invoice->fiscal_snapshot['fiscal']['tributos'] ?? [];
                if ($tributes) $invoice->auditTributes('reversed', $tributes, $tributes);
            }
        });
    }

    private function auditTributes(string $action, ?array $before, ?array $after): void
    {
        $this->tributeAudits()->create([
            'user_id' => Auth::id(), 'action' => $action,
            'before_values' => $before, 'after_values' => $after,
        ]);
    }

    public function tributeAudits(): HasMany
    {
        return $this->hasMany(InvoiceTributeAudit::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'fiscal_snapshot' => 'array',
            'nc_cae_expiry' => 'date',
            'nc_issued_at' => 'datetime',
            'dollar_rate' => 'decimal:4',
            'total_usd' => 'decimal:4',
            'total_local' => 'decimal:2',
            'total_tributos' => 'decimal:2',
            'cae_expiry' => 'date',
            'notify_afip' => 'boolean',
            'is_multiple_payment' => 'boolean',
        ];
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
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
