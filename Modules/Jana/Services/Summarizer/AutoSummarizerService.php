<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Summarizer;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Throwable;

/**
 * Onda 5 — Agent A1 (Auto-summary docs longos).
 *
 * Service map-reduce gpt-4o-mini (via laravel/ai) com:
 *   - Cache MySQL 24h em `mcp_doc_summaries` (chave content_hash MD5 + model)
 *   - Threshold de ativação (default 8KB) — docs menores passam direto
 *   - Cap mensal R$ (fail-open quando excede — retorna texto truncado)
 *   - Anthropic prompt caching breakpoints documentados no payload markdown
 *     (sentinel `<!--JANA_CACHE_BREAKPOINT_*-->`) — ver §Prompt caching abaixo
 *
 * Estratégia map-reduce (LangChain pattern):
 *   1. CHUNK    — divide texto respeitando markdown headers (## / ###) como
 *      breakpoints naturais (NÃO corta seção no meio). Fallback: window 4-6KB.
 *   2. MAP      — pra cada chunk, LLM produz "mini-summary" ~200 tokens
 *   3. REDUCE   — concatena mini-summaries + 2ª chamada LLM produz summary
 *      final coerente narrativa ~1500 tokens
 *   4. CACHE    — grava `(hash, model) → summary` MySQL 24h TTL (re-resume após)
 *
 * §Prompt caching (Anthropic — surpresa estratégica dossier Onda 5):
 *   - Anthropic prompt caching só funciona em API direta Anthropic (não OpenAI gpt-4o-mini)
 *   - laravel/ai 0.6 não expõe `cache_control` breakpoints em payload de mensagens
 *   - SOLUÇÃO 2026-05-13: gravar metadata pra futura migração (Wagner valida ADR
 *     migrar de gpt-4o-mini → Claude Haiku quando router custo deslocar).
 *     System prompt INSERE sentinels (`<!--JANA_CACHE_BREAKPOINT_SYSTEM-->`,
 *     `<!--JANA_CACHE_BREAKPOINT_KB-->`) — quando AnthropicProvider laravel/ai
 *     ganhar cache_control suporte, basta o decorator detectar sentinel e
 *     transformar em breakpoint `{"type": "ephemeral"}`.
 *   - Test cobertura: sentinels OBRIGATÓRIOS no prompt payload (regression
 *     guard pra mudança de provider futura).
 *
 * @see memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §6 (A1)
 * @see memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §9 (Prompt caching)
 * @see Modules/Jana/Mcp/Tools/HandoffFetchSummarizedTool.php (pattern similar)
 */
class AutoSummarizerService
{
    /**
     * Sentinel marker pro system prompt — futura tradução pra Anthropic
     * cache_control breakpoint quando provider trocar.
     */
    public const CACHE_BREAKPOINT_SYSTEM = '<!--JANA_CACHE_BREAKPOINT_SYSTEM-->';

    /**
     * Sentinel marker pra KB doc estático — futura tradução pra Anthropic
     * cache_control breakpoint (KB doc tem 1h cache hit típico).
     */
    public const CACHE_BREAKPOINT_KB = '<!--JANA_CACHE_BREAKPOINT_KB-->';

    /**
     * Resume `$text` aplicando map-reduce + cache + cap. Retorna summary OU
     * texto original truncado em caso de skip (threshold/cap/falha LLM).
     *
     * @param  string  $text  Texto bruto pra resumir
     * @param  array   $opts  Override: model, target_tokens, force (bypass threshold)
     */
    public function summarize(string $text, array $opts = []): SummaryResult
    {
        $threshold = (int) ($opts['threshold_chars']
            ?? config('copiloto.auto_summarizer.threshold_chars', 8000));

        $model = (string) ($opts['model']
            ?? config('copiloto.auto_summarizer.model', 'gpt-4o-mini'));

        // Threshold: doc pequeno passa direto (zero custo)
        if (! ($opts['force'] ?? false) && mb_strlen($text) < $threshold) {
            return SummaryResult::passthrough($text, 'below_threshold');
        }

        // Cap mensal hard-enforce — fail-open com texto truncado
        if ($this->capExceeded()) {
            Log::channel('copiloto-ai')->warning('auto-summarizer: cap mensal excedido (fail-open)', [
                'current_brl' => $this->monthlySpendBrl(),
                'cap_brl' => (float) config('copiloto.auto_summarizer.max_cost_brl', 10),
            ]);

            return SummaryResult::passthrough(
                $this->truncate($text, $threshold),
                'cap_exceeded'
            );
        }

        $hash = md5($text);

        // Cache hit dentro do TTL
        $cached = $this->fetchCached($hash, $model);
        if ($cached !== null) {
            return SummaryResult::cacheHit($cached, $hash);
        }

        // Map-reduce LLM
        try {
            $result = $this->mapReduce($text, $model, $opts);
        } catch (Throwable $e) {
            Log::channel('copiloto-ai')->warning('auto-summarizer: LLM falhou (fail-open)', [
                'error' => $e->getMessage(),
                'hash' => $hash,
            ]);

            return SummaryResult::passthrough(
                $this->truncate($text, $threshold),
                'llm_error'
            );
        }

        // Persiste cache
        $this->persistCache(
            hash: $hash,
            model: $model,
            originalSize: mb_strlen($text),
            summary: $result->summary,
            tokensIn: $result->tokensIn,
            tokensOut: $result->tokensOut,
            costBrl: $result->costBrl,
        );

        return $result;
    }

    /**
     * Divide texto em chunks respeitando markdown headers (`##`, `###`) como
     * breakpoints naturais — preserva coerência semântica. Fallback window
     * `chunk_size_chars` quando seção é gigante.
     *
     * @return list<string>
     */
    public function chunk(string $text, ?int $chunkSize = null): array
    {
        $chunkSize = $chunkSize ?? (int) config('copiloto.auto_summarizer.chunk_size_chars', 5000);

        // Tenta split por headers H2/H3 primeiro
        $sections = preg_split('/^(##\s|###\s)/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($sections === false || count($sections) < 3) {
            // Sem headers — window simples
            return $this->windowSplit($text, $chunkSize);
        }

        $chunks = [];
        $current = $sections[0] ?? '';
        for ($i = 1; $i < count($sections); $i += 2) {
            $headerMark = $sections[$i] ?? '';
            $body = $sections[$i + 1] ?? '';
            $section = $headerMark . $body;

            if (mb_strlen($current) + mb_strlen($section) > $chunkSize && $current !== '') {
                $chunks[] = trim($current);
                $current = $section;
            } else {
                $current .= $section;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        // Se uma seção sozinha estoura chunk_size, sub-divide por window
        $final = [];
        foreach ($chunks as $c) {
            if (mb_strlen($c) > $chunkSize * 2) {
                $final = array_merge($final, $this->windowSplit($c, $chunkSize));
            } else {
                $final[] = $c;
            }
        }

        return $final;
    }

    /**
     * Cap mensal excedido? Soma `cost_brl` do mês corrente.
     */
    public function capExceeded(): bool
    {
        $cap = (float) config('copiloto.auto_summarizer.max_cost_brl', 10);
        if ($cap <= 0) {
            // Cap=0 desativa serviço (fail-open imediato)
            return true;
        }

        return $this->monthlySpendBrl() >= $cap;
    }

    /**
     * Total gasto R$ no mês corrente (chama DB cada call — Wagner aprova,
     * volume ~50 chamadas/mês, latência irrelevante).
     */
    public function monthlySpendBrl(): float
    {
        try {
            $startOfMonth = CarbonImmutable::now()->startOfMonth();

            $sum = DB::table('mcp_doc_summaries')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('cost_brl');

            return (float) $sum;
        } catch (Throwable $e) {
            // Tabela ainda não migrada — assume 0
            return 0.0;
        }
    }

    // ============================================================
    // Map-reduce internals
    // ============================================================

    /**
     * Pipeline map-reduce completo.
     */
    protected function mapReduce(string $text, string $model, array $opts): SummaryResult
    {
        $targetTokens = (int) ($opts['target_tokens']
            ?? config('copiloto.auto_summarizer.target_tokens', 1500));

        $chunks = $this->chunk($text);

        // MAP — mini-summary por chunk
        $miniSummaries = [];
        $tokensInTotal = 0;
        $tokensOutTotal = 0;

        foreach ($chunks as $i => $chunk) {
            $mini = $this->callLlmMap($chunk, $i + 1, count($chunks));
            $miniSummaries[] = $mini;
            // Estimativa: 1 token ~= 4 chars (PT-BR), proxy aceitável
            $tokensInTotal += (int) (mb_strlen($this->mapSystemPrompt() . $chunk) / 4);
            $tokensOutTotal += (int) (mb_strlen($mini) / 4);
        }

        // REDUCE — concatena + summary final
        $reduced = $this->callLlmReduce($miniSummaries, $targetTokens);
        $tokensInTotal += (int) (mb_strlen($this->reduceSystemPrompt($targetTokens) . implode("\n\n", $miniSummaries)) / 4);
        $tokensOutTotal += (int) (mb_strlen($reduced) / 4);

        $costBrl = $this->calcCostBrl($tokensInTotal, $tokensOutTotal, $model);

        return SummaryResult::generated(
            summary: $reduced,
            hash: md5($text),
            tokensIn: $tokensInTotal,
            tokensOut: $tokensOutTotal,
            costBrl: $costBrl,
            chunks: count($chunks),
        );
    }

    /**
     * Chama LLM pra MAP stage (1 chunk → mini-summary).
     */
    protected function callLlmMap(string $chunk, int $idx, int $total): string
    {
        $agent = new AnonymousAgent(
            instructions: $this->mapSystemPrompt(),
            messages: [],
            tools: [],
        );

        $response = $agent->prompt(
            "Chunk {$idx} de {$total}:\n\n"
            . self::CACHE_BREAKPOINT_KB . "\n"
            . $chunk
        );

        return trim((string) $response);
    }

    /**
     * Chama LLM pra REDUCE stage (mini-summaries → summary final).
     *
     * @param  list<string>  $miniSummaries
     */
    protected function callLlmReduce(array $miniSummaries, int $targetTokens): string
    {
        $agent = new AnonymousAgent(
            instructions: $this->reduceSystemPrompt($targetTokens),
            messages: [],
            tools: [],
        );

        $joined = implode("\n\n---\n\n", $miniSummaries);

        $response = $agent->prompt(
            "Mini-resumos a consolidar:\n\n"
            . self::CACHE_BREAKPOINT_KB . "\n"
            . $joined
        );

        return trim((string) $response);
    }

    /**
     * System prompt MAP stage — PT-BR com cache breakpoint sentinel.
     */
    protected function mapSystemPrompt(): string
    {
        return self::CACHE_BREAKPOINT_SYSTEM . "\n"
            . <<<'PROMPT'
            Você é Jana, summarizer técnico do projeto oimpresso (ERP brasileiro).

            TAREFA: comprimir 1 chunk de doc longo em mini-resumo ~200 tokens
            preservando:
              1. Decisões arquiteturais / ADRs referenciadas (números literais)
              2. Trechos load-bearing (regras, restrições Tier 0, fórmulas)
              3. Contexto operacional (paths, configs, comandos)

            FORMATO:
            - 3-6 bullets curtos OU 1 parágrafo denso (escolher pelo conteúdo)
            - Português brasileiro
            - Sem markdown headers (sem `#`)
            - Preservar siglas técnicas literais (PR #N, ADR 0NNN, biz=N)

            EVITAR:
            - Inventar dados não mencionados
            - Repetir frases do chunk literalmente — sintetizar
            - Frases corporativas vazias ("é importante notar que...")
            PROMPT;
    }

    /**
     * System prompt REDUCE stage — PT-BR com cache breakpoint sentinel.
     */
    protected function reduceSystemPrompt(int $targetTokens): string
    {
        return self::CACHE_BREAKPOINT_SYSTEM . "\n"
            . <<<PROMPT
            Você é Jana, summarizer técnico do projeto oimpresso (ERP brasileiro).

            TAREFA: consolidar N mini-resumos em 1 summary final coerente,
            target ~{$targetTokens} tokens, preservando:
              1. Estrutura narrativa (intro → corpo → conclusões/ações)
              2. PRs/ADRs literais já mencionados
              3. Pendências e blockers (se houver)
              4. Estado final (ativo/encerrado/pendente)

            FORMATO:
            - Markdown estruturado: 2-4 headers H3 (`###`) opcionais
            - Bullets onde fizer sentido
            - Português brasileiro, tom advisor sênior direto
            - Final OBRIGATÓRIO: linha "Status: <ativo|encerrado|continuation>"

            EVITAR:
            - Repetir mini-resumos verbatim
            - Inventar info não presente nos mini-resumos
            - Headers `#` ou `##` (use só `###` ou bullets)
            PROMPT;
    }

    // ============================================================
    // Cache MySQL
    // ============================================================

    /**
     * Busca summary cacheado dentro do TTL.
     */
    protected function fetchCached(string $hash, string $model): ?string
    {
        try {
            $ttlHours = (int) config('copiloto.auto_summarizer.cache_ttl_hours', 24);
            $cutoff = CarbonImmutable::now()->subHours($ttlHours);

            $row = DB::table('mcp_doc_summaries')
                ->where('content_hash', $hash)
                ->where('model', $model)
                ->where('created_at', '>=', $cutoff)
                ->first(['summary']);

            return $row?->summary;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Persiste summary no cache (upsert por hash+model).
     */
    protected function persistCache(
        string $hash,
        string $model,
        int $originalSize,
        string $summary,
        int $tokensIn,
        int $tokensOut,
        float $costBrl,
    ): void {
        try {
            $existing = DB::table('mcp_doc_summaries')
                ->where('content_hash', $hash)
                ->where('model', $model)
                ->first(['id']);

            if ($existing !== null) {
                DB::table('mcp_doc_summaries')
                    ->where('id', $existing->id)
                    ->update([
                        'summary' => $summary,
                        'tokens_in' => DB::raw("tokens_in + {$tokensIn}"),
                        'tokens_out' => DB::raw("tokens_out + {$tokensOut}"),
                        'cost_brl' => DB::raw("cost_brl + {$costBrl}"),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('mcp_doc_summaries')->insert([
                    'content_hash' => $hash,
                    'original_size' => $originalSize,
                    'summary' => $summary,
                    'tokens_in' => $tokensIn,
                    'tokens_out' => $tokensOut,
                    'cost_brl' => $costBrl,
                    'model' => $model,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('auto-summarizer: erro salvando cache', [
                'hash' => $hash,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Calcula custo BRL aproximado (gpt-4o-mini pricing snapshot 2026-04).
     * Pega pricing canônico de `copiloto.ai.pricing` se modelo existe lá.
     */
    protected function calcCostBrl(int $tokensIn, int $tokensOut, string $model): float
    {
        $pricing = config("copiloto.ai.pricing.{$model}");
        $cambio = (float) config('copiloto.ai.cambio_brl_usd', 5.50);

        if (! is_array($pricing) || ! isset($pricing['input'], $pricing['output'])) {
            // Fallback gpt-4o-mini
            $inputUsdPer1k = 0.00015;
            $outputUsdPer1k = 0.0006;
        } else {
            $inputUsdPer1k = (float) $pricing['input'];
            $outputUsdPer1k = (float) $pricing['output'];
        }

        $costUsd = ($tokensIn / 1000) * $inputUsdPer1k + ($tokensOut / 1000) * $outputUsdPer1k;

        return round($costUsd * $cambio, 6);
    }

    /**
     * Window split fallback quando não há headers markdown.
     *
     * @return list<string>
     */
    protected function windowSplit(string $text, int $chunkSize): array
    {
        $chunks = [];
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i += $chunkSize) {
            $chunks[] = mb_substr($text, $i, $chunkSize);
        }

        return $chunks;
    }

    /**
     * Trunca texto preservando início + nota explicativa final.
     */
    protected function truncate(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars - 80)
            . "\n\n_[... truncado: auto-summarizer indisponível, ver doc original]_";
    }
}
