<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services;

use Modules\Marketplaces\Entities\ContaVendedor;

/**
 * Grava os tokens de uma conta de vendedor (US-MKT-003). Uma linha por (empresa, marketplace,
 * vendedor): reconectar a mesma conta atualiza, outra conta cria linha nova (ADR 0117).
 * `business_id` sempre vem de quem chama (sessão ou job), nunca do marketplace.
 */
class ContasVendedor
{
    /** @param  array{access_token: string, refresh_token: string, expires_in: int, user_id: string, scope: ?string}  $tokens */
    public function gravarTokens(int $businessId, int $marketplaceId, array $tokens): ContaVendedor
    {
        if ($tokens['user_id'] === '') {
            throw new \RuntimeException('Resposta de token sem o id do vendedor.');
        }

        // Sessão pode ser de superadmin ou job sem sessão: a empresa vai explícita na consulta.
        $conta = ContaVendedor::withoutGlobalScopes()->firstOrNew([
            'business_id' => $businessId,
            'marketplace_id' => $marketplaceId,
            'seller_id_external' => $tokens['user_id'],
        ]);

        $conta->fill([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'access_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'oauth_state' => ['scope' => $tokens['scope']],
            'last_refresh_at' => now(),
            'status' => ContaVendedor::STATUS_ATIVA,
        ])->save();

        return $conta;
    }
}
