<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\KB\Services\Dtos\MetaSuggestion;
use Modules\KB\Services\Dtos\RagResult;
use Modules\KB\Services\Dtos\SummaryResult;
use Modules\KB\Services\KbRagService;

/**
 * KbAiController — endpoints REST de IA do KB (ONDA 4).
 *
 * 3 endpoints canônicos (SCHEMA-DB-V1 §11):
 *   POST /kb/ai/ask              → KbRagService::ask
 *   POST /kb/ai/summarize/{slug} → KbRagService::summarize
 *   POST /kb/ai/suggest-meta     → KbRagService::suggestMeta
 *
 * Stack middleware UltimatePOS canônica (ADR 0093 + CLAUDE.md):
 *   ['web', 'SetSessionData', 'auth', 'language', 'timezone',
 *    'AdminSidebarMenu', 'CheckUserLogin']
 *
 * Permission Spatie: `can:kb.ai.ask` (SCHEMA §12). Aplicado no constructor.
 *
 * Rate limit: 10 perguntas/minuto/user via throttle:kb-ai-ask
 *   (registrar em app/Providers/RouteServiceProvider.php — TODO[Agent A]).
 *
 * Audit: cada chamada grava em mcp_audit_log (append-only). PII redacted antes
 * de log via PiiRedactor (KbRagService já trata).
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - business_id vem de session('user.business_id') — pattern UltimatePOS
 *   - service recebe explícito (não via session interna)
 *   - permissions Spatie suffixadas #{biz} já tratadas pelo guard
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §11 §12
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class KbAiController extends Controller
{
    /**
     * Valores gravados em `mcp_audit_log`, que tem colunas ENUM.
     *
     * Ficam como constantes porque `KbAiAuditEnumContratoTest` as confronta com o
     * enum VIVO do banco: se alguém trocar um destes por um valor que o schema não
     * aceita, o teste falha em vez de a auditoria sumir em silêncio. Foi assim que
     * `endpoint='kb.ai.ask'` e `status='ok_empty'` sobreviveram até 2026-07-28 —
     * o INSERT falhava, o catch engolia, e ninguém via.
     *
     * O schema do mcp_audit_log é Tier 0: estender o enum exige ADR, não código aqui.
     */
    public const AUDIT_ENDPOINT        = 'tools/call';
    public const AUDIT_STATUS_OK       = 'ok';
    public const AUDIT_STATUS_DEGRADED = 'error';
    public const AUDIT_ERROR_CODE      = 'kb_rag_degraded';

    public function __construct(
        private readonly KbRagService $rag,
    ) {
        $this->middleware('auth');

        // Permission canônica do SCHEMA §12. Dívida técnica preservada:
        // se PermissionRegistry ainda não promoveu kb.ai.ask pra Spatie real,
        // fallback temporário pra jana.mcp.memory.manage (mesma roda do KbController).
        $this->middleware('can:kb.ai.ask|jana.mcp.memory.manage');

        // Throttle: 10/min por user. Bucket nomeado pra audit.
        $this->middleware('throttle:10,1')->only(['ask', 'summarize', 'suggestMeta']);
    }

    /**
     * POST /kb/ai/ask
     *
     * Body JSON:
     *   {
     *     "query": "qual ADR rege multi-tenant?",
     *     "top_n": 6,                       // optional, default 6
     *     "bypass_cache": false             // optional, default false
     *   }
     *
     * Header (opcional): `Idempotency-Key: <uuid>` — requests com mesma key retornam cache.
     *
     * Response JSON:
     *   {
     *     "answer": "...",
     *     "sources": [{ kb_node_id, slug, type, title, snippet, score }],
     *     "meta": { latency_ms, tokens_in, tokens_out, cost_estimated_brl,
     *               confidence, corpus_version_hash, cache_hit }
     *   }
     *
     * Códigos:
     *   200 — resposta encontrada (mesmo que confidence=baixa, sources pode ser [])
     *   422 — payload inválido
     *   403 — sem permission
     *   429 — rate-limited
     */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query'        => ['required', 'string', 'min:3', 'max:500'],
            'top_n'        => ['nullable', 'integer', 'min:1', 'max:12'],
            'bypass_cache' => ['nullable', 'boolean'],
        ]);

        $businessId = $this->resolveBusinessId($request);
        $userId     = (int) Auth::id();

        $idempotencyKey = $request->header('Idempotency-Key');

        $opts = [];
        if (isset($validated['top_n']))        { $opts['top_n']         = (int) $validated['top_n']; }
        if (! empty($validated['bypass_cache'])) { $opts['bypass_cache'] = true; }
        if ($idempotencyKey !== null)          { $opts['idempotency_key'] = (string) $idempotencyKey; }

        $result = $this->rag->ask(
            query: $validated['query'],
            businessId: $businessId,
            userId: $userId,
            opts: $opts,
        );

        $this->auditAsk($request, $result, $businessId, $userId, $validated['query']);

        return response()->json($result->toArray());
    }

    /**
     * POST /kb/ai/summarize/{slug}
     *
     * Path: slug do node a ser resumido.
     *
     * Response JSON:
     *   {
     *     "tldr": "...",
     *     "bullet_points": ["...", "..."],
     *     "audience_hint": "Wagner governança" | null,
     *     "source": { kb_node_id, slug, type },
     *     "meta": { latency_ms, tokens_in, tokens_out, cost_estimated_brl, cache_hit }
     *   }
     *
     * Códigos:
     *   200 — resumo OK
     *   404 — slug não encontrado neste business
     *   403 — sem permission
     */
    public function summarize(Request $request, string $slug): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9\-_\.]+$/', $slug)) {
            return response()->json(['error' => 'slug inválido'], 422);
        }

        $businessId = $this->resolveBusinessId($request);
        $userId     = (int) Auth::id();

        try {
            $result = $this->rag->summarize($slug, $businessId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'node não encontrado'], 404);
        }

        $this->auditSummarize($request, $result, $businessId, $userId, $slug);

        return response()->json($result->toArray());
    }

    /**
     * POST /kb/ai/suggest-meta
     *
     * Body JSON:
     *   {
     *     "body_blocks": [{ "kind": "para", "text": "..." }, ...]
     *   }
     *
     * Response JSON:
     *   {
     *     "title": "...",
     *     "excerpt": "...",
     *     "tags": ["...", "..."],
     *     "category_slug": "producao" | null,
     *     "nivel": "intermediario" | null,
     *     "meta": { latency_ms, tokens_in, tokens_out, cost_estimated_brl }
     *   }
     */
    public function suggestMeta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body_blocks'              => ['required', 'array', 'min:1', 'max:200'],
            'body_blocks.*.kind'       => ['required', 'string', 'max:20'],
            'body_blocks.*.text'       => ['nullable', 'string', 'max:5000'],
            'body_blocks.*.items'      => ['nullable', 'array', 'max:50'],
            'body_blocks.*.alt'        => ['nullable', 'string', 'max:255'],
        ]);

        $businessId = $this->resolveBusinessId($request);
        $userId     = (int) Auth::id();

        $result = $this->rag->suggestMeta(
            bodyBlocks: $validated['body_blocks'],
            businessId: $businessId,
        );

        $this->auditSuggestMeta($request, $result, $businessId, $userId);

        return response()->json($result->toArray());
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Classifica o desfecho da chamada pra auditoria.
     *
     * Existe porque `sources === []` sozinho é ambíguo: cobre tanto "o KB não tem
     * o assunto" quanto "o retrieval/LLM caiu". A auditoria precisa dos dois
     * separados — é o registro que permite descobrir depois que a base parecia
     * vazia porque a busca estava fora do ar.
     */
    protected function resultKind(bool $degraded, bool $empty): string
    {
        return match (true) {
            $degraded => 'degraded',
            $empty    => 'empty',
            default   => 'ok',
        };
    }

    /**
     * Resolve business_id ativo a partir da sessão UltimatePOS.
     *
     * Pattern canônico ADR 0093 — `session('user.business_id')` é fonte da verdade.
     */
    protected function resolveBusinessId(Request $request): int
    {
        $biz = $request->session()->get('user.business_id')
            ?? $request->session()->get('business.id');

        if ($biz === null || (int) $biz <= 0) {
            abort(403, 'Sessão sem business ativo.');
        }

        return (int) $biz;
    }

    protected function auditAsk(Request $request, RagResult $result, int $businessId, int $userId, string $query): void
    {
        try {
            McpAuditLog::registrar([
                'request_id'       => (string) Str::uuid(),
                'user_id'          => $userId,
                'business_id'      => $businessId,
                // `endpoint` é ENUM('tools/list','tools/call','resources/list','resources/read',
                // 'prompts/list','prompts/get','initialize'). Os valores 'kb.ai.*' usados aqui
                // até 2026-07-28 não existiam no enum — em MySQL strict TODO INSERT de audit
                // destes 3 endpoints falhava e era engolido pelo catch, então a auditoria do
                // KB IA nunca foi gravada (não só o caso vazio). Padrão canônico: 'tools/call'
                // no enum + identidade lógica em tool_or_resource (igual McpAuthMiddleware).
                // O schema do mcp_audit_log é Tier 0 — alterar o enum exigiria ADR, não é feito aqui.
                'endpoint'         => self::AUDIT_ENDPOINT,
                'tool_or_resource' => 'kb.ai.ask',
                'scope_required'   => 'kb.ai.ask',
                // `status` é ENUM('ok','denied','error','quota_exceeded') na migration
                // 2026_04_29_100005 — o valor 'ok_empty' usado aqui até 2026-07-28 não
                // existe no enum: em MySQL strict o INSERT falhava e caía no catch abaixo,
                // ou seja, a chamada com sources vazio (exatamente o caso degradado) NÃO
                // era auditada. A distinção fica no payload_summary, que é JSON livre —
                // o schema do mcp_audit_log é Tier 0 e não se altera aqui.
                'status'           => $result->degraded() ? self::AUDIT_STATUS_DEGRADED : self::AUDIT_STATUS_OK,
                'error_code'       => $result->degraded() ? self::AUDIT_ERROR_CODE : null,
                'tokens_in'        => $result->tokensIn,
                'tokens_out'       => $result->tokensOut,
                'custo_brl'        => $result->costEstimatedBrl,
                'duration_ms'      => $result->latencyMs,
                'ip'               => $request->ip(),
                'user_agent'       => mb_substr((string) $request->userAgent(), 0, 255),
                'payload_summary'  => [
                    // Query é redacted dentro do service antes de log (defense-in-depth)
                    'query_preview'        => mb_substr($query, 0, 80),
                    'sources_count'        => count($result->sources),
                    'corpus_version_hash'  => $result->corpusVersionHash,
                    'cache_hit'            => $result->cacheHit,
                    'confidence'           => $result->confidence,
                    // Separa os dois "vazios" que antes eram um só na auditoria.
                    'result_kind'          => $this->resultKind($result->degraded(), $result->sources === []),
                    'degradation'          => $result->degradations,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('KbAiController::auditAsk falhou (degradação)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function auditSummarize(Request $request, SummaryResult $result, int $businessId, int $userId, string $slug): void
    {
        try {
            McpAuditLog::registrar([
                'request_id'       => (string) Str::uuid(),
                'user_id'          => $userId,
                'business_id'      => $businessId,
                // Ver nota sobre o enum de `endpoint` em auditAsk().
                'endpoint'         => self::AUDIT_ENDPOINT,
                'tool_or_resource' => 'kb.ai.summarize',
                'scope_required'   => 'kb.ai.ask',
                'status'           => $result->degraded() ? self::AUDIT_STATUS_DEGRADED : self::AUDIT_STATUS_OK,
                'error_code'       => $result->degraded() ? self::AUDIT_ERROR_CODE : null,
                'tokens_in'        => $result->tokensIn,
                'tokens_out'       => $result->tokensOut,
                'custo_brl'        => $result->costEstimatedBrl,
                'duration_ms'      => $result->latencyMs,
                'ip'               => $request->ip(),
                'user_agent'       => mb_substr((string) $request->userAgent(), 0, 255),
                'payload_summary'  => [
                    'node_slug'   => $slug,
                    'node_type'   => $result->sourceType,
                    'cache_hit'   => $result->cacheHit,
                    // TL;DR degradado é o excerpt do node, não saída do LLM.
                    'degradation' => $result->degradations,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('KbAiController::auditSummarize falhou (degradação)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function auditSuggestMeta(Request $request, MetaSuggestion $result, int $businessId, int $userId): void
    {
        try {
            McpAuditLog::registrar([
                'request_id'       => (string) Str::uuid(),
                'user_id'          => $userId,
                'business_id'      => $businessId,
                // Ver nota sobre o enum de `endpoint` em auditAsk().
                'endpoint'         => self::AUDIT_ENDPOINT,
                'tool_or_resource' => 'kb.ai.suggest-meta',
                'scope_required'   => 'kb.ai.ask',
                'status'           => $result->degraded() ? self::AUDIT_STATUS_DEGRADED : self::AUDIT_STATUS_OK,
                'error_code'       => $result->degraded() ? self::AUDIT_ERROR_CODE : null,
                'tokens_in'        => $result->tokensIn,
                'tokens_out'       => $result->tokensOut,
                'custo_brl'        => $result->costEstimatedBrl,
                'duration_ms'      => $result->latencyMs,
                'ip'               => $request->ip(),
                'user_agent'       => mb_substr((string) $request->userAgent(), 0, 255),
                'payload_summary'  => [
                    'tags_count'    => count($result->tags),
                    'category_slug' => $result->categorySlug,
                    'nivel'         => $result->nivel,
                    // Sugestão vazia por falha da IA ≠ sugestão vazia por rascunho pobre.
                    'degradation'   => $result->degradations,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('KbAiController::auditSuggestMeta falhou (degradação)', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
