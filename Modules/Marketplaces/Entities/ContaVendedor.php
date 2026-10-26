<?php

namespace Modules\Marketplaces\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conta do vendedor num marketplace (mkt_account_credentials). Uma empresa pode ter mais de
 * uma conta no mesmo marketplace (padrão da ADR 0117). Tokens cifrados no banco pelo cast
 * `encrypted` e fora de toArray()/JSON.
 */
class ContaVendedor extends Model
{
    use HasBusinessScope;

    public const STATUS_ATIVA = 'active';
    public const STATUS_TOKEN_EXPIRADO = 'token_expired';
    public const STATUS_REVOGADA = 'revoked';

    protected $table = 'mkt_account_credentials';

    protected $fillable = [
        'business_id', 'marketplace_id', 'seller_id_external', 'seller_nickname',
        'access_token', 'refresh_token', 'access_token_expires_at', 'refresh_token_expires_at',
        'oauth_state', 'last_sync_at', 'last_refresh_at', 'status',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'oauth_state' => 'array',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_refresh_at' => 'datetime',
    ];

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class, 'marketplace_id');
    }
}
