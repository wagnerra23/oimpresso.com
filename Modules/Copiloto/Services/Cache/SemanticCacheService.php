<?php

namespace Modules\Copiloto\Services\Cache;

use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\CacheSemantico;
use Modules\Copiloto\Entities\Conversa;

/**
 * MEM-CACHE-1 (ADR 0037 Sprint 8) — Cache semântico de respostas LLM.
 *
 * Uso (no responderChat):
 *
 *   $cache = app(SemanticCacheService::class);
 *
 *   // Tenta hit
 *   if ($cached = $cache->buscar($conv, $mensagem)) {
 *       return $cached->resposta; // economia: zero tokens
 *   }
 *
 *   // Miss — chama LLM normal
 *   $resposta = $llm->generate(...);
 *
 *   // Grava pra futuro
 *   $cache->gravar($conv, $mensagem, $resposta, $tokensIn, $tokensOut);
 *
 *   return $resposta;
 *
 * Hit strategy (cascata, do mais barato pro mais caro):
 *   1. Cache_key exato (SHA256 query_normalizada) — match perfeito
 *   2. FULLTEXT MATCH (top 5) → similaridade Jaccard sobre tokens >= 0.85
 *   3. (Sprint 9) Cosine similarity sobre embedding > 0.95
 *
 * TTL default: 1h (configurável). Pra dados que mudam dentro de 1h
 * (faturamento, vendas hoje, etc), o caller pode invalidar via
 * invalidarPorBusiness($bizId) após eventos críticos.
 */
class SemanticCacheService
{
    /** TTL em segundos. Default 3600 (1h). */
    protected int $ttlSegundos;

    /** Threshold de similaridade textual Jaccard pra hit fuzzy [0..1]. */
    protected float $thresholdJaccard;

    public function __construct()
    {
        $this->ttlSegundos = (int) config('copiloto.cache.ttl_segundos', 3600);
        $this->thresholdJaccard = (float) config('copiloto.cache.threshold_jaccard', 0.85);
    }

    /**
     * Tenta cache hit. Retorna entrada ou null.
     */
    public function buscar(Conversa $conv, string $mensagem): ?CacheSemantico
    {
        $bizId = (int) $conv->business_id;
        $userId = (int) $conv->user_id;
        $normalizada = $this->normalizar($mensagem);
        $key = $this->calcularKey($bizId, $userId, $normalizada);

        // 1. Match exato
        $exato = CacheSemantico::where('cache_key', $key)
            ->doEscopo($bizId, $userId)
            ->naoExpirado()
            ->first();

        if ($exato) {
            $exato->registrarHit();
            $this->logHit($conv, $mensagem, 'exato', $exato);
            return $exato;
        }

        // 2. Fuzzy textual via FULLTEXT (NL mode)
        if (mb_strlen($normalizada) < 4) {
            return null; // muito curto pra fuzzy
        }

        $candidatos = CacheSemantico::query()
            ->doEscopo($bizId, $userId)
            ->naoExpirado()
            ->whereRaw(
                'MATCH(query_normalizada) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$normalizada]
            )
            ->limit(5)
            ->get();

        foreach ($candidatos as $c) {
            $sim = $this->jaccardSimilarity($normalizada, $c->query_normalizada);
            if ($sim >= $this->thresholdJaccard) {
                $c->registrarHit();
                $this->logHit($conv, $mensagem, "fuzzy:{$sim}", $c);
                return $c;
            }
        }

        return null;
    }

    /**
     * Grava resposta no cache.
     */
    public function gravar(
        Conversa $conv,
        string $mensagem,
        string $resposta,
        ?int $tokensIn = null,
        ?int $tokensOut = null,
        array $metadata = []
    ): CacheSemantico {
        $bizId = (int) $conv->business_id;
        $userId = (int) $conv->user_id;
        $normalizada = $this->normalizar($mensagem);
        $key = $this->calcularKey($bizId, $userId, $normalizada);

        $custoBrl = $this->calcularCusto($tokensIn, $tokensOut);

        return CacheSemantico::updateOrCreate(
            ['cache_key' => $key],
            [
                'business_id' => $bizId,
                'user_id' => $userId,
                'query_original' => mb_substr($mensagem, 0, 5000),
                'query_normalizada' => mb_substr($normalizada, 0, 5000),
                'query_embedding' => null, // Sprint 9 — text-embedding-3-small
                'resposta' => $resposta,
                'metadata' => array_merge($metadata, [
                    'gravado_em' => now()->toIso8601String(),
                    'modelo' => config('copiloto.openai.model_chat', 'gpt-4o-mini'),
                ]),
                'hits' => 0,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'custo_brl_original' => $custoBrl,
                'expira_em' => now()->addSeconds($this->ttlSegundos),
            ]
        );
    }

    /**
     * Invalida todas entradas de um business (após evento crítico, ex: novo
     * faturamento registrado). Setando expira_em=now().
     */
    public function invalidarPorBusiness(int $businessId): int
    {
        return CacheSemantico::where('business_id', $businessId)
            ->where('expira_em', '>', now())
            ->update(['expira_em' => now()]);
    }

    public function stats(?int $businessId = null): array
    {
        $q = CacheSemantico::query();
        if ($businessId !== null) $q->where('business_id', $businessId);

        $total = (clone $q)->count();
        $totalHits = (clone $q)->sum('hits');
        $totalEconomizado = (clone $q)->selectRaw('SUM(hits * COALESCE(custo_brl_original, 0)) as eco')
            ->value('eco') ?? 0;

        return [
            'entradas_cache' => $total,
            'total_hits' => (int) $totalHits,
            'hit_rate' => $total > 0 ? round($totalHits / max(1, $total), 2) : 0,
            'r$_economizado' => round((float) $totalEconomizado, 4),
        ];
    }

    // ---- Internos ---------------------------------------------------------

    /**
     * Normaliza pra comparação fuzzy (lowercase + sem acentos + sort spaces).
     * "Qual o Faturamento?" → "qual o faturamento"
     * "QUANTO entrou no caixa?" → "quanto entrou no caixa"
     */
    protected function normalizar(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        // Remove acentos via transliteração
        $s = transliterator_transliterate('Any-Latin; Latin-ASCII;', $s);
        // Remove pontuação
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', '', $s);
        // Normaliza whitespace
        $s = preg_replace('/\s+/', ' ', trim($s));
        return $s ?? '';
    }

    protected function calcularKey(int $businessId, int $userId, string $queryNormalizada): string
    {
        return hash('sha256', "{$businessId}|{$userId}|{$queryNormalizada}");
    }

    /**
     * Jaccard similarity sobre tokens (palavras únicas).
     * Mais robusto que Levenshtein pra paráfrases tipo:
     *   "qual o faturamento" vs "como esta o faturamento" → 0.50
     *   "qual o faturamento" vs "qual faturamento" → 0.67 (sem stopwords seria 1.0)
     */
    protected function jaccardSimilarity(string $a, string $b): float
    {
        $tokensA = array_filter(explode(' ', $a), fn ($t) => mb_strlen($t) > 1);
        $tokensB = array_filter(explode(' ', $b), fn ($t) => mb_strlen($t) > 1);

        if (empty($tokensA) || empty($tokensB)) return 0.0;

        $setA = array_unique($tokensA);
        $setB = array_unique($tokensB);
        $intersect = array_intersect($setA, $setB);
        $union = array_unique(array_merge($setA, $setB));

        return count($union) === 0 ? 0.0 : count($intersect) / count($union);
    }

    /**
     * Custo aproximado em R$ (gpt-4o-mini @ pricing 2026 + cambio 5.5).
     */
    protected function calcularCusto(?int $tokensIn, ?int $tokensOut): float
    {
        if ($tokensIn === null && $tokensOut === null) return 0.0;
        $usd = (($tokensIn ?? 0) * 0.00000015) + (($tokensOut ?? 0) * 0.0000006);
        return round($usd * 5.5, 6);
    }

    protected function logHit(Conversa $conv, string $mensagem, string $tipo, CacheSemantico $cached): void
    {
        Log::channel('copiloto-ai')->info('SemanticCache: HIT', [
            'tipo' => $tipo,
            'business_id' => $conv->business_id,
            'user_id' => $conv->user_id,
            'query_atual' => mb_substr($mensagem, 0, 100),
            'query_cached' => mb_substr($cached->query_original, 0, 100),
            'cache_id' => $cached->id,
            'hits_acumulados' => $cached->hits,
            'r$_economizado' => $cached->totalEconomizado(),
        ]);
    }
}
