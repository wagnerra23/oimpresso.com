<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services\MercadoLivre;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth2 do Mercado Livre (US-MKT-003). O aplicativo (client_id/secret) é UM só, da plataforma,
 * e vem do ambiente; os tokens são do VENDEDOR e ficam por empresa em mkt_account_credentials.
 *
 * Fluxo: authorization code. O access token vale ~6h; o refresh token é de uso único (cada
 * renovação devolve um novo), por isso a renovação sempre grava o par que voltou.
 */
class MercadoLivreOAuth
{
    public function configurado(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    public function urlAutorizacao(string $state): string
    {
        return config('marketplaces.mercado_livre.authorize_url').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int, user_id: string, scope: ?string} */
    public function trocarCodigo(string $code): array
    {
        return $this->pedirToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int, user_id: string, scope: ?string} */
    public function renovar(string $refreshToken): array
    {
        return $this->pedirToken(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    /** @param  array<string, string>  $campos */
    private function pedirToken(array $campos): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Aplicativo do Mercado Livre não configurado (MERCADOLIVRE_CLIENT_ID/SECRET/REDIRECT_URI).');
        }

        $resposta = Http::asForm()->acceptJson()->timeout(15)->post(
            (string) config('marketplaces.mercado_livre.token_url'),
            $campos + ['client_id' => $this->clientId(), 'client_secret' => $this->clientSecret()],
        );

        $corpo = $resposta->json() ?? [];
        if (! $resposta->successful() || empty($corpo['access_token']) || empty($corpo['refresh_token'])) {
            // Nunca loga o corpo: pode trazer token.
            throw new RuntimeException('Mercado Livre recusou o pedido de token (HTTP '.$resposta->status().').');
        }

        return [
            'access_token' => (string) $corpo['access_token'],
            'refresh_token' => (string) $corpo['refresh_token'],
            'expires_in' => (int) ($corpo['expires_in'] ?? 21600),
            'user_id' => (string) ($corpo['user_id'] ?? ''),
            'scope' => isset($corpo['scope']) ? (string) $corpo['scope'] : null,
        ];
    }

    private function clientId(): string
    {
        return (string) config('marketplaces.mercado_livre.client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('marketplaces.mercado_livre.client_secret', '');
    }

    private function redirectUri(): string
    {
        return (string) config('marketplaces.mercado_livre.redirect_uri', '');
    }
}
