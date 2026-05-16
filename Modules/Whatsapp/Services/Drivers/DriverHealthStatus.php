<?php

namespace Modules\Whatsapp\Services\Drivers;

/**
 * Resultado do ping (health check) de um driver.
 *
 * Observabilidade D9.a (ADR 0155): value-object — ping concreto envolve
 * em `OtelHelper::span(` (Tracer driver.ping.<provider>).
 *
 * Usado pelo WhatsappDriverHealthCheckJob (Sprint 2 — Lote 2c) pra decidir:
 * - 5 falhas consecutivas → driver_health = degraded → ativa fallback
 * - 10 falhas → disconnected
 * - banDetected=true → marca banned + alerta cross-tenant
 */
final class DriverHealthStatus
{
    public function __construct(
        public readonly bool $healthy,
        public readonly ?string $displayPhone = null,
        public readonly ?string $sessionState = null, // ex: 'connected'|'qr_required'|'disconnected'
        public readonly ?string $errorMessage = null,
        public readonly bool $banDetected = false,
    ) {}

    public static function healthy(?string $displayPhone = null, ?string $sessionState = 'connected'): self
    {
        return new self(healthy: true, displayPhone: $displayPhone, sessionState: $sessionState);
    }

    public static function unhealthy(string $errorMessage, ?string $sessionState = null, bool $banDetected = false): self
    {
        return new self(
            healthy: false,
            sessionState: $sessionState,
            errorMessage: $errorMessage,
            banDetected: $banDetected,
        );
    }
}
