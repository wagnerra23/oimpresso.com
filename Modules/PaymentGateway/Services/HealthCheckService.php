<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\AsaasDriver;
use Modules\PaymentGateway\Services\Drivers\BcbPixDriver;
use Modules\PaymentGateway\Services\Drivers\C6Driver;
use Modules\PaymentGateway\Services\Drivers\InterDriver;
use Throwable;

/**
 * Service que dispara health check real em uma credencial de gateway
 * via PaymentDriverContract::healthCheck() — atualiza colunas
 * health_status + health_checked_at na tabela payment_gateway_credentials.
 *
 * Read-only do ponto de vista funcional (não cria/altera cobrança).
 * Multi-tenant Tier 0 — credencial filtrada por business_id global scope.
 *
 * ADR 0170 Onda 4d.3.
 */
class HealthCheckService
{
    /**
     * Resolve driver concreto pela key do gateway.
     *
     * @throws DriverNotSupportedException
     */
    public function resolveDriver(string $gatewayKey): PaymentDriverContract
    {
        return match ($gatewayKey) {
            'inter'   => app(InterDriver::class),
            'c6'      => app(C6Driver::class),
            'asaas'   => app(AsaasDriver::class),
            'bcb_pix' => app(BcbPixDriver::class),
            default   => throw new DriverNotSupportedException("Driver não suportado: {$gatewayKey}"),
        };
    }

    /**
     * Roda health check real numa credencial específica.
     *
     * Atualiza health_status + health_checked_at no Model após chamada.
     * Captura exceções pra não bloquear UI (status virá `down` em erro).
     */
    public function check(PaymentGatewayCredential $credential): DriverHealth
    {
        $now = CarbonImmutable::now();

        try {
            $driver = $this->resolveDriver($credential->gateway_key);
            $health = $driver->healthCheck($credential);

            $credential->update([
                'health_status' => $health->status,
                'health_checked_at' => $now,
            ]);

            return $health;
        } catch (DriverNotSupportedException $e) {
            // PesaPal e drivers legacy retornam down sem update
            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: 0,
                checkedAt: new DateTimeImmutable(),
                errorMessage: $e->getMessage(),
            );
        } catch (Throwable $e) {
            $credential->update([
                'health_status' => 'down',
                'health_checked_at' => $now,
            ]);

            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: 0,
                checkedAt: new DateTimeImmutable(),
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Roda health check em todas credenciais ativas do business.
     * Usado no botão "Testar todos" da Tela 2 Settings.
     *
     * @return array<int, array{credential_id: int, status: string, latency_ms: int, message: ?string}>
     */
    public function checkAll(int $businessId): array
    {
        $credentials = PaymentGatewayCredential::query()
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->get();

        return $credentials->map(function (PaymentGatewayCredential $c) {
            $h = $this->check($c);

            return [
                'credential_id' => $c->id,
                'status' => $h->status,
                'latency_ms' => $h->latencyMs,
                'message' => $h->errorMessage,
            ];
        })->all();
    }
}
