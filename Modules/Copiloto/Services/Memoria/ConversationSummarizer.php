<?php

namespace Modules\Copiloto\Services\Memoria;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Entities\Mensagem;

/**
 * MEM-S8-2 (ADR 0037 Sprint 8) — Comprime histórico de conversas longas.
 *
 * Antes: hot window com últimas 20 mensagens cresce linear → após 30 turnos
 * o system prompt tem 5k+ tokens, dos quais só 1k são relevantes.
 *
 * Depois: quando conversa > 15 turnos, dispara summarizer:
 *   - Pega TODAS msgs except as últimas 8
 *   - Resume em ~200 tokens via LLM cheap (gpt-4o-mini)
 *   - Salva em conversa.metadata['summary'] + summary_tokens
 *   - ChatCopilotoAgent::messages() lê summary + últimas 8 (vs todas 20)
 *
 * Ganho esperado:
 *   - Conversas curtas (<15 turnos): 0% impacto
 *   - Conversas médias (15-30 turnos): -40% tokens hot window
 *   - Conversas longas (>30 turnos): -70% tokens hot window
 *
 * Custo: 1× LLM call cheap (~200 tokens out × $0.60/M = $0.00012 = R$ 0,00066)
 * por compressão. Compressão amortizada em 5+ turnos seguintes.
 *
 * Trigger: chamado por ChatCopilotoAgent ou via job after-response.
 */
class ConversationSummarizer
{
    /**
     * Threshold de turnos pra disparar summary. Default 15.
     * Pode ajustar via config copiloto.summarizer.threshold_turnos.
     */
    protected int $thresholdTurnos;

    /**
     * Quantas mensagens recentes manter SEM resumir.
     * Default 8 (4 turnos user/assistant — contexto imediato preservado).
     */
    protected int $msgsRecentes;

    public function __construct()
    {
        $this->thresholdTurnos = (int) config('copiloto.summarizer.threshold_turnos', 15);
        $this->msgsRecentes = (int) config('copiloto.summarizer.msgs_recentes', 8);
    }

    /**
     * Verifica se a conversa precisa de summary, gera, e salva.
     *
     * @return array{
     *   compressed: bool,
     *   summary?: string,
     *   tokens_before?: int,
     *   tokens_after_estimated?: int,
     * }
     */
    public function comprimirSeNecessario(Conversa $conv): array
    {
        $totalTurnos = $conv->mensagens()
            ->whereIn('role', ['user', 'assistant'])
            ->count() / 2; // 1 turno = 1 user + 1 assistant

        if ($totalTurnos < $this->thresholdTurnos) {
            return ['compressed' => false];
        }

        // Verifica se já tem summary ATUAL (atualizado no último turno)
        $metadata = $conv->metadata ?? [];
        $summaryTurnos = $metadata['summary_turnos'] ?? 0;

        // Re-resume só se passou +5 turnos desde último summary (amortização)
        if (($totalTurnos - $summaryTurnos) < 5) {
            return ['compressed' => false, 'reason' => 'amortizando_summary_anterior'];
        }

        return $this->comprimir($conv, $totalTurnos);
    }

    protected function comprimir(Conversa $conv, int $totalTurnos): array
    {
        // Pega TODAS msgs except as últimas N
        $allMsgs = $conv->mensagens()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get();

        $msgsParaResumir = $allMsgs->slice(0, max(0, $allMsgs->count() - $this->msgsRecentes));

        if ($msgsParaResumir->isEmpty()) {
            return ['compressed' => false, 'reason' => 'sem_msgs_pra_resumir'];
        }

        $tokensBefore = (int) $msgsParaResumir->sum(
            fn (Mensagem $m) => mb_strlen($m->content) / 4 // ~4 chars/token PT
        );

        // Constrói transcript pra LLM
        $transcript = $msgsParaResumir
            ->map(fn (Mensagem $m) => strtoupper($m->role) . ': ' . mb_substr($m->content, 0, 800))
            ->implode("\n\n");

        try {
            // laravel/ai AnonymousAgent — define instructions+messages+tools e chama prompt().
            // Mais leve que Vizra Agent — não precisa criar classe.
            $agent = new AnonymousAgent(
                instructions: $this->systemPromptResumo(),
                messages: [],
                tools: [],
            );
            $response = $agent->prompt(
                "Conversa pra resumir:\n\n{$transcript}"
            );

            $summary = (string) $response;
            $tokensAfter = (int) ($response->usage->completionTokens ?? mb_strlen($summary) / 4);

            // Persiste no metadata da Conversa
            $newMetadata = array_merge($conv->metadata ?? [], [
                'summary' => $summary,
                'summary_turnos' => $totalTurnos,
                'summary_tokens' => $tokensAfter,
                'summary_compressao' => round($tokensBefore / max(1, $tokensAfter), 1),
                'summary_gerado_em' => now()->toIso8601String(),
            ]);
            $conv->update(['metadata' => $newMetadata]);

            Log::channel('copiloto-ai')->info('ConversationSummarizer: comprimido', [
                'conversa_id' => $conv->id,
                'turnos' => $totalTurnos,
                'msgs_resumidas' => $msgsParaResumir->count(),
                'tokens_before' => $tokensBefore,
                'tokens_after' => $tokensAfter,
                'compressao' => round($tokensBefore / max(1, $tokensAfter), 1),
            ]);

            return [
                'compressed' => true,
                'summary' => $summary,
                'tokens_before' => $tokensBefore,
                'tokens_after_estimated' => $tokensAfter,
                'compression_ratio' => round($tokensBefore / max(1, $tokensAfter), 2),
            ];
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('ConversationSummarizer: erro', [
                'conversa_id' => $conv->id,
                'error' => $e->getMessage(),
            ]);

            return ['compressed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Recupera summary atual da conversa, se existir e válido.
     */
    public function recuperarSummary(Conversa $conv): ?string
    {
        $metadata = $conv->metadata ?? [];
        return $metadata['summary'] ?? null;
    }

    /**
     * Prompt de sistema pro LLM resumir.
     */
    protected function systemPromptResumo(): string
    {
        return <<<PROMPT
        Você é um summarizer de conversa entre user e assistente IA.
        OBJETIVO: comprimir histórico em ~200 tokens, preservando:
          1. Fatos numéricos mencionados (R$, %, datas, métricas)
          2. Decisões tomadas pelo user (mudanças de meta, preferências)
          3. Pendências em aberto
          4. Tom/contexto emocional se relevante (cliente frustrado, etc)

        FORMATO obrigatório:
        - Texto único, máximo 4 parágrafos curtos
        - Sem listas numeradas
        - Sem markdown
        - Português brasileiro
        - Tempo verbal passado: "user perguntou X, assistente respondeu Y, depois user pediu Z"

        EVITAR:
        - Repetir saudações ou small talk
        - Detalhes de implementação (HOW), foque no WHAT/WHY
        - Inventar fatos que não estão no transcript
        PROMPT;
    }
}
