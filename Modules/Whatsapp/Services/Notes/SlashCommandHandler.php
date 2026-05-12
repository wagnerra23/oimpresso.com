<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

use Modules\Whatsapp\Entities\Message;

/**
 * SlashCommandHandler — contrato que cada handler de slash command implementa.
 *
 * O parser ({@see SlashCommandParser}) detecta o comando + argumentos brutos
 * e dispatcha pro handler registrado em {@see SlashCommandRegistry}. Os
 * handlers transformam a nota interna em ação semântica (gravar fato pra
 * Jana lembrar, marcar correção pro fine-tune, agendar lembrete, toggle
 * config per-contato).
 *
 * ## Como adicionar comando novo
 *
 *  1. Criar classe `Modules\Whatsapp\Services\Notes\XxxHandler` implementando
 *     este contrato. Retornar `SlashCommandResult` (success/error/unrecognized).
 *
 *  2. Registrar em `WhatsappServiceProvider::register()`:
 *     ```php
 *     $this->app->singleton(XxxHandler::class);
 *     $this->app->extend(SlashCommandRegistry::class, function ($registry, $app) {
 *         $registry->register('xxx', $app->make(XxxHandler::class));
 *         return $registry;
 *     });
 *     ```
 *
 *  3. Atualizar regex em `SlashCommandParser::COMMAND_PATTERN` (alternativa
 *     literal `(lembrar|corrigir|lembrete|config|xxx)`) — Wagner aprova.
 *
 *  4. Pest test em `Modules/Whatsapp/Tests/Feature/SlashXxxTest.php` cobrindo:
 *     parser detection, handler execution, multi-tenant isolation, gate
 *     `is_internal_note=true` (handler NUNCA roda fora de nota interna).
 *
 *  5. Considerar autocomplete UI em `ConversationThread.tsx` (lista
 *     `SLASH_COMMANDS`).
 *
 * ## Gate Tier 0
 *
 * Handlers só rodam quando `is_internal_note=true` no controller — defense
 * em profundidade no `InboxController::send()`. NUNCA confie no body
 * isoladamente; sempre checar `$note->is_internal_note` (já vem persistido
 * antes do dispatch).
 *
 * @see SlashCommandParser
 * @see SlashCommandRegistry
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 */
interface SlashCommandHandler
{
    /**
     * Executa o comando.
     *
     * @param  Message  $note       Nota interna recém-persistida (is_internal_note=true)
     * @param  string   $arguments  Texto depois do "/<comando> " — pode ser vazio
     */
    public function handle(Message $note, string $arguments): SlashCommandResult;
}
