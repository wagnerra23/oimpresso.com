<?php

declare(strict_types=1);

namespace Modules\KB\Services\Dtos;

/**
 * RagResult — saída canônica do KbRagService::ask().
 *
 * Contém a resposta sintetizada pelo LLM (PT-BR), as sources citadas
 * (top-N nodes que entraram no prompt) com snippet pra UI, e métricas
 * de custo + latência pra audit log.
 *
 * Imutável (readonly) — pra que cache Redis/audit log armazenem snapshot
 * estável sem race condition.
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §11 POST /kb/ai/ask
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 */
final class RagResult
{
    /**
     * @param  string                                                                                                $answer            Resposta PT-BR sintetizada, com citações inline [1][2]
     * @param  array<int,array{kb_node_id:int,slug:string,type:string,title:string,snippet:string,score:float}>     $sources           Top-N nodes que alimentaram o prompt (ordem = numeração [1][2]…)
     * @param  int                                                                                                   $latencyMs         Duração total da chamada ask() (retrieval + LLM)
     * @param  int                                                                                                   $tokensIn          Tokens de input cobrados (system + prompt + sources)
     * @param  int                                                                                                   $tokensOut         Tokens de output cobrados (resposta)
     * @param  float                                                                                                 $costEstimatedBrl  Custo estimado em R$ (modelo × tokens × USD→BRL)
     * @param  string                                                                                                $confidence        alta|media|baixa (estimado pelo LLM ou pelo nº de sources com score ≥0.6)
     * @param  string|null                                                                                           $corpusVersionHash Hash do corpus no momento da query (max updated_at) — usado pra cache invalidation
     * @param  bool                                                                                                  $cacheHit          true se a resposta veio do Redis (não chamou LLM)
     */
    public function __construct(
        public readonly string $answer,
        public readonly array $sources,
        public readonly int $latencyMs,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly float $costEstimatedBrl,
        public readonly string $confidence = 'media',
        public readonly ?string $corpusVersionHash = null,
        public readonly bool $cacheHit = false,
    ) {}

    /**
     * Serializa pra JSON response do endpoint /kb/ai/ask.
     *
     * Shape canônico (SCHEMA-DB-V1 §11):
     *   { answer, sources[], meta: { latency_ms, tokens_in, tokens_out, cost_estimated_brl, confidence, cache_hit } }
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'answer'  => $this->answer,
            'sources' => $this->sources,
            'meta'    => [
                'latency_ms'          => $this->latencyMs,
                'tokens_in'           => $this->tokensIn,
                'tokens_out'          => $this->tokensOut,
                'cost_estimated_brl'  => $this->costEstimatedBrl,
                'confidence'          => $this->confidence,
                'corpus_version_hash' => $this->corpusVersionHash,
                'cache_hit'           => $this->cacheHit,
            ],
        ];
    }

    /**
     * Factory canônico pra resposta de "não encontrei nada no KB".
     *
     * Mesmo nestes casos retornamos um RagResult válido — apenas com `confidence=baixa`,
     * `sources=[]` e mensagem honesta. Custo é o que foi gasto no retrieval (~tokens
     * mínimos do prompt do LLM, se chegou a chamar; zero se short-circuit por corpus vazio).
     */
    public static function notFound(int $latencyMs = 0, ?string $corpusVersionHash = null): self
    {
        return new self(
            answer: 'Não encontrei isso no KB. Quer criar um artigo novo sobre o tema?',
            sources: [],
            latencyMs: $latencyMs,
            tokensIn: 0,
            tokensOut: 0,
            costEstimatedBrl: 0.0,
            confidence: 'baixa',
            corpusVersionHash: $corpusVersionHash,
            cacheHit: false,
        );
    }
}
