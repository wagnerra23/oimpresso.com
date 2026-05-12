<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

/**
 * ParsedCommand — DTO imutável do resultado do {@see SlashCommandParser}.
 *
 * Representa um slash command detectado numa nota interna do atendimento.
 *
 *   Body                                    → ParsedCommand
 *   "/lembrar prefere boleto"               → ParsedCommand('lembrar', 'prefere boleto')
 *   "/corrigir Deveria ter dito X"          → ParsedCommand('corrigir', 'Deveria ter dito X')
 *   "/lembrete amanhã ligar cliente"        → ParsedCommand('lembrete', 'amanhã ligar cliente')
 *
 * @see Modules\Whatsapp\Services\Notes\SlashCommandParser
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 */
final class ParsedCommand
{
    public function __construct(
        public readonly string $command,
        public readonly string $arguments,
    ) {
    }
}
