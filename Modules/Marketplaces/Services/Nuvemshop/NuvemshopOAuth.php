<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services\Nuvemshop;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth2 da Nuvemshop (ADR 0443, fatia a). O aplicativo de parceiro (app_id + client_secret) é UM
 * só, da plataforma, e vem do ambiente; o token é da LOJA e fica por empresa em
 * mkt_account_credentials, com o store_id em `seller_id_external`.
 *
 * Diferente do Mercado Livre: o token NÃO expira (vale até a loja reinstalar ou remover o app),
 * então não há refresh token nem data de vencimento. A troca do código é um POST em JSON.
 * Fonte: tiendanube.github.io/api-documentation/authentication (API 2025-03).
 */
class NuvemshopOAuth
{
    /** Flag desligada ou app sem credencial = conectar recusa sem chamar a Nuvemshop. */
    public function configurado(): bool
    {
        return (bool) config('services.nuvemshop.ativo', false)
            && $this->appId() !== '' && $this->clientSecret() !== '';
    }

    public function urlAutorizacao(string $state): string
    {
        $base = str_replace('{app_id}', rawurlencode($this->appId()), (string) config('services.nuvemshop.authorize_url'));

        return $base.'?'.http_build_query(['state' => $state]);
    }

    /** @return array{access_token: string, refresh_token: null, expires_in: null, user_id: string, scope: ?string} */
    public function trocarCodigo(string $code): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Aplicativo da Nuvemshop não configurado (MARKETPLACES_NUVEMSHOP_ATIVO/APP_ID/CLIENT_SECRET).');
        }

        $resposta = Http::asJson()->acceptJson()->timeout(15)->post((string) config('services.nuvemshop.token_url'), [
            'client_id' => $this->appId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        $corpo = $resposta->json() ?? [];
        $loja = (string) ($corpo['user_id'] ?? $corpo['store_id'] ?? '');
        if (! $resposta->successful() || empty($corpo['access_token']) || $loja === '') {
            // Nunca loga o corpo: pode trazer token.
            throw new RuntimeException('Nuvemshop recusou o pedido de token (HTTP '.$resposta->status().').');
        }

        return [
            'access_token' => (string) $corpo['access_token'],
            'refresh_token' => null,
            'expires_in' => null,
            'user_id' => $loja,
            'scope' => isset($corpo['scope']) ? (string) $corpo['scope'] : null,
        ];
    }

    private function appId(): string
    {
        return (string) config('services.nuvemshop.app_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('services.nuvemshop.client_secret', '');
    }
}
