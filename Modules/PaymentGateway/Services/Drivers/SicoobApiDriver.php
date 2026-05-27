<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Drivers;

use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;

/**
 * Driver Sicoob — API Cobrança v3 (REST + OAuth2 client_credentials + mTLS).
 *
 * US-FIN-044 — Onda 4f.sicoob_api. Sinal qualificado: biz=4 ROTA LIVRE
 * (Larissa via Kamila) pediu 2026-05-27 [ADR 0105 — cliente como sinal].
 *
 * PR1 (este arquivo) — APENAS skeleton:
 *   ✓ key, supports
 *   ✗ todos demais métodos lançam DriverNotSupportedException("PR{N}")
 *
 * Próximas fatias do mesmo módulo:
 *   PR2: OAuth2 client_credentials + emitirBoleto (POST /cobranca/v3/boletos)
 *   PR3: mTLS handshake real (.pfx + senha cifrada via Crypt)
 *   PR4: processWebhook + HMAC + idempotência
 *   PR5: Wizard UI step Sicoob (SheetNovoGateway.tsx)
 *   PR6: RUNBOOK + Charter + Pest cross-tenant biz=4 vs biz=99
 *
 * Diferença frente ao SicoobCnabDriver:
 *   - CnabDriver = arquivo remessa/retorno (assíncrono, sem webhook)
 *   - ApiDriver  = REST tempo real (sync) + webhook baixa real-time
 *
 * Os 2 coexistem — cliente escolhe qual ativar no wizard
 * `/settings/payment-gateways`. Mesma cooperativa singular Sicoob pode
 * ter os dois cadastrados (um por business_id separado).
 *
 * Refs:
 *   - https://developers.sicoob.com.br/#!/apis (docs oficiais)
 *   - ADR 0170-bancos-nativos-top5-drivers-separados §4f.sicoob_api
 *   - memory/sessions/2026-05-27-sicoob-api-credenciais-pedido.md (checklist Kamila)
 */
class SicoobApiDriver implements PaymentDriverContract
{
    /**
     * Endpoint REST oficial Sicoob (cooperativa singular agnóstica —
     * roteamento por client_id no token).
     */
    private const API_BASE_PRODUCTION = 'https://api.sicoob.com.br';

    private const API_BASE_SANDBOX = 'https://sandbox.sicoob.com.br';

    public function key(): string
    {
        return 'sicoob_api';
    }

    /**
     * Sicoob API v3 hoje suporta boleto + pix_cob.
     * pix_cobv (com vencimento) chega futuramente — ver Sicoob roadmap.
     */
    public function supports(string $tipo): bool
    {
        return in_array($tipo, ['boleto', 'pix_cob'], true);
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('SicoobApiDriver::emitirBoleto chega no PR2 (US-FIN-044).');
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('SicoobApiDriver::emitirPix chega no PR2+ (US-FIN-044). Use sicoob_cnab por enquanto.');
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('Sicoob PIX Automático não suportado nesta API. Use bcb_pix driver (regulado BCB).');
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('Sicoob não emite cartão via API Cobrança. Use Asaas/Pagar.me.');
    }

    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        throw new DriverNotSupportedException('SicoobApiDriver::cancelar chega no PR2 (US-FIN-044).');
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        throw new DriverNotSupportedException('Sicoob refund de boleto não suportado via API. TED reverso operado manualmente.');
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        throw new DriverNotSupportedException('SicoobApiDriver::consultar chega no PR2 (US-FIN-044).');
    }

    public function healthCheck(object $cred): DriverHealth
    {
        throw new DriverNotSupportedException('SicoobApiDriver::healthCheck chega no PR2 (US-FIN-044).');
    }

    public function processWebhook(array $payload, object $cred): ?object
    {
        throw new DriverNotSupportedException('SicoobApiDriver::processWebhook chega no PR4 (US-FIN-044).');
    }
}
