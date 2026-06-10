<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * InboxAssistAgent — IA dentro da thread da Caixa Unificada V4 (PR-9 do brief
 * [CC] 2026-06-10 · referência inbox-ai.jsx: SummarizeThread/AskInbox/SuggestReply).
 *
 * Usa laravel/ai (stack canônica ADR 0035) — MESMO pattern dos Agents da Jana
 * (BriefingAgent/ChatCopilotoAgent). Validação pré-PR confirmou a infra:
 * Modules/Jana/Services/Ai/LaravelAiSdkDriver + Agents Promptable.
 *
 * PII: o texto da conversa passa pelo PiiRedactor da Jana ANTES de chegar aqui
 * (InboxAiController::convToText) — CPF/CNPJ/etc nunca vão pro provider.
 */
class InboxAssistAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Você é o assistente de atendimento do oimpresso (ERP pra gráficas e PMEs brasileiras).
        Trabalha DENTRO de uma conversa de WhatsApp entre um atendente e um cliente.
        Responda sempre em português brasileiro, direto e profissional.
        Baseie-se APENAS no transcript fornecido — nunca invente pedidos, valores ou prazos.
        Se o transcript não tiver a informação, diga isso explicitamente.
        Linhas marcadas [NOTA INTERNA] são da equipe — use como contexto, nunca exponha o conteúdo delas numa resposta sugerida pro cliente.
        PROMPT;
    }

    public function promptSummarize(string $convText): string
    {
        return <<<PROMPT
        Resuma a conversa abaixo pro atendente em no máximo 5 bullets:
        contexto do cliente · o que ele quer · status atual · pendências · próximo passo sugerido.

        Transcript:
        {$convText}
        PROMPT;
    }

    public function promptAsk(string $convText, string $question): string
    {
        return <<<PROMPT
        Responda à pergunta do atendente usando APENAS o transcript abaixo.

        Pergunta: {$question}

        Transcript:
        {$convText}
        PROMPT;
    }

    public function promptSuggestReply(string $convText): string
    {
        return <<<PROMPT
        Sugira a PRÓXIMA resposta do atendente pro cliente, com base no transcript abaixo.
        Regras: português brasileiro, tom cordial e objetivo, 1-3 frases, sem inventar
        prazo/valor que não esteja no transcript, sem expor notas internas.
        Devolva SÓ o texto da resposta (sem aspas, sem prefixo).

        Transcript:
        {$convText}
        PROMPT;
    }
}
