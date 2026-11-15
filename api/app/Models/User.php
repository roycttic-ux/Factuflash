<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_name',
        'expected_monthly_invoices',
        'simultaneous_registers',
        'phone',
        'cuit',
        'cert_path',
        'key_path',
        'pos_number',
        'role',
        'logo_path',
        'plan_status',
        'trial_expires_at',
        'paid_until',
        'owner_id',
        'cash_register_name',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['days_left', 'system_owner_id', 'has_access'];

    public function getSystemOwnerIdAttribute()
    {
        // Si el usuario tiene owner_id (es empleado), devuelve el jefe. Si no, se devuelve a sí mismo.
        return $this->owner_id ?? $this->id;
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'trial_expires_at' => 'datetime',
            'paid_until' => 'datetime',
        ];
    }

    public function getDaysLeftAttribute()
    {
        $end = $this->paid_until;
        if ($this->trial_expires_at && (!$end || $this->trial_expires_at->greaterThan($end))) {
            $end = $this->trial_expires_at;
        }
        return $end ? max(0, (int) ceil(now()->diffInDays($end, false))) : 0;
    }

    public function getHasAccessAttribute(): bool
    {
        if ($this->owner_id) return (bool) self::find($this->owner_id)?->has_access;
        return $this->role === 'admin'
            || $this->email === 'admin@factuflash.com'
            || ($this->paid_until && $this->paid_until->isFuture())
            || ($this->trial_expires_at && $this->trial_expires_at->isFuture());
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // Facturas donde este usuario fue el cajero (no el dueño)
    public function cashierInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'cashier_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
