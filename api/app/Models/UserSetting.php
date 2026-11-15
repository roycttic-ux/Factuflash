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

    // These values remain available to server-side integrations, never to JSON responses.
    protected $hidden = ['mp_access_token', 'pos_clover_access_token', 'arca_cert_path', 'arca_key_path'];
    protected $appends = ['mp_configured', 'pos_clover_configured'];

    public function getMpConfiguredAttribute(): bool
    {
        return filled($this->mp_access_token);
    }

    public function getPosCloverConfiguredAttribute(): bool
    {
        return filled($this->pos_clover_access_token);
    }

    protected $fillable = [
        'user_id',
        'slug',
        'company_name',
        'company_logo',
        'company_address',
        'company_phone',
        'company_email',
        'company_cuit',
        'invoice_prefix',
        'tax_condition',
        'arca_cuit',
        'arca_cert_path',
        'arca_key_path',
        'arca_connected',
        'arca_pto_vta',
        'dollar_rate',
        'dollar_rate_mode',
        'dollar_rate_manual',
        'inflation_rate',
        'whatsapp_number',
        'show_whatsapp_button',
        'mp_access_token',
        'mp_public_key',
        'catalog_theme',
        'catalog_title',
        'catalog_description',
        'catalog_instagram',
        'catalog_facebook',
        'catalog_tiktok',
        'catalog_show_stock',
        'catalog_show_price',
        'catalog_grid_columns',
        'bank_name',
        'bank_cbu',
        'bank_alias',
        'bank_holder',
        'catalog_video_url',
        'pos_provider',
        'pos_clover_merchant_id',
        'pos_clover_access_token',
        'fiscal_jurisdiction',
        'business_activity',
        'iibb_number',
        'iibb_condition',
        'iibb_regime',
        'default_iibb_alicuota',
        'onboarding_complete',
    ];

    protected function casts(): array
    {
        return [
            'arca_connected' => 'boolean',
            'arca_pto_vta' => 'integer',
            'dollar_rate' => 'decimal:2',
            'default_iibb_alicuota' => 'decimal:2',
            'inflation_rate' => 'decimal:2',
            'catalog_show_stock' => 'boolean',
            'catalog_show_price' => 'boolean',
            'catalog_grid_columns' => 'integer',
            'onboarding_complete' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
