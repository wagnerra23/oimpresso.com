<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use App\Account;
use Illuminate\Container\Container;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\IdempotencyConflictException;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\AsaasDriver;
use Modules\PaymentGateway\Services\Drivers\BcbPixDriver;
use Modules\PaymentGateway\Services\Drivers\C6Driver;
use Modules\PaymentGateway\Services\Drivers\InterDriver;

/**
 * Implementação do PaymentGatewayContract — coordena drivers + persistência.
 *
 * Onda 4a — ADR 0170. Só InterDriver bindado por enquanto.
 * Onda 4b/4c/4d amarra C6/Asaas/BcbPix.
 *
 * Multi-tenant Tier 0 — Cobranca usa HasBusinessScope (ADR 0093).
 * for(Account) resolve credential ativa pra business_id atual.
 */
class PaymentGatewayService implements PaymentGatewayContract
{
    private ?PaymentGatewayCredential $activeCredential = null;

    /**
     * Mapa gateway_key → FQCN do driver.
     *
     * Onda 4a: inter
     * Onda 4b: + c6, asaas
     * Onda 4d.1: + bcb_pix (este PR — PIX Automático regulado BCB)
     * Onda 5/6: pesapal (deprecated → remoção)
     */
    private const DRIVERS = [
        'inter'   => InterDriver::class,
        'c6'      => C6Driver::class,
        'asaas'   => AsaasDriver::class,
        'bcb_pix' => BcbPixDriver::class,
    ];

    public function for(Account $account): self
    {
        $credentialId = $account->payment_gateway_credential_id ?? null;
        if ($credentialId === null) {
            // Fallback: lookup via fin_contas_bancarias.payment_gateway_credential_id
            $credentialId = \DB::table('fin_contas_bancarias')
                ->where('id', $account->id)
                ->value('payment_gateway_credential_id');
        }

        if ($credentialId === null) {
            throw new CredentialMisconfiguredException(
                "Account #{$account->id} não tem payment_gateway_credential_id vinculado. " .
                "Rode paymentgateway:migrate-credentials --apply OU configure via UI Settings."
            );
        }

        $credential = PaymentGatewayCredential::query()->find($credentialId);
        if ($credential === null) {
            throw new CredentialMisconfiguredException(
                "PaymentGatewayCredential #{$credentialId} não encontrada"
            );
        }
        if (! $credential->ativo) {
            throw new CredentialMisconfiguredException(
                "Credential #{$credentialId} ({$credential->gateway_key}) está inativa"
            );
        }

        $this->activeCredential = $credential;

        return $this;
    }

    public function emitirBoleto(EmitirCobrancaInput $input): CobrancaEmitidaResult
    {
        return $this->emitir($input, 'boleto', fn ($driver, $cred) => $driver->emitirBoleto($input, $cred));
    }

    public function emitirPix(EmitirCobrancaInput $input, string $tipo = 'cob'): CobrancaEmitidaResult
    {
        $tipoMap = match ($tipo) {
            'cob'  => 'pix_cob',
            'cobv' => 'pix_cobv',
            default => throw new DriverNotSupportedException("Tipo PIX desconhecido: {$tipo}"),
        };

        return $this->emitir($input, $tipoMap, fn ($driver, $cred) => $driver->emitirPix($input, $cred, $tipo));
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input): CobrancaEmitidaResult
    {
        return $this->emitir($input, 'pix_recv', fn ($driver, $cred) => $driver->emitirPixAutomatico($input, $cred));
    }

    public function cobrarCartao(EmitirCobrancaInput $input, CardToken $token): CobrancaEmitidaResult
    {
        return $this->emitir($input, 'card', fn ($driver, $cred) => $driver->cobrarCartao($input, $cred, $token));
    }

    public function cancelar(object $cobranca, string $motivo): void
    {
        $cred = $this->credentialForCobranca($cobranca);
        $driver = $this->driverFor($cred);
        $driver->cancelar($cobranca, $cred, $motivo);

        if ($cobranca instanceof Cobranca) {
            $cobranca->update(['status' => 'cancelada']);
        }
    }

    public function refund(object $cobranca, ?int $valorCentavos, string $motivo): void
    {
        $cred = $this->credentialForCobranca($cobranca);
        $driver = $this->driverFor($cred);
        $driver->refund($cobranca, $cred, $valorCentavos, $motivo);
    }

    public function consultar(object $cobranca): CobrancaStatus
    {
        $cred = $this->credentialForCobranca($cobranca);
        $driver = $this->driverFor($cred);

        return $driver->consultar($cobranca, $cred);
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    /**
     * Pipeline canônica de emissão:
     *   1. Idempotência: já existe Cobranca paga/emitida com mesma key? retorna ela.
     *   2. Cria Cobranca em status 'pending'
     *   3. Delega driver
     *   4. Atualiza Cobranca com artefatos retornados
     */
    private function emitir(EmitirCobrancaInput $input, string $tipo, callable $driverCall): CobrancaEmitidaResult
    {
        $cred = $this->requireCredential();
        $driver = $this->driverFor($cred);

        // 1. Idempotência
        $existing = Cobranca::query()
            ->where('business_id', $input->businessId)
            ->where('idempotency_key', $input->idempotencyKey)
            ->first();

        if ($existing !== null) {
            if (in_array($existing->status, ['paga', 'cancelada'], true)) {
                throw new IdempotencyConflictException(
                    "Cobranca {$existing->id} com idempotency_key={$input->idempotencyKey} já está em status terminal ({$existing->status})"
                );
            }

            // Status pending/emitida/vencida/erro — retorna existente (cliente já tem).
            return $this->resultFromExisting($existing);
        }

        // 2. Cria pending
        $cobranca = Cobranca::query()->create([
            'business_id'                   => $input->businessId,
            'payment_gateway_credential_id' => $cred->id,
            'tipo'                          => $tipo,
            'status'                        => 'pending',
            'valor_centavos'                => $input->valorCentavos,
            'vencimento'                    => $input->vencimento,
            'contact_id'                    => $input->contactId,
            'payer_cpf_cnpj'                => $input->meta['payer_cpf_cnpj'] ?? null,
            'payer_name'                    => $input->meta['payer_name'] ?? null,
            'payer_email'                   => $input->meta['payer_email'] ?? null,
            'descricao'                     => $input->descricao,
            'idempotency_key'                => $input->idempotencyKey,
            'origem_type'                   => $input->origemType,
            'origem_id'                     => $input->origemId,
        ]);

        // 3. Driver
        $result = $driverCall($driver, $cred);

        // 4. Atualiza com artefatos
        $cobranca->update([
            'status'              => 'emitida',
            'gateway_external_id' => $result->gatewayExternalId,
            'linha_digitavel'     => $result->linhaDigitavel,
            'codigo_barras'       => $result->codigoBarras,
            'pix_emv'             => $result->pixEmv,
            'pix_qr_code_path'    => $result->pixQrCodePath,
            'boleto_pdf_url'      => $result->boletoPdfUrl,
            'nosso_numero'        => $result->nossoNumero,
            'payload_gateway'     => $result->payloadGateway,
        ]);

        // Retorna result com cobrancaId real (driver retornou 0).
        return new CobrancaEmitidaResult(
            cobrancaId: $cobranca->id,
            gatewayExternalId: $result->gatewayExternalId,
            tipo: $result->tipo,
            emitidaEm: $result->emitidaEm,
            linhaDigitavel: $result->linhaDigitavel,
            codigoBarras: $result->codigoBarras,
            pixEmv: $result->pixEmv,
            pixQrCodePath: $result->pixQrCodePath,
            boletoPdfUrl: $result->boletoPdfUrl,
            nossoNumero: $result->nossoNumero,
            payloadGateway: $result->payloadGateway,
        );
    }

    private function resultFromExisting(Cobranca $existing): CobrancaEmitidaResult
    {
        return new CobrancaEmitidaResult(
            cobrancaId: $existing->id,
            gatewayExternalId: (string) $existing->gateway_external_id,
            tipo: $existing->tipo,
            emitidaEm: $existing->updated_at?->toDateTimeImmutable() ?? new \DateTimeImmutable(),
            linhaDigitavel: $existing->linha_digitavel,
            codigoBarras: $existing->codigo_barras,
            pixEmv: $existing->pix_emv,
            pixQrCodePath: $existing->pix_qr_code_path,
            boletoPdfUrl: $existing->boleto_pdf_url,
            nossoNumero: $existing->nosso_numero,
            payloadGateway: $existing->payload_gateway ?? [],
        );
    }

    private function credentialForCobranca(object $cobranca): PaymentGatewayCredential
    {
        if ($this->activeCredential !== null) {
            return $this->activeCredential;
        }

        if ($cobranca instanceof Cobranca && $cobranca->payment_gateway_credential_id) {
            $cred = PaymentGatewayCredential::query()->find($cobranca->payment_gateway_credential_id);
            if ($cred !== null) {
                return $cred;
            }
        }

        throw new CredentialMisconfiguredException(
            'Sem credential ativa — chame ->for($account) antes ou cobranca precisa ter payment_gateway_credential_id'
        );
    }

    private function requireCredential(): PaymentGatewayCredential
    {
        if ($this->activeCredential === null) {
            throw new CredentialMisconfiguredException(
                'Sem credential ativa — chame ->for($account) antes de emitir'
            );
        }

        return $this->activeCredential;
    }

    private function driverFor(PaymentGatewayCredential $cred): PaymentDriverContract
    {
        $key = $cred->gateway_key;
        $class = self::DRIVERS[$key] ?? null;

        if ($class === null) {
            throw new DriverNotSupportedException(
                "Driver pra gateway_key='{$key}' não disponível nesta onda. " .
                "Onda 4a só Inter; C6/Asaas/BCB-Pix chegam nas próximas."
            );
        }

        return Container::getInstance()->make($class);
    }
}
