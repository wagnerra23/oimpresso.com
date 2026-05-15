<?php

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Modules\Jana\Ai\Cache\PromptCacheConfig;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Support\ContextoNegocio;
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
 *
 * Sprint MEM-HOT-2 (ADR 0047, fix Gap 1 do ADR 0046): aceita ContextoNegocio
 * opcional no construtor. Quando presente, injeta dados reais de negócio
 * (empresa, faturamento 90d, clientes, metas) no system prompt — Caminho A.
 * BC-compat: ChatCopilotoAgent($conv) sem ctx mantém comportamento anterior.
 */
class ChatCopilotoAgent implements Agent, HasProviderOptions
{
    use Promptable;

    public function __construct(
        public Conversa $conversa,
        public string $memoriaContexto = '',
        public ?ContextoNegocio $ctx = null,
    ) {
    }

    public function instructions(): Stringable|string
    {
        $base = <<<PROMPT
        Você é o Copiloto do oimpresso, um assistente de IA para gestores de pequenas e médias empresas brasileiras.
        Responda sempre em português brasileiro.
        Seja direto, prático e orientado a resultados.
        Nunca sugira ações ilegais ou antiéticas.
        Nunca invente dados — baseie-se apenas no contexto fornecido.
        Quando não tiver informação suficiente, peça esclarecimentos.
        PROMPT;

        $partes = [$base];

        // MEM-HOT-2 (ADR 0047) — contexto de negócio compacto (token-economy).
        if ($this->ctx !== null) {
            $partes[] = $this->formatarContextoNegocio($this->ctx);
        }

        // Sprint 5 (ADR 0036) — recall de fatos persistentes do user via MemoriaContrato.
        if ($this->memoriaContexto !== '') {
            $partes[] = trim($this->memoriaContexto);
        }

        return implode("\n\n", $partes);
    }

    /**
     * Formata ContextoNegocio em bloco system-prompt compacto (~150-250 tokens).
     * Pula seções vazias pra não desperdiçar tokens.
     */
    protected function formatarContextoNegocio(ContextoNegocio $ctx): string
    {
        $linhas = ['CONTEXTO DO NEGÓCIO (dados reais — use estes números, não invente):'];

        $bizLabel = $ctx->businessId !== null
            ? "{$ctx->businessName} (id {$ctx->businessId})"
            : $ctx->businessName;
        $linhas[] = "EMPRESA: {$bizLabel}";
        $linhas[] = 'DATA HOJE: ' . now()->toDateString();

        if ($ctx->clientesAtivos > 0) {
            $linhas[] = "CLIENTES ATIVOS: {$ctx->clientesAtivos}";
        }

        if (! empty($ctx->faturamento90d)) {
            $linhas[] = 'FATURAMENTO ÚLTIMOS 90 DIAS (3 ângulos por mês):';
            $linhas[] = '  - BRUTO    = total vendido (somar sell.final, ignora devoluções)';
            $linhas[] = '  - LÍQUIDO  = bruto menos devoluções (sell_return)';
            $linhas[] = '  - CAIXA    = pagamentos efetivamente recebidos no mês (transaction_payments)';
            foreach ($ctx->faturamento90d as $m) {
                // Compat: registros antigos podem ter só 'valor' (alias do bruto)
                $bruto   = (float) ($m['bruto']   ?? $m['valor'] ?? 0);
                $liquido = (float) ($m['liquido'] ?? $bruto);
                $caixa   = (float) ($m['caixa']   ?? $bruto);
                $linhas[] = sprintf(
                    '  %s: bruto R$ %s · líquido R$ %s · caixa R$ %s',
                    $m['mes'],
                    number_format($bruto, 2, ',', '.'),
                    number_format($liquido, 2, ',', '.'),
                    number_format($caixa, 2, ',', '.'),
                );
            }
        }

        if (! empty($ctx->metasAtivas)) {
            $linhas[] = 'METAS ATIVAS:';
            foreach ($ctx->metasAtivas as $meta) {
                $alvo = number_format((float) $meta['valor_alvo'], 2, ',', '.');
                $real = number_format((float) ($meta['realizado'] ?? 0), 2, ',', '.');
                $linhas[] = "  - {$meta['nome']}: alvo R$ {$alvo} / realizado R$ {$real}";
            }
        }

        if (! empty($ctx->modulosAtivos)) {
            $linhas[] = 'MÓDULOS ATIVOS: ' . implode(', ', $ctx->modulosAtivos);
        }

        if ($ctx->observacoes !== null && $ctx->observacoes !== '') {
            $linhas[] = "OBSERVAÇÕES: {$ctx->observacoes}";
        }

        return implode("\n", $linhas);
    }

    /**
     * Retorna histórico da conversa pra injetar como contexto ao LLM.
     * Últimas 20 mensagens (inverte pra ordem cronológica).
     */
    public function messages(): iterable
    {
        // MEM-S8-2 (ADR 0037 Sprint 8) — se conversa tem summary, usa
        // summary + últimas 8 msgs (vs 20 sem summary). Comprime hot window.
        $metadata = $this->conversa->metadata ?? [];
        $summary = $metadata['summary'] ?? null;

        $limite = $summary !== null ? 8 : 20;

        $msgs = $this->conversa
            ->mensagens()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => new Message($m->role, $m->content))
            ->all();

        // Injeta summary como system message no início (se existir)
        if ($summary !== null) {
            array_unshift($msgs, new Message('system',
                "Resumo do histórico anterior desta conversa (turnos antigos comprimidos):\n\n{$summary}"
            ));
        }

        return $msgs;
    }

    /**
     * GAP D4 #5 — Prompt caching live (Anthropic).
     *
     * Implementa `HasProviderOptions` pra sobrescrever `system` (string default
     * em BuildsTextRequests) por array de text blocks com `cache_control`
     * ephemeral — habilita cache hit na Anthropic Messages API.
     *
     * Anthropic reusa o prefixo do prompt até o último bloco marcado. Quebra em
     * 2 blocos:
     *   1. Persona/instruções base (raramente muda) — cache breakpoint principal
     *   2. ContextoNegocio (muda por business, estável dentro sessão) — segundo
     *      breakpoint (só se ctx presente)
     *
     * Histórico de conversa NÃO recebe marker — vai como messages[] regular
     * (cresce a cada turno; cache de prefixo já cobre system).
     *
     * Kill-switch: env `COPILOTO_PROMPT_CACHE_ENABLED=false` desliga
     * globalmente em caso de regressão.
     *
     * Pattern oficial laravel/ai: `array_merge($body, $providerOptions)` em
     * `BuildsTextRequests::buildTextRequestBody` permite override do `system`.
     *
     * @see \Modules\Jana\Ai\Cache\PromptCacheConfig
     * @see \Laravel\Ai\Gateway\Anthropic\Concerns\BuildsTextRequests
     */
    public function providerOptions(Lab|string $provider): array
    {
        // Cache só faz sentido na Anthropic — outros providers usam o caminho
        // default string. Se kill-switch off → não retorna nada (string default).
        if (! PromptCacheConfig::isEnabled()) {
            return [];
        }

        $providerKey = $provider instanceof Lab ? $provider->value : (string) $provider;
        if ($providerKey !== Lab::Anthropic->value) {
            return [];
        }

        // Reconstrói o system prompt em blocos cacheáveis.
        $instructions = (string) $this->instructions();

        // Heurística mínimo cacheável — Anthropic exige conteúdo mínimo
        // (~1024 tokens Sonnet); abaixo disso o marker é ignorado mas custa
        // overhead — pulamos.
        if (strlen($instructions) < PromptCacheConfig::minCacheableChars()) {
            return [];
        }

        // Split: persona base (estável) | contexto negócio (sessão).
        // Reuso a mesma montagem do instructions() mas em blocks separados.
        $blocks = $this->montarSystemBlocksCacheaveis();

        return [
            'system' => $blocks,
        ];
    }

    /**
     * Monta system prompt como array de text blocks Anthropic, com
     * `cache_control` no ÚLTIMO bloco estável (Anthropic cacheia prefixo até o
     * marker).
     *
     * Estratégia:
     *   - Bloco 1: persona base (sempre presente, estável)
     *   - Bloco 2: ContextoNegocio (se presente)
     *   - Bloco 3: memoria recall (se presente — pode mudar entre turnos da
     *     mesma sessão, mas em janela 5min é estável)
     *
     * cache_control vai no ÚLTIMO bloco — Anthropic cacheia TUDO até ele.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function montarSystemBlocksCacheaveis(): array
    {
        $personaBase = <<<PROMPT
        Você é o Copiloto do oimpresso, um assistente de IA para gestores de pequenas e médias empresas brasileiras.
        Responda sempre em português brasileiro.
        Seja direto, prático e orientado a resultados.
        Nunca sugira ações ilegais ou antiéticas.
        Nunca invente dados — baseie-se apenas no contexto fornecido.
        Quando não tiver informação suficiente, peça esclarecimentos.
        PROMPT;

        $blocks = [
            // Bloco 1 — persona (estável, NÃO marca cache_control aqui se houver
            // bloco posterior; o marker no último cobre prefixo inteiro).
            ['type' => 'text', 'text' => $personaBase],
        ];

        // Bloco 2 — contexto negócio compacto (~150-250 tokens, ADR 0047).
        if ($this->ctx !== null) {
            $blocks[] = ['type' => 'text', 'text' => $this->formatarContextoNegocio($this->ctx)];
        }

        // Bloco 3 — memoria recall (fatos persistentes do user; ADR 0036).
        if ($this->memoriaContexto !== '') {
            $blocks[] = ['type' => 'text', 'text' => trim($this->memoriaContexto)];
        }

        // cache_control sempre vai no ÚLTIMO bloco — Anthropic cacheia até ele
        // (prefix matching). Isso cobre persona + ctx + memoria num único hit.
        $lastIdx = count($blocks) - 1;
        $blocks[$lastIdx]['cache_control'] = PromptCacheConfig::cacheControlMarker();

        return $blocks;
    }
}
