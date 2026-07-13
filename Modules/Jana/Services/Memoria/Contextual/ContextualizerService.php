<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria\Contextual;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Telemetry\LangfuseClient;

/**
 * Contextual Retrieval Anthropic — gera contexto curto (50-100 tokens)
 * pra cada chunk descrevendo o documento de origem ANTES do embedding/BM25.
 *
 * Anthropic blog 2024-09-19: "Introducing Contextual Retrieval"
 *   - Contextual Embeddings: -49% failed retrievals
 *   - Contextual Embeddings + Contextual BM25 + Reranking: -67% failed retrievals
 *
 * Custo: ~$1.02 por 1M tokens de documento (Haiku 4.5 com prompt caching).
 * Operacional pra ~1.500 docs oimpresso (estimativa <$1/dia steady state).
 *
 * Pipeline canônico:
 *
 *   chunk + doc_full ──▶ Haiku 4.5 (system: SYSTEM_PROMPT, doc com cache_control)
 *                    ──▶ contexto 50-100 tokens
 *                    ──▶ prepend ao chunk antes de embedding/BM25
 *
 * Prompt caching (PromptCacheConfig):
 *   - Bloco doc COMPLETO marcado `cache_control: ephemeral` (5min TTL).
 *   - Reusa entre N chunks do mesmo documento — 1 cache write + N cache reads.
 *   - Reads custam 10% do input regular (Haiku: $0.10 vs $1.00 por 1M tokens).
 *
 * Pré-req: feature flag `copiloto.contextual_retrieval.enabled` = true.
 *
 * Multi-tenant: opera sobre tabela `mcp_memory_documents` (repo-wide, sem
 * business_id scope por design — ADR 0053). Backfill respeita business_id
 * herdado do doc original.
 *
 * @see https://www.anthropic.com/news/contextual-retrieval
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 * @see memory/requisitos/Jana/CONTEXTUAL-RETRIEVAL-ANTHROPIC.md
 */
final class ContextualizerService
{
    /**
     * System prompt canônico Anthropic (do blog post 2024-09-19).
     *
     * Placeholders {{WHOLE_DOCUMENT}} e {{CHUNK_CONTENT}} são substituídos
     * em runtime. WHOLE_DOCUMENT é o bloco cacheável (sempre vem antes do CHUNK
     * pra prefix caching reutilizar entre chunks do mesmo doc).
     */
    public const SYSTEM_PROMPT_TEMPLATE = <<<'PROMPT'
        <document>
        {{WHOLE_DOCUMENT}}
        </document>
        Here is the chunk we want to situate within the whole document
        <chunk>
        {{CHUNK_CONTENT}}
        </chunk>
        Please give a short succinct context to situate this chunk within
        the overall document for the purposes of improving search retrieval
        of the chunk. Answer only with the succinct context and nothing else.
        PROMPT;

    /** Endpoint canônico Anthropic Messages API. */
    private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';

    /** Versão da API Anthropic (header obrigatório). */
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Gera contexto pra 1 chunk individual. Aplica prompt caching no documento
     * (reusa cache se chamada já tiver sido feita ≤5min antes pro mesmo doc).
     *
     * @param  string  $documentFull  Documento inteiro (cacheável)
     * @param  string  $chunkContent  Chunk específico pra contextualizar
     * @return string Contexto 50-100 tokens (sem prefix/suffix)
     */
    public function contextualize(string $documentFull, string $chunkContent): string
    {
        return OtelHelper::spanBiz('jana.contextual_retrieval.contextualize', function () use ($documentFull, $chunkContent) {
            return $this->doContextualize($documentFull, $chunkContent);
        }, [
            'doc_chars' => strlen($documentFull),
            'chunk_chars' => strlen($chunkContent),
        ]);
    }

    private function doContextualize(string $documentFull, string $chunkContent): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        // Mock mode pra Pest local (sem custo, sem rede). Pattern H4 RAGAS.
        if ($this->isForceMock()) {
            return $this->mockContext($documentFull, $chunkContent);
        }

        $model = (string) config('copiloto.contextual_retrieval.cheap_model', 'claude-haiku-4-5-20251001');
        $maxTokens = (int) config('copiloto.contextual_retrieval.context_max_tokens', 100);

        $apiKey = (string) (config('copiloto.contextual_retrieval.api_key', '') ?: env('ANTHROPIC_API_KEY', ''));
        if ($apiKey === '') {
            Log::channel('copiloto-ai')->warning('ContextualizerService: ANTHROPIC_API_KEY ausente — pulando');

            return '';
        }

        // System prompt SEM chunk (chunk vai como user message).
        // Doc vai no system com cache_control: ephemeral — reusa entre N chunks.
        $systemBlocks = [
            [
                'type' => 'text',
                'text' => '<document>'."\n".$documentFull."\n".'</document>',
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type' => 'text',
                'text' => 'Please give a short succinct context to situate the chunk that will come within '
                    .'the overall document above for the purposes of improving search retrieval of the chunk. '
                    .'Answer only with the succinct context and nothing else.',
            ],
        ];

        $userMessage = "Here is the chunk we want to situate within the whole document:\n"
            ."<chunk>\n".$chunkContent."\n</chunk>";

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemBlocks,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        try {
            $t0 = microtime(true);

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])
                ->timeout(30)
                ->post(self::ANTHROPIC_API_URL, $payload);

            if (! $response->successful()) {
                Log::channel('copiloto-ai')->warning('ContextualizerService: HTTP falha', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return '';
            }

            $data = $response->json();
            $context = $this->extrairConteudoTexto($data);

            $usage = $data['usage'] ?? [];
            Log::channel('copiloto-ai')->debug('ContextualizerService::contextualize', [
                'model' => $model,
                'chunk_chars' => strlen($chunkContent),
                'doc_chars' => strlen($documentFull),
                'context_chars' => strlen($context),
                'input_tokens' => $usage['input_tokens'] ?? null,
                'cache_creation_input_tokens' => $usage['cache_creation_input_tokens'] ?? null,
                'cache_read_input_tokens' => $usage['cache_read_input_tokens'] ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
            ]);

            // US-COPI-108 (reconciliação 2026-07-12): call-site Http:: direto, fora do
            // listener global Langfuse — instrumentação inline. Input omitido de
            // propósito (doc inteiro é grande e potencialmente sensível); contadores
            // + cache tokens vão em metadata. No-op se langfuse.enabled=false.
            app(LangfuseClient::class)->traceComGeneration([
                'name'        => 'contextual-retrieval',
                'business_id' => null, // mcp_memory_documents é repo-wide (ADR 0053)
                'tool'        => 'contextualizer',
                'metadata'    => ['doc_chars' => strlen($documentFull), 'chunk_chars' => strlen($chunkContent)],
            ], [
                'name'        => 'contextualize-chunk',
                'model'       => $model,
                'output'      => trim($context),
                'usage'       => [
                    'input'  => $usage['input_tokens'] ?? null,
                    'output' => $usage['output_tokens'] ?? null,
                ],
                'metadata'    => [
                    'cache_creation_input_tokens' => $usage['cache_creation_input_tokens'] ?? null,
                    'cache_read_input_tokens'     => $usage['cache_read_input_tokens'] ?? null,
                ],
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);

            return trim($context);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('ContextualizerService exception: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Batch contextualize — N chunks do MESMO documento, reusa cache_control.
     *
     * Estratégia: itera os chunks sequencialmente. A primeira chamada paga
     * `cache_creation_input_tokens` (1.25× input regular); as N-1 seguintes
     * pagam `cache_read_input_tokens` (0.1× input regular).
     *
     * Math (Haiku 4.5):
     *   - Doc 8k tokens, 10 chunks de 800 tokens.
     *   - SEM cache: 10 × 8k × $1.00/1M = $0.08
     *   - COM cache: 1 × 8k × $1.25/1M + 9 × 8k × $0.10/1M = $0.0172 (~78% economia)
     *
     * @param  string  $documentFull  Documento (cacheado entre chamadas)
     * @param  array<string>  $chunks  Lista de chunks pra contextualizar
     * @return array<string, string>  map sha1(chunk) => contexto
     */
    public function contextualizeBatch(string $documentFull, array $chunks): array
    {
        $resultado = [];

        foreach ($chunks as $chunk) {
            $hash = sha1($chunk);
            $resultado[$hash] = $this->contextualize($documentFull, $chunk);
        }

        return $resultado;
    }

    /**
     * Estima custo Anthropic Haiku 4.5 pra 1 doc + N chunks (apenas pricing
     * input — output é negligível em 50-100 tokens).
     *
     * Pricing snapshot 2026 (config('copiloto.mcp.pricing_per_million.haiku')):
     *   - input: $1.00 / 1M tokens
     *   - cache_read: $0.10 / 1M tokens
     *   - cache_write: $1.25 / 1M tokens
     *
     * @return array{usd:float, brl:float, breakdown:array}
     */
    public function estimarCusto(int $docTokens, int $numChunks): array
    {
        $pricing = (array) config('copiloto.mcp.pricing_per_million.haiku', [
            'input' => 1.00,
            'output' => 5.00,
            'cache_read' => 0.10,
            'cache_write' => 1.25,
        ]);

        $cambio = (float) config('copiloto.ai.cambio_brl_usd', 5.50);

        // 1 cache_write na 1ª chamada + (N-1) cache_reads nas seguintes.
        $cacheWriteTokens = $docTokens;
        $cacheReadTokens = max(0, $numChunks - 1) * $docTokens;
        $outputTokens = $numChunks * 100; // ~100 tokens por contexto

        $usdCacheWrite = ($cacheWriteTokens / 1_000_000) * (float) ($pricing['cache_write'] ?? 1.25);
        $usdCacheRead = ($cacheReadTokens / 1_000_000) * (float) ($pricing['cache_read'] ?? 0.10);
        $usdOutput = ($outputTokens / 1_000_000) * (float) ($pricing['output'] ?? 5.00);

        $usdTotal = $usdCacheWrite + $usdCacheRead + $usdOutput;

        return [
            'usd' => round($usdTotal, 6),
            'brl' => round($usdTotal * $cambio, 4),
            'breakdown' => [
                'cache_write_tokens' => $cacheWriteTokens,
                'cache_read_tokens' => $cacheReadTokens,
                'output_tokens' => $outputTokens,
                'usd_cache_write' => round($usdCacheWrite, 6),
                'usd_cache_read' => round($usdCacheRead, 6),
                'usd_output' => round($usdOutput, 6),
            ],
        ];
    }

    /**
     * Feature flag global.
     */
    public function isEnabled(): bool
    {
        return (bool) config('copiloto.contextual_retrieval.enabled', false);
    }

    /**
     * Mock mode pra Pest local (não bate em rede).
     */
    public function isForceMock(): bool
    {
        return (bool) (config('copiloto.contextual_retrieval.force_mock', false)
            || env('CONTEXTUAL_RETRIEVAL_FORCE_MOCK', false));
    }

    /**
     * Mock determinístico pra Pest. Não chama LLM.
     */
    private function mockContext(string $documentFull, string $chunkContent): string
    {
        $docSnippet = mb_substr(trim($documentFull), 0, 50);
        $chunkSha = substr(sha1($chunkContent), 0, 8);

        return sprintf(
            '[MOCK] Trecho do documento iniciado por "%s..." (sha=%s).',
            $docSnippet,
            $chunkSha,
        );
    }

    /**
     * Extrai texto do array de content blocks Anthropic.
     * Response shape: ['content' => [['type' => 'text', 'text' => '...'], ...]]
     */
    private function extrairConteudoTexto(array $data): string
    {
        $blocks = $data['content'] ?? [];
        $partes = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $partes[] = (string) ($block['text'] ?? '');
            }
        }

        return implode('', $partes);
    }
}
