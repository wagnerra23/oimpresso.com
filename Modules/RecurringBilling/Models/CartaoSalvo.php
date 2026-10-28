<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Cartão do cliente salvo como token do gateway (US-RB-011). Nunca número nem CVV.
 *
 * O `token` é cifrado no banco e não sai em array/JSON (`$hidden`): a tela mostra bandeira e
 * últimos 4 dígitos. Quem cobra lê `$cartao->token` explicitamente.
 */
class CartaoSalvo extends Model
{
    use HasBusinessScope;

    protected $table = 'rb_cartoes_salvos';

    protected $fillable = [
        'business_id', 'contact_id', 'gateway_key', 'token', 'token_hash',
        'ultimos_4', 'bandeira', 'status', 'origem_cobranca_id',
    ];

    protected $hidden = ['token', 'token_hash'];

    protected $casts = [
        'token' => 'encrypted',
    ];
}
