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
use Modules\PaymentGateway\Services\Cnab\Drivers\AilosCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\BBCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\BanrisulCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\BradescoCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\BtgCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\CaixaCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\CresolCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\ItauCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\SantanderCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\SicoobCnabDriver;
use Modules\PaymentGateway\Services\Cnab\Drivers\SicrediCnabDriver;
use Modules\PaymentGateway\Services\Drivers\AsaasDriver;
use Modules\PaymentGateway\Services\Drivers\BcbPixDriver;
use Modules\PaymentGateway\Services\Drivers\C6Driver;
use Modules\PaymentGateway\Services\Drivers\InterDriver;
use Modules\PaymentGateway\Services\Drivers\PagarmeDriver;

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
     * Onda 4d.1: + bcb_pix (PIX Automático regulado BCB)
     * Onda 4e: + pagarme (Pagar.me v5 — Stone group — boleto/pix_cob/card)
     * Onda 4f.cnab: + 11 CnabDrivers (ADR 0170-bancos-nativos-top5-drivers-separados v3 Wagner 2026-05-26)
     *               — Bradesco/Itaú/BB/Santander/Caixa/Sicoob/Ailos/Sicredi/Cresol/Banrisul/BTG
     *               todos file-based via lib eduardokum/laravel-boleto sobre fundação CnabBoletoAdapter
     *               PRs mergeados: #1589 #1590 #1592 #1606-#1613
     * Onda 5/6: pesapal (deprecated → remoção)
     */
    private const DRIVERS = [
        // API REST drivers
        'inter'   => InterDriver::class,
        'c6'      => C6Driver::class,
        'asaas'   => AsaasDriver::class,
        'bcb_pix' => BcbPixDriver::class,
        'pagarme' => PagarmeDriver::class,

        // CNAB drivers (file-based — boleto registrado via remessa/retorno)
        // Onda 4f.cnab — clientes emitem boleto dia 1 sem esperar homologação Open API (14-45d)
        'bradesco_cnab'  => BradescoCnabDriver::class,
        'itau_cnab'      => ItauCnabDriver::class,
        'bb_cnab'        => BBCnabDriver::class,
        'santander_cnab' => SantanderCnabDriver::class,
        'caixa_cnab'     => CaixaCnabDriver::class,
        'sicoob_cnab'    => SicoobCnabDriver::class,
        'ailos_cnab'     => AilosCnabDriver::class,
        'sicredi_cnab'   => SicrediCnabDriver::class,
        'cresol_cnab'    => CresolCnabDriver::class,
        'banrisul_cnab'  => BanrisulCnabDriver::class,
        'btg_cnab'       => BtgCnabDriver::class,
    ];

    /**
     * Resolve credencial ativa pra um Account UPOS.
     *
     * Ordem de resolução (Wagner 2026-05-19 — UX em 1 tela só):
     *   1. CANON: payment_gateway_credentials.conta_bancaria_id = Account.id
     *      (capturada pelo wizard SheetNovoGateway step 3)
     *   2. LEGACY 1: fin_contas_bancarias.payment_gateway_credential_id
     *      (vínculo via tela Financeiro/Contas Bancárias — coexiste pra contas
     *      pré-Onda 5 sem perder dados)
     *   3. LEGACY 2: accounts.payment_gateway_credential_id (coluna pode não
     *      existir em prod — fallback defensivo, geralmente null)
     */
    public function for(Account $account): self
    {
        $credential = $this->resolveCredentialForAccount($account);

        if ($credential === null) {
            throw new CredentialMisconfiguredException(
                "Account #{$account->id} não tem credencial PaymentGateway ativa. " .
                "Cadastre em /settings/payment-gateways e vincule esta conta no step 3 do wizard."
            );
        }
        if (! $credential->ativo) {
            throw new CredentialMisconfiguredException(
                "Credential #{$credential->id} ({$credential->gateway_key}) está inativa"
            );
        }

        $this->activeCredential = $credential;

        return $this;
    }

    /**
     * Lookup canon Onda 5 → resolve credencial ATIVA pra Account em 3 níveis.
     * Retorna null se nenhum FK matchar.
     */
    private function resolveCredentialForAccount(Account $account): ?PaymentGatewayCredential
    {
        // 1. CANON: payment_gateway_credentials.conta_bancaria_id (wizard step 3)
        $credential = PaymentGatewayCredential::query()
            ->where('conta_bancaria_id', $account->id)
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();
        if ($credential !== null) {
            return $credential;
        }

        // 2. LEGACY 1: fin_contas_bancarias.payment_gateway_credential_id
        $credentialId = \DB::table('fin_contas_bancarias')
            ->where('account_id', $account->id)
            ->value('payment_gateway_credential_id');
        if ($credentialId !== null) {
            return PaymentGatewayCredential::query()->find($credentialId);
        }

        // 3. LEGACY 2: accounts.payment_gateway_credential_id (coluna talvez null/inexistente)
        $credentialId = $account->payment_gateway_credential_id ?? null;
        if ($credentialId !== null) {
            return PaymentGatewayCredential::query()->find($credentialId);
        }

        return null;
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
