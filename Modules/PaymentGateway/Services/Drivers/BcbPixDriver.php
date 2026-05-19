<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Drivers;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Driver PIX Automático regulado BCB — Resolução 380/2024.
 *
 * Onda 4d.1 — ADR 0170. Driver especializado em **PIX Recorrência** (recv):
 * mandatos recorrentes onde o pagador autoriza cobranças automáticas no app
 * do banco dele (out-of-band). Cobranças subsequentes vêm via webhook.
 *
 * **PSP-agnóstico** — Wagner pode rotear via Inter/C6/Asaas/PSP terceiro
 * que implemente API Open Finance Pix Automático. URL base via config.
 *
 * Suporta:
 *   ✓ emitirPixAutomatico (cria mandato via PUT /v2/rec/{txid})
 *   ✓ cancelar (DELETE /v2/rec/{txid} = revogar mandato)
 *   ✓ consultar (GET /v2/rec/{txid})
 *   ✓ healthCheck (OAuth ping)
 *   ✓ processWebhook (cobranças recorrentes notificadas pelo PSP)
 *
 * NÃO suporta (use outros drivers):
 *   ✗ emitirBoleto, emitirPix(cob/cobv), cobrarCartao, refund
 *
 * Credenciais (config_json):
 *   client_id:     OAuth2 PSP
 *   client_secret: OAuth2 PSP
 *   base_url:      URL PSP (sandbox/prod do parceiro escolhido)
 *   ambiente:      'sandbox' | 'production'
 *
 * Fluxo PIX Automático (BCB):
 *   1. Beneficiário (Wagner) cria mandato PUT /v2/rec/{txid} com pagador info
 *   2. Pagador autoriza via app banco dele (out-of-band, manual)
 *   3. Banco do pagador notifica BCB que aceitou
 *   4. PSP do beneficiário notifica via webhook que mandato está ATIVO
 *   5. PSP gera cobranças automáticas no ciclo definido (mensal/anual/etc)
 *   6. Webhook chega a cada cobrança paga/falhada
 */
class BcbPixDriver implements PaymentDriverContract
{
    private array $tokenCache = [];

    public function key(): string
    {
        return 'bcb_pix';
    }

    public function supports(string $tipo): bool
    {
        return $tipo === 'pix_recv';
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('BcbPix só faz pix_recv. Use inter/c6/asaas pra boleto.');
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            "BcbPix só faz pix_recv (PIX Automático). Pra '{$tipo}' use inter/c6/asaas."
        );
    }

    /**
     * Cria mandato PIX Automático (recv).
     *
     * Pagador autoriza out-of-band depois. Retorna mandato em status PENDING
     * (aguardando autorização do pagador no app do banco dele).
     */
    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);

        $cpfPagador = preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? '');
        if ($cpfPagador === '') {
            throw new InvalidPayerException('BcbPix mandato exige CPF/CNPJ do pagador em meta.payer_cpf_cnpj');
        }

        $pixKey = $input->meta['pix_key'] ?? null;
        if ($pixKey === null) {
            throw new InvalidPayerException('BcbPix mandato exige meta.pix_key (chave PIX do beneficiário)');
        }

        $payload = [
            'vinculo' => [
                'contrato'      => $input->idempotencyKey,
                'objeto'        => substr($input->descricao, 0, 35),
            ],
            'calendario' => [
                'dataInicial'   => Carbon::instance($input->vencimento)->toDateString(),
                'periodicidade' => $input->meta['periodicidade'] ?? 'MENSAL', // SEMANAL/MENSAL/TRIMESTRAL/SEMESTRAL/ANUAL
            ],
            'valor' => [
                'valorRec'      => number_format($input->valorCentavos / 100, 2, '.', ''),
            ],
            'pagador' => array_filter([
                'cpf'           => strlen($cpfPagador) === 11 ? $cpfPagador : null,
                'cnpj'          => strlen($cpfPagador) === 14 ? $cpfPagador : null,
                'nome'          => $input->meta['payer_name'] ?? null,
            ]),
            'recebedor' => [
                'chave'         => $pixKey,
            ],
        ];

        $response = $this->client($cred)
            ->put("/v2/rec/{$input->idempotencyKey}", $payload);

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "BcbPix mandato falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $idRec = (string) ($data['idRec'] ?? $data['txid'] ?? $input->idempotencyKey);

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $idRec,
            tipo: 'pix_recv',
            emitidaEm: new \DateTimeImmutable(),
            payloadGateway: $data,
        );
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('BcbPix não emite cartão. Use Asaas.');
    }

    /**
     * Revoga mandato PIX Automático.
     * Cobranças futuras param. Cobranças já efetivadas mantêm-se.
     */
    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        $this->assertCredential($cred);
        $idRec = (string) ($cobranca->gateway_external_id ?? '');
        if ($idRec === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra revogar mandato BcbPix');
        }

        $response = $this->client($cred)
            ->delete("/v2/rec/{$idRec}", ['motivo' => substr($motivo, 0, 140)]);

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "BcbPix revogar mandato falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        throw new DriverNotSupportedException(
            'BcbPix mandato não tem refund individual via API. ' .
            'Revogue o mandato (cancelar) + faça devolução PIX manual da cobrança específica.'
        );
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        $this->assertCredential($cred);
        $idRec = (string) ($cobranca->gateway_external_id ?? '');
        if ($idRec === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra consultar mandato BcbPix');
        }

        $response = $this->client($cred)->get("/v2/rec/{$idRec}");
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "BcbPix consultar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $status = (string) ($data['status'] ?? '');

        return new CobrancaStatus(
            status: $this->mapStatus($status),
            payloadGateway: $data,
        );
    }

    public function healthCheck(object $cred): DriverHealth
    {
        $this->assertCredential($cred);
        $start = microtime(true);

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($this->baseUrl($cred) . '/oauth/token', [
                    'client_id'     => $cred->config_json['client_id'] ?? '',
                    'client_secret' => $cred->config_json['client_secret'] ?? '',
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'pix.recorrencia.read pix.recorrencia.write',
                ]);

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful() && ! empty($response->json('access_token'))) {
                return new DriverHealth(
                    ok: true,
                    status: $latencyMs > 3000 ? 'degraded' : 'ok',
                    latencyMs: $latencyMs,
                    checkedAt: new \DateTimeImmutable(),
                );
            }

            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: $latencyMs,
                checkedAt: new \DateTimeImmutable(),
                errorMessage: "BcbPix OAuth failed ({$response->status()})",
            );
        } catch (\Throwable $e) {
            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: (int) round((microtime(true) - $start) * 1000),
                checkedAt: new \DateTimeImmutable(),
                errorMessage: $e->getMessage(),
            );
        }
    }

    public function processWebhook(array $payload, object $cred): ?object
    {
        // Webhook BCB padrão: pix[] (lista de cobranças confirmadas) OU
        // rec[] (atualizações de mandato).
        $idRec = (string) (
            $payload['idRec']
            ?? $payload['rec']['idRec']
            ?? $payload['pix'][0]['infoPagador']['idRec']
            ?? ''
        );

        if ($idRec === '') {
            return null;
        }

        return (object) [
            'gateway_external_id' => $idRec,
            'gateway_key'         => $this->key(),
            'raw_payload'         => $payload,
        ];
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    private function assertCredential(object $cred): void
    {
        if (! $cred instanceof PaymentGatewayCredential) {
            throw new CredentialMisconfiguredException(
                'Credential precisa ser PaymentGatewayCredential, recebeu: ' . get_class($cred)
            );
        }
        if ($cred->gateway_key !== 'bcb_pix') {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver BcbPix"
            );
        }
        $config = $cred->config_json ?? [];
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new CredentialMisconfiguredException(
                'BcbPix credential precisa client_id + client_secret em config_json'
            );
        }
        if (empty($config['base_url'])) {
            throw new CredentialMisconfiguredException(
                'BcbPix credential precisa base_url em config_json (PSP-agnóstico)'
            );
        }
    }

    private function baseUrl(PaymentGatewayCredential $cred): string
    {
        return rtrim((string) ($cred->config_json['base_url'] ?? ''), '/');
    }

    private function client(PaymentGatewayCredential $cred): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($cred))
            ->withToken($this->getAccessToken($cred))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    private function getAccessToken(PaymentGatewayCredential $cred): string
    {
        $key = $cred->id . ':' . $cred->ambiente;
        if (isset($this->tokenCache[$key])) {
            return $this->tokenCache[$key];
        }

        $config = $cred->config_json ?? [];
        $response = Http::asForm()
            ->timeout(10)
            ->post($this->baseUrl($cred) . '/oauth/token', [
                'client_id'     => $config['client_id'] ?? '',
                'client_secret' => $config['client_secret'] ?? '',
                'grant_type'    => 'client_credentials',
                'scope'         => 'pix.recorrencia.read pix.recorrencia.write',
            ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            throw new CredentialMisconfiguredException(
                "BcbPix OAuth falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        return $this->tokenCache[$key] = (string) $response->json('access_token');
    }

    private function mapStatus(string $bcbStatus): string
    {
        return match (strtoupper($bcbStatus)) {
            'ATIVA', 'ACTIVE'                          => 'emitida',
            'CRIADA', 'CREATED', 'AGUARDANDO_PAGADOR'  => 'pending',
            'REJEITADA', 'REJEITADA_PAGADOR'           => 'erro',
            'CANCELADA', 'EXPIRADA', 'CANCELED'        => 'cancelada',
            'CONCLUIDA', 'COMPLETED'                   => 'paga',
            default                                    => 'pending',
        };
    }
}
