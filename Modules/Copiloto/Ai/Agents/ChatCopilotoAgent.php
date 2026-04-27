<?php

namespace Modules\Copiloto\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Modules\Copiloto\Entities\Conversa;
use Stringable;

/**
 * ChatCopilotoAgent — responde mensagens do user usando histórico da Conversa do projeto.
 *
 * Usa Laravel AI SDK (laravel/ai) — ver ADR 0034 + ADR 0035 (verdade canônica).
 * Substitui o método responderChat() do antigo OpenAiDirectDriver.
 *
 * NOTA: usa nosso schema próprio (copiloto_conversas + copiloto_mensagens) em vez do
 * Conversational do laravel/ai (que cria tabelas próprias). Migração pra schema do
 * laravel/ai pode ser sprint 2 quando Vizra ADK entrar (ADR 0032).
 */
class ChatCopilotoAgent implements Agent
{
    use Promptable;

    public function __construct(
        public Conversa $conversa,
    ) {
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o Copiloto do oimpresso, um assistente de IA para gestores de pequenas e médias empresas brasileiras.
        Responda sempre em português brasileiro.
        Seja direto, prático e orientado a resultados.
        Nunca sugira ações ilegais ou antiéticas.
        Nunca invente dados — baseie-se apenas no contexto fornecido.
        Quando não tiver informação suficiente, peça esclarecimentos.
        PROMPT;
    }

    /**
     * Retorna histórico da conversa pra injetar como contexto ao LLM.
     * Últimas 20 mensagens (inverte pra ordem cronológica).
     */
    public function messages(): iterable
    {
        return $this->conversa
            ->mensagens()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => new Message($m->role, $m->content))
            ->all();
    }
}
