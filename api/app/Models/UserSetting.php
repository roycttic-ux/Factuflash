<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    protected $table = 'user_settings';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'company_name',
        'company_logo',
        'company_address',
        'company_phone',
        'company_email',
        'company_cuit',
        'arca_cuit',
        'arca_cert_path',
        'arca_key_path',
        'arca_connected',
        'dollar_rate',
        'inflation_rate',
        'whatsapp_number',
        'show_whatsapp_button',
        'mp_access_token',
        'mp_public_key',
        'catalog_theme',
    ];

    protected function casts(): array
    {
        return [
            'arca_connected' => 'boolean',
            'dollar_rate' => 'decimal:2',
            'inflation_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
