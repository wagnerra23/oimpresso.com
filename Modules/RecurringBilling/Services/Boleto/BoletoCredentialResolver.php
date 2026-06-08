<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services\Boleto;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Crypt;
use Modules\RecurringBilling\Contracts\BoletoCredentialResolverInterface;
use Modules\RecurringBilling\Models\BoletoCredential;

/**
 * Extração granular Wave 18 D4 — credential lookup + decryption isolado.
 *
 * Antes inline em BoletoService::driver() + ::decryptConfig(). Agora:
 *
 *   - `resolve(int $businessId)` retorna ['banco' => string, 'config' => array]
 *     com config_json decifrado (client_secret, api_key, certificado_senha,
 *     certificado_key_b64).
 *   - `resolveDriverName(int $businessId)` retorna apenas o string `banco`
 *     sem decifrar — útil pra OTel/log sem custo de Crypt.
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId explícito, BusinessScope global
 * filtra `rb_boleto_credentials.business_id` automaticamente.
 *
 * SoC brutal (Constituição v2 §5): BoletoService agora vira thin orchestrator
 * que delega resolve+decrypt aqui. Test isolado sem precisar instanciar driver.
 *
 * Observability D9.a: spans `rb.boleto.credential.resolve`.
 *
 * @see Modules\RecurringBilling\Services\Boleto\BoletoService
 * @see Modules\RecurringBilling\Models\BoletoCredential
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §5 SoC
 */
class BoletoCredentialResolver implements BoletoCredentialResolverInterface
{
    /**
     * Campos sensíveis de `config_json` que vêm criptografados via Crypt::encryptString.
     * Decifrados antes de devolver pro driver.
     */
    private const SENSITIVE_FIELDS = [
        'client_secret',
        'api_key',
        'certificado_senha',
        'certificado_key_b64',
    ];

    /**
     * Resolve credencial ativa + decifra fields sensíveis. Lança se ausente.
     *
     * @return array{banco: string, config: array<string, mixed>, ambiente: string}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException se sem credencial ativa
     */
    public function resolve(int $businessId): array
    {
        return OtelHelper::spanBiz('rb.boleto.credential.resolve', function () use ($businessId): array {
            $credential = BoletoCredential::where('business_id', $businessId)
                ->where('ativo', true)
                ->firstOrFail();

            $config = $this->decryptConfig($credential->config_json ?? []);

            return [
                'banco'   => (string) $credential->banco,
                'config'  => $config,
                'ambiente' => (string) ($credential->ambiente ?? 'production'),
            ];
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'boleto.credential.resolve',
            'business_id' => $businessId,
        ]);
    }

    /**
     * Resolve apenas o nome do banco sem decifrar (cheap, pra logs/OTel).
     * Fail-safe: retorna 'unknown' se ausente em vez de explodir.
     */
    public function resolveDriverName(int $businessId): string
    {
        try {
            $row = BoletoCredential::where('business_id', $businessId)
                ->where('ativo', true)
                ->first();

            return $row?->banco ?? 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Decifra campos sensíveis com Crypt::decryptString.
     * Campos ausentes são ignorados (back-compat com credenciais legacy).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function decryptConfig(array $config): array
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($config[$field])) {
                $config[$field] = Crypt::decryptString($config[$field]);
            }
        }

        return $config;
    }
}
