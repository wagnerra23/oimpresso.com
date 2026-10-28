<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Dto;

/**
 * Resposta do terminal para uma cobrança TEF (US-SELL-075).
 *
 * `status` usa o vocabulário de TefTransacao: pendente | aprovada | negada | erro.
 */
final class TefRespostaTerminal
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $bandeira = null,
        public readonly ?string $nsu = null,
        public readonly ?string $autorizacao = null,
        public readonly ?string $externoId = null,
        public readonly ?string $mensagem = null,
        public readonly array $payload = [],
    ) {
    }
}
