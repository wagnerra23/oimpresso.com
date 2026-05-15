<?php

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Modules\Jana\Ai\Cache\PromptCacheConfig;
use Modules\Jana\Support\ContextoNegocio;
use Stringable;

/**
 * BriefingAgent — gera o briefing inicial da conversa do Copiloto.
 *
 * Usa Laravel AI SDK (laravel/ai) — ver ADR 0034 + ADR 0035 (verdade canônica).
 * Substitui o método gerarBriefing() do antigo OpenAiDirectDriver.
 */
class BriefingAgent implements Agent, HasProviderOptions
{
    use Promptable;

    public function __construct(
        public ContextoNegocio $ctx,
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

    public function montarPromptBriefing(): string
    {
        $ctx = $this->ctx;

        $fat = collect($ctx->faturamento90d)
            ->map(fn ($m) => "  {$m['mes']}: R$ " . number_format($m['valor'], 2, ',', '.'))
            ->implode("\n");

        $metas = collect($ctx->metasAtivas)
            ->map(fn ($m) => "  - {$m['nome']}: alvo R$ " . number_format($m['valor_alvo'], 2, ',', '.') . ' / realizado R$ ' . number_format($m['realizado'], 2, ',', '.'))
            ->implode("\n");

        return <<<PROMPT
        Faça um briefing rápido e amigável para o gestor da empresa "{$ctx->businessName}".

        Dados disponíveis:
        - Clientes ativos: {$ctx->clientesAtivos}
        - Faturamento últimos 90 dias (por mês):
        {$fat}
        - Módulos ativos: {$ctx->modulosAtivos}
        - Metas ativas:
        {$metas}

        Seja conciso (3-5 frases). Destaque tendência e uma oportunidade óbvia. Termine convidando o gestor a pedir sugestões de metas.
        PROMPT;
    }

    /**
     * GAP D4 #5 — Prompt caching live (Anthropic).
     *
     * BriefingAgent roda tipicamente 1x/dia por business — TTL 1h é overkill
     * pois cada execução é nova janela. Mas DENTRO do mesmo briefing, prompt
     * iniciais + ContextoNegocio são reusados se houver retries/streaming.
     * Estratégia conservadora: cache_control ephemeral 5min default no system.
     *
     * @see \Modules\Jana\Ai\Cache\PromptCacheConfig
     */
    public function providerOptions(Lab|string $provider): array
    {
        if (! PromptCacheConfig::isEnabled()) {
            return [];
        }

        $providerKey = $provider instanceof Lab ? $provider->value : (string) $provider;
        if ($providerKey !== Lab::Anthropic->value) {
            return [];
        }

        $instructions = (string) $this->instructions();
        if (strlen($instructions) < PromptCacheConfig::minCacheableChars()) {
            return [];
        }

        return [
            'system' => [
                PromptCacheConfig::textBlockCached($instructions),
            ],
        ];
    }
}
