<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use Modules\Whatsapp\Entities\WhatsappBusinessConfig;

/**
 * DriverFactory — resolve driver por business com fallback runtime.
 *
 * Decisão mãe: ADR 0096.
 *
 * Lógica de resolução (em runtime, em cada chamada):
 * 1. Se `driver_health` é healthy ou never_checked → usa driver primário
 * 2. Se driver primário está degraded/disconnected/banned → usa fallback_driver
 *    (gating de cadastro garante Meta Cloud sempre cadastrado quando driver
 *    primário é zapi/baileys; ADR 0096 emenda 4)
 *
 * Driver desconhecido ou proibido → InvalidArgumentException (defesa em
 * profundidade contra entrada malformada que passou pelo FormRequest).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3 Fluxos críticos
 */
class DriverFactory
{
    /**
     * Resolve a instância concreta do driver pra um business.
     *
     * Aplica fallback automático em runtime se driver primário está degraded
     * — é exatamente isso que protege a operação quando Z-API/Baileys cai.
     */
    public static function make(WhatsappBusinessConfig $config): DriverInterface
    {
        $effectiveDriver = $config->effectiveDriver();

        $forbidden = config('whatsapp.forbidden_drivers', []);
        if (in_array($effectiveDriver, $forbidden, true)) {
            throw new \InvalidArgumentException(
                "Driver '{$effectiveDriver}' está em forbidden_drivers (config/whatsapp.php). "
                . "Reabrir só via nova ADR explícita Wagner-aceita."
            );
        }

        return match ($effectiveDriver) {
            'zapi' => app(ZapiDriver::class),
            'meta_cloud' => app(MetaCloudDriver::class),
            'null' => app(NullDriver::class),
            // 'baileys' será adicionado em Sprint 3 (ADR 0096 emenda 4)
            // — quando implementado, descomentar:
            // 'baileys' => app(BaileysDriver::class),
            default => throw new \InvalidArgumentException(
                "Driver '{$effectiveDriver}' desconhecido. Valores válidos Sprint 1: "
                . "'zapi', 'meta_cloud', 'null'."
            ),
        };
    }

    /**
     * Resolve driver SEM aplicar fallback automático.
     *
     * Usado pelo WhatsappDriverHealthCheckJob (Sprint 2) que precisa pingar
     * o driver primário diretamente, mesmo que esteja marcado degraded —
     * pra ver se voltou.
     */
    public static function makePrimary(WhatsappBusinessConfig $config): DriverInterface
    {
        $forbidden = config('whatsapp.forbidden_drivers', []);
        if (in_array($config->driver, $forbidden, true)) {
            throw new \InvalidArgumentException(
                "Driver primário '{$config->driver}' está em forbidden_drivers."
            );
        }

        return match ($config->driver) {
            'zapi' => app(ZapiDriver::class),
            'meta_cloud' => app(MetaCloudDriver::class),
            'null' => app(NullDriver::class),
            default => throw new \InvalidArgumentException(
                "Driver primário '{$config->driver}' desconhecido."
            ),
        };
    }
}
