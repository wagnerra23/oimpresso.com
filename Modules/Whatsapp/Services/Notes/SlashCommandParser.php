<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

/**
 * SlashCommandParser — detecta `/comando <argumentos>` em notas internas.
 *
 * Sintaxe canônica (ADR 0142 §2):
 *
 *   /lembrar <texto>                      → US-WA-074
 *   /corrigir <expected_response>         → US-WA-075
 *   /lembrete <data_humana_ou_iso> <body> → US-WA-076
 *   /config <key>=<value>                 → US-WA-077
 *
 * Princípios:
 *  - Best-effort: comandos não reconhecidos passam direto (nota fica como
 *    nota normal, sem warning) — princípio "evolução incremental".
 *  - Apenas a primeira linha conta — body multi-linha mantém só o `/cmd <args>`
 *    da primeira ocorrência.
 *  - Whitespace tolerante: leading/trailing spaces no body são ignorados
 *    antes do match.
 *
 * @see SlashCommandHandler
 * @see SlashCommandRegistry
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 */
final class SlashCommandParser
{
    /**
     * Regex base do parser. Quando registrar comando novo, atualizar a
     * alternativa literal aqui (ex.: `(lembrar|corrigir|lembrete|config|novo)`)
     * — Wagner aprova mudança via PR.
     *
     * Flags:
     *   /s = `.` casa newlines (pega body multi-linha como argumento)
     *   /m = ^ casa início de linha (futuro: aceitar slash não no início)
     *
     * Captura: $1 = command, $2 = arguments (texto completo após o espaço)
     */
    private const COMMAND_PATTERN = '/^\/(lembrar|corrigir|lembrete|config)\s+(.+?)$/sm';

    /**
     * Lista canônica de comandos reconhecidos sintaticamente — mesma do
     * COMMAND_PATTERN. Exposta pra autocomplete UI saber o que oferecer.
     *
     * @return array<int,array{name:string,description:string}>
     */
    public static function knownCommands(): array
    {
        return [
            ['name' => 'lembrar',  'description' => 'Grava fato sobre o contato pra Jana lembrar'],
            ['name' => 'corrigir', 'description' => 'Marca resposta do bot como errada (treino)'],
            ['name' => 'lembrete', 'description' => 'Cria lembrete agendado'],
            ['name' => 'config',   'description' => 'Toggle bot per-contato (bot=on|off)'],
        ];
    }

    /**
     * Tenta extrair comando + argumentos do body.
     *
     *   "/lembrar prefere boleto"        → ParsedCommand('lembrar', 'prefere boleto')
     *   "/desconhecido xyz"              → null (não-match)
     *   "lembrar sem barra"              → null (não-match)
     *   "/lembrar"                       → null (sem espaço/argumentos)
     *   "  /lembrar prefere boleto  "   → ParsedCommand('lembrar', 'prefere boleto')
     */
    public function parse(string $body): ?ParsedCommand
    {
        $body = trim($body);
        if ($body === '' || $body[0] !== '/') {
            return null;
        }

        if (! preg_match(self::COMMAND_PATTERN, $body, $matches)) {
            return null;
        }

        $command = strtolower($matches[1] ?? '');
        $arguments = trim($matches[2] ?? '');

        if ($command === '' || $arguments === '') {
            return null;
        }

        return new ParsedCommand($command, $arguments);
    }
}
