<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile;

/**
 * Um drift individual detectado por um Reconciler: desired (git) != observed (vivo).
 *
 * `healable` = se o drift tem fonte-de-verdade clara e pode ser curado sozinho
 * (idempotente, append-only). Se false, o Reconciler só ALERTA — humano decide (R10).
 *
 * ADR 0237.
 */
final readonly class ReconcileDrift
{
    public function __construct(
        public string $target,         // o que driftou (path do índice / uid do índice / setting / SHA)
        public string $detail,         // descrição humana do drift
        public string $desired,        // resumo do estado desejado (git)
        public string $observed,       // resumo do estado vivo
        public bool $healable,         // pode curar sozinho? senão = alerta humano
        public bool $healed = false,   // foi curado nesta run? (heal=true + healable)
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'detail' => $this->detail,
            'desired' => $this->desired,
            'observed' => $this->observed,
            'healable' => $this->healable,
            'healed' => $this->healed,
        ];
    }
}
