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
    /**
     * `refresh_token`/`expires_in` nulos = token sem vencimento (Nuvemshop, ADR 0443): a conta fica
     * sem data de vencimento e o tokenValido devolve o token direto, sem tentar renovar.
     *
     * @param  array{access_token: string, refresh_token: ?string, expires_in: ?int, user_id: string, scope: ?string}  $tokens
     */
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
            'access_token_expires_at' => $tokens['expires_in'] === null ? null : now()->addSeconds($tokens['expires_in']),
            'oauth_state' => ['scope' => $tokens['scope']],
            'last_refresh_at' => now(),
            'status' => ContaVendedor::STATUS_ATIVA,
        ])->save();

        return $conta;
    }

    /**
     * Devolve um access token válido da conta, renovando antes se faltar menos de
     * $margemMinutos para vencer. Refresh token recusado (`invalid_grant` do ML) marca a conta
     * como `token_expired`: a empresa precisa conectar de novo, e o erro sobe para quem chamou.
     */
    public function tokenValido(ContaVendedor $conta, int $margemMinutos = 5): string
    {
        $vence = $conta->access_token_expires_at;
        // Token sem vencimento e sem refresh (Nuvemshop): vale até a loja remover o app.
        if ($conta->access_token && $vence === null && ! $conta->refresh_token) {
            return (string) $conta->access_token;
        }
        if ($conta->access_token && $vence !== null && $vence->greaterThan(now()->addMinutes($margemMinutos))) {
            return (string) $conta->access_token;
        }

        try {
            $tokens = app(MercadoLivre\MercadoLivreOAuth::class)->renovar((string) $conta->refresh_token);
        } catch (\Throwable $e) {
            $conta->forceFill(['status' => ContaVendedor::STATUS_TOKEN_EXPIRADO])->save();
            throw $e;
        }

        $atualizada = $this->gravarTokens((int) $conta->business_id, (int) $conta->marketplace_id, array_merge($tokens, ['user_id' => (string) $conta->seller_id_external]));
        $conta->setRawAttributes($atualizada->getAttributes(), true);

        return (string) $atualizada->access_token;
    }
}
