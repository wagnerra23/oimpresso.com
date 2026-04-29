<?php

namespace Modules\Copiloto\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Support\ContextoNegocio;
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
class ChatCopilotoAgent implements Agent
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
