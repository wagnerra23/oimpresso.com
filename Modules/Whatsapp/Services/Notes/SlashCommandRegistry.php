<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

use Modules\Whatsapp\Entities\Message;

/**
 * Observabilidade D9.a (ADR 0155): lookup hashmap sub-µs; Tracer via
 * `OtelHelper::span(` reside no handler dispatchado.
 *
 * SlashCommandRegistry — mapeia nome do comando → handler concreto.
 *
 * Wiring é feito em `WhatsappServiceProvider::register()`. Handlers
 * planejados (ADR 0142):
 *   - `lembrar`  → LembrarHandler   (US-WA-074)
 *   - `corrigir` → CorrigirHandler  (US-WA-075)
 *   - `lembrete` → LembreteHandler  (US-WA-076)
 *   - `config`   → ConfigHandler    (US-WA-077)
 *
 * Comando registrado mas sem handler concreto retorna `unrecognized()` —
 * permite expansão incremental sem quebrar parser.
 *
 * @see SlashCommandHandler
 * @see SlashCommandParser
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 */
final class SlashCommandRegistry
{
    /**
     * @var array<string,SlashCommandHandler>
     */
    private array $handlers = [];

    /**
     * Registra (ou substitui) o handler de um comando. Útil pra testes
     * que mockam handler específico.
     */
    public function register(string $command, SlashCommandHandler $handler): void
    {
        $this->handlers[strtolower($command)] = $handler;
    }

    public function has(string $command): bool
    {
        return isset($this->handlers[strtolower($command)]);
    }

    /**
     * Dispatcha pro handler registrado. Comando sem handler → unrecognized()
     * silenciosamente (não polui UI com warning).
     */
    public function dispatch(string $command, Message $note, string $arguments): SlashCommandResult
    {
        $key = strtolower($command);
        $handler = $this->handlers[$key] ?? null;

        if ($handler === null) {
            return SlashCommandResult::unrecognized();
        }

        return $handler->handle($note, $arguments);
    }
}
