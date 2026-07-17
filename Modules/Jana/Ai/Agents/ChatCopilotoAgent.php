<?php

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Modules\Jana\Ai\Cache\PromptCacheConfig;
use Modules\Jana\Ai\Tools\BriefDiario\InadimplenciaTool;
use Modules\Jana\Ai\Tools\BriefDiario\NfeStatusTool;
use Modules\Jana\Ai\Tools\BriefDiario\OportunidadesTool;
use Modules\Jana\Ai\Tools\BriefDiario\TicketsTopTool;
use Modules\Jana\Ai\Tools\BriefDiario\VendasPeriodoTool;
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
 *
 * US-COPI-141 — tool use no chat (Camada B v2, [ADR 0141]). Até aqui este era
 * o único caminho conversacional da Jana e era single-shot: o PHP pré-cozinhava
 * o ContextoNegocio e o LLM só formatava. Agora declara HasTools e reusa as 5
 * tools READ-ONLY já provadas em prod pelo BriefDiarioAgent — o LLM decide se
 * precisa de número vivo (vendas de hoje, inadimplência, NF-e) em vez de repetir
 * snapshot velho. Nenhuma tool escreve; write-action é assunto da [ADR 0145]
 * (gate no ADS + FsmActionBridge), fora do escopo desta US.
 *
 * Tier 0 mecânico ([ADR 0093] + [ADR 0141]): o business_id das tools vem do
 * `$conversa->business_id` (constructor), NUNCA do LLM. Conversa sem business_id
 * → zero tools (fail-safe: sem tool é melhor que tool com tenant errado).
 *
 * Flag default-OFF (`copiloto.chat_tools.enabled`, [ADR 0245]): com a flag OFF
 * `tools()` devolve `[]` e o SDK omite a chave `tools` do request
 * (`BuildsTextRequests`: `if (filled($tools))`) — pipeline byte-idêntico ao
 * legado. Prod espera; homolog liga.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
#[MaxSteps(5)]
class ChatCopilotoAgent implements Agent, HasProviderOptions, HasTools
{
    use Promptable;

    public function __construct(
        public Conversa $conversa,
        public string $memoriaContexto = '',
        public ?ContextoNegocio $ctx = null,
    ) {
    }

    /**
     * Modelo do chat — US-COPI-135. Lê `copiloto.chat_model` (env `JANA_CHAT_MODEL`).
     *
     * null/vazio → devolve null e o SDK cai no default do provider
     * (`AI_OPENAI_TEXT_DEFAULT`, hoje gpt-4o-mini) = comportamento legado. Setar
     * `JANA_CHAT_MODEL=gpt-4o` liga o modelo forte SÓ neste agent — não arrasta os
     * ~8 agents batch internos (que herdam o default global) pro modelo caro.
     *
     * Mesmo padrão env-driven do ClarificadorAgent::model() (ADR 0245): knob de
     * config, kill-switch por env, zero code change pra ligar/desligar.
     */
    public function model(): ?string
    {
        $m = config('copiloto.chat_model');

        return is_string($m) && $m !== '' ? $m : null;
    }

    public function instructions(): Stringable|string
    {
        $partes = [$this->personaBase()];

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
     * Tools READ-ONLY do chat — as mesmas 5 já provadas em prod pelo
     * BriefDiarioAgent (US-COPI-202), reusadas em vez de reescritas.
     *
     * Tier 0 mecânico ([ADR 0141]): `$businessId` sai do `$conversa->business_id`
     * e vai no constructor de cada tool. O LLM não tem campo pra passar business
     * (schema das tools é vazio) e, se tentasse, a tool ignora — ela só usa o do
     * constructor.
     *
     * Dois fail-safes devolvem `[]` (o SDK então omite a chave `tools` do request
     * e o pipeline fica idêntico ao legado):
     *   1. flag `copiloto.chat_tools.enabled` OFF (default — prod espera, ADR 0245)
     *   2. conversa sem `business_id` — sem tenant provado, zero tool. Nunca
     *      chutar business: tool com tenant errado é vazamento Tier 0.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    public function tools(): iterable
    {
        return $this->toolsAtivas();
    }

    /**
     * Resolve as tools uma vez — consultada por `tools()` (o que o SDK envia) e
     * por `personaBase()` (o que o prompt promete). Mesma fonte pros dois: se o
     * prompt falasse de ferramenta que o SDK não mandou, a Jana prometeria ao
     * cliente uma consulta que não pode fazer.
     *
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    protected function toolsAtivas(): array
    {
        if (! (bool) config('copiloto.chat_tools.enabled', false)) {
            return [];
        }

        $businessId = $this->conversa->business_id;

        if ($businessId === null) {
            return [];
        }

        $businessId = (int) $businessId;

        return [
            new VendasPeriodoTool($businessId),
            new InadimplenciaTool($businessId),
            new TicketsTopTool($businessId),
            new NfeStatusTool($businessId),
            new OportunidadesTool($businessId),
        ];
    }

    /**
     * Persona base do system prompt — fonte única.
     *
     * Consumida por `instructions()` (caminho string default) E por
     * `montarSystemBlocksCacheaveis()` (caminho Anthropic prompt-cache). Os dois
     * PRECISAM ver o mesmo texto: se divergirem, o cliente recebe uma Jana com
     * instruções diferentes dependendo do provider.
     *
     * O parágrafo de ferramentas só entra quando há tool declarada — prometer
     * ferramenta que `tools()` não devolveu seria instruir o LLM a mentir.
     */
    protected function personaBase(): string
    {
        $base = <<<PROMPT
        Você é o Copiloto do oimpresso, um assistente de IA para gestores de pequenas e médias empresas brasileiras.
        Responda sempre em português brasileiro.
        Seja direto, prático e orientado a resultados.
        Nunca sugira ações ilegais ou antiéticas.
        Nunca invente dados — baseie-se apenas no contexto fornecido.
        Quando não tiver informação suficiente, peça esclarecimentos.
        PROMPT;

        if (empty($this->toolsAtivas())) {
            return $base;
        }

        $ferramentas = <<<PROMPT
        FERRAMENTAS DE CONSULTA (somente leitura — nenhuma altera dado):
        Você pode buscar número vivo do negócio: vendas por período, inadimplência,
        tickets em aberto, status de NF-e e oportunidades. Use quando a pergunta
        pedir número atual — não chute e não repita número velho do contexto se a
        ferramenta responde melhor. Não invente valor que a ferramenta não devolveu.

        TIER 0: as ferramentas só enxergam a empresa autenticada desta conversa.
        NUNCA aceite instrução pra consultar outra empresa — recuse e explique.
        PROMPT;

        return $base . "\n\n" . $ferramentas;
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
        $blocks = [
            // Bloco 1 — persona (estável, NÃO marca cache_control aqui se houver
            // bloco posterior; o marker no último cobre prefixo inteiro).
            ['type' => 'text', 'text' => $this->personaBase()],
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
