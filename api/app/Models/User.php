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
        'phone',
        'cuit',
        'cert_path',
        'key_path',
        'pos_number',
        'role',
        'logo_path',
        'plan_status',
        'trial_expires_at',
        'owner_id',
        'cash_register_name',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['days_left', 'system_owner_id'];

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
        ];
    }

    public function getDaysLeftAttribute()
    {
        if (!$this->trial_expires_at) return 0;
        $diff = now()->diffInDays($this->trial_expires_at, false);
        return $diff > 0 ? (int)round($diff) : 0;
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
