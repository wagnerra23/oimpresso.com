<?php

declare(strict_types=1);

namespace Modules\Ponto\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente do Firebase Cloud Messaging, API HTTP v1 (ADR 0423 §1-§2).
 *
 * Sem dependência nova: o token OAuth2 sai de um JWT RS256 da conta de serviço, assinado com
 * `openssl_sign` (o CLI do Hostinger não tem ext-sodium) e trocado no endpoint do Google.
 * O token de acesso fica em cache por 50 min (o Google dá 60).
 *
 * O JSON da conta de serviço é lido de `ponto_push.fcm_credentials` — um CAMINHO no
 * servidor, nunca o conteúdo no .env nem no git.
 */
class FcmClient
{
    public const OK = 'ok';
    public const TOKEN_INVALIDO = 'token_invalido';
    public const ERRO = 'erro';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CACHE_KEY = 'ponto:push:fcm_access_token';

    /**
     * Envia uma notificação a um aparelho.
     *
     * @param  array<string, string>  $dados  vai em `data` (só strings, exigência do FCM)
     * @return string self::OK | self::TOKEN_INVALIDO | self::ERRO
     */
    public function enviar(string $token, string $titulo, string $corpo, array $dados = []): string
    {
        $projeto = (string) config('ponto_push.fcm_project_id');
        if ($projeto === '') {
            throw new RuntimeException('ponto_push.fcm_project_id não configurado.');
        }

        $resposta = Http::withToken($this->tokenDeAcesso())
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$projeto}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $titulo, 'body' => $corpo],
                    'data' => array_map('strval', $dados),
                    'android' => ['priority' => 'high'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($resposta->successful()) {
            return self::OK;
        }

        // Aparelho desinstalou o app ou o token expirou: o FCM responde 404 / UNREGISTERED.
        $codigos = collect($resposta->json('error.details', []))->pluck('errorCode')->filter()->all();
        if ($resposta->status() === 404 || in_array('UNREGISTERED', $codigos, true)) {
            return self::TOKEN_INVALIDO;
        }

        Log::warning('ponto.push: FCM recusou o envio', [
            'status' => $resposta->status(),
            'codigos' => $codigos,
        ]);

        return self::ERRO;
    }

    private function tokenDeAcesso(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), function (): string {
            $conta = $this->contaDeServico();
            $agora = time();

            $jwt = $this->jwtAssinado([
                'iss' => $conta['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $agora,
                'exp' => $agora + 3600,
            ], $conta['private_key']);

            $resposta = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            $token = (string) $resposta->json('access_token', '');
            if (! $resposta->successful() || $token === '') {
                throw new RuntimeException('Google recusou a conta de serviço do FCM (HTTP ' . $resposta->status() . ').');
            }

            return $token;
        });
    }

    /** @return array{client_email: string, private_key: string} */
    private function contaDeServico(): array
    {
        $caminho = (string) config('ponto_push.fcm_credentials');
        if ($caminho === '' || ! is_readable($caminho)) {
            throw new RuntimeException('JSON da conta de serviço do FCM ausente ou ilegível.');
        }

        $json = json_decode((string) file_get_contents($caminho), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('JSON da conta de serviço do FCM sem client_email/private_key.');
        }

        return ['client_email' => (string) $json['client_email'], 'private_key' => (string) $json['private_key']];
    }

    /** @param array<string, mixed> $claims */
    private function jwtAssinado(array $claims, string $chavePrivada): string
    {
        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $entrada = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.' . $b64((string) json_encode($claims));

        $assinatura = '';
        if (! openssl_sign($entrada, $assinatura, $chavePrivada, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Falha ao assinar o JWT da conta de serviço do FCM.');
        }

        return $entrada . '.' . $b64($assinatura);
    }
}
