<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Auditoria 2026-05-13 §5 (P0) — Resume handoffs via LLM.
 *
 * Wagner gasta 30-90min/dia relendo handoffs (mediana 142 linhas, outlier 2151).
 * Esta tool retorna últimos N handoffs em ~1.5k tokens total (compact) ou
 * ~4k tokens (detailed) substituindo leitura raw de ~5k-25k tokens.
 *
 * Lista handoffs em `memory/handoffs/*.md` filtrados por janela temporal,
 * resume cada um via gpt-4o-mini (laravel/ai AnonymousAgent), e cacheia em
 * `mcp_handoff_summaries` keyed por (filename, content_hash MD5).
 *
 * Custo: ~R$ [redacted Tier 0] por handoff resumido. 3 handoffs × R$ [redacted Tier 0] = R$ [redacted Tier 0]/chamada.
 * Cache hit = R$ [redacted Tier 0] (re-uso resumo anterior).
 *
 * Multi-tenant: handoffs são repo-wide (governança projeto, não business);
 * cache sem business_id — ver migration `mcp_handoff_summaries`.
 */
class HandoffFetchSummarizedTool extends Tool
{
    protected string $name = 'handoff-fetch-summarized';

    protected string $title = 'Handoffs recentes resumidos';

    protected string $description = 'Retorna handoffs recentes resumidos via LLM. Ideal pra retomar sessão sem reler 2000 linhas. Filtra por janela temporal (since), formato compact (~150 tok cada) ou detailed (~400 tok cada), e módulo mencionado.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()
                ->description('Janela temporal: `1d|3d|7d|14d|30d` (default `3d`) OU data ISO `YYYY-MM-DD`')
                ->default('3d'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(10)
                ->default(3)
                ->description('Quantos handoffs resumir (default 3, max 10)'),
            'module' => $schema->string()
                ->description('Filtra handoffs cujo conteúdo menciona o módulo (case-insensitive). Ex: `Jana`, `Whatsapp`, `Repair`.'),
            'format' => $schema->string()
                ->description('Formato resumo: `compact` (~150 tokens cada, default) ou `detailed` (~400 tokens cada).')
                ->default('compact'),
        ];
    }

    public function handle(Request $request): Response
    {
        $since = (string) $request->get('since', '3d');
        $limit = max(1, min(10, (int) $request->get('limit', 3)));
        $moduleFilter = trim((string) $request->get('module', ''));
        $format = $request->get('format', 'compact') === 'detailed' ? 'detailed' : 'compact';

        $sinceDate = $this->parseSince($since);
        if ($sinceDate === null) {
            return Response::text(
                "Erro: parâmetro `since` inválido. Use `1d|3d|7d|14d|30d` ou data ISO `YYYY-MM-DD`."
            );
        }

        // Diretório testável via config override (default: memory/handoffs/ real).
        // Pest tests usam temp dir pra isolar de handoffs prod.
        $handoffDir = config('jana.handoffs_dir') ?? base_path('memory/handoffs');
        if (! is_dir($handoffDir)) {
            return Response::text(
                "Diretório `memory/handoffs/` não existe. Sem handoffs pra resumir."
            );
        }

        // Lista arquivos elegíveis (filename YYYY-MM-DD-HHMM-*.md ≥ sinceDate)
        $files = $this->listHandoffsFiltrados($handoffDir, $sinceDate, $moduleFilter, $limit);

        if (empty($files)) {
            return Response::text(
                "# Handoffs resumidos\n\nNenhum handoff encontrado na janela `since={$since}`" .
                ($moduleFilter !== '' ? " filtrando módulo `{$moduleFilter}`" : '') . '.'
            );
        }

        // Resume cada handoff (cache miss → LLM, cache hit → DB)
        $resumos = [];
        $rawTokensTotal = 0;
        $summaryTokensTotal = 0;
        $cacheHits = 0;
        $cacheMisses = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file['path']);
            if ($content === false || $content === '') {
                continue;
            }

            $hash = md5($content);
            $rawTokens = (int) (mb_strlen($content) / 4);
            $rawTokensTotal += $rawTokens;

            $cached = $this->buscarCache($file['filename'], $hash, $format);
            if ($cached !== null) {
                $cacheHits++;
                $resumos[] = $this->renderResumo($file, $cached);
                $summaryTokensTotal += (int) (mb_strlen($cached) / 4);
                continue;
            }

            $cacheMisses++;
            $resumo = $this->resumirViaLlm($content, $format);
            if ($resumo === null) {
                continue;
            }

            $this->salvarCache($file['filename'], $hash, $format, $resumo);
            $resumos[] = $this->renderResumo($file, $resumo);
            $summaryTokensTotal += (int) (mb_strlen($resumo) / 4);
        }

        if (empty($resumos)) {
            return Response::text(
                "# Handoffs resumidos\n\nNenhum handoff pôde ser resumido (LLM offline ou arquivos inacessíveis)."
            );
        }

        $stats = sprintf(
            "_%d handoff(s) · %d tokens (raw ~%d) · %d cache hit / %d miss · formato `%s`_",
            count($resumos),
            $summaryTokensTotal,
            $rawTokensTotal,
            $cacheHits,
            $cacheMisses,
            $format
        );

        $output = "# Handoffs resumidos (últimos " . count($resumos) . ")\n\n" .
            $stats . "\n\n---\n\n" .
            implode("\n\n---\n\n", $resumos);

        return Response::text($output);
    }

    /**
     * Converte `since` em CarbonImmutable. Aceita `Nd` (N dias) ou ISO date.
     */
    protected function parseSince(string $since): ?CarbonImmutable
    {
        $since = trim($since);
        if ($since === '') {
            return CarbonImmutable::now()->subDays(3);
        }

        // Pattern Nd (1d, 3d, 7d, 14d, 30d, etc)
        if (preg_match('/^(\d+)d$/i', $since, $m)) {
            return CarbonImmutable::now()->subDays((int) $m[1]);
        }

        // Tenta parse ISO
        try {
            return CarbonImmutable::parse($since)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lista handoffs em memory/handoffs/ filtrados por data + módulo,
     * ordenados desc, limitado a $limit.
     *
     * @return array<int, array{filename: string, path: string, date: CarbonImmutable, slug: string}>
     */
    protected function listHandoffsFiltrados(string $dir, CarbonImmutable $sinceDate, string $moduleFilter, int $limit): array
    {
        $candidates = [];
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if (! str_ends_with($entry, '.md') || str_starts_with($entry, '_')) {
                continue;
            }
            // Extract YYYY-MM-DD-HHMM do filename
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})-(\d{4})-(.+)\.md$/', $entry, $m)) {
                continue;
            }
            try {
                $date = CarbonImmutable::parse($m[1] . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2));
            } catch (\Throwable $e) {
                continue;
            }

            if ($date->lt($sinceDate)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            // Filtro por módulo (case-insensitive grep no conteúdo)
            if ($moduleFilter !== '') {
                $content = @file_get_contents($path);
                if ($content === false || stripos($content, $moduleFilter) === false) {
                    continue;
                }
            }

            $candidates[] = [
                'filename' => $entry,
                'path' => $path,
                'date' => $date,
                'slug' => $m[3],
            ];
        }

        // Ordena DESC por data (mais recente primeiro)
        usort($candidates, fn ($a, $b) => $b['date']->getTimestamp() <=> $a['date']->getTimestamp());

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Busca resumo cacheado pra (filename, hash, format). Retorna texto ou null.
     */
    protected function buscarCache(string $filename, string $hash, string $format): ?string
    {
        try {
            $col = $format === 'detailed' ? 'summary_detailed' : 'summary_compact';
            $row = DB::table('mcp_handoff_summaries')
                ->where('filename', $filename)
                ->where('content_hash', $hash)
                ->first([$col]);

            if ($row === null) {
                return null;
            }

            return $row->{$col} ?? null;
        } catch (\Throwable $e) {
            // Tabela ainda não migrada — não bloqueia
            return null;
        }
    }

    /**
     * Salva resumo no cache (upsert por filename + hash).
     */
    protected function salvarCache(string $filename, string $hash, string $format, string $resumo): void
    {
        try {
            $col = $format === 'detailed' ? 'summary_detailed' : 'summary_compact';

            // Estimativa de custo gpt-4o-mini: $0.15/M input + $0.60/M output
            // Conversão USD→BRL ~5.0 (caputrado em config copiloto.usd_brl mas hardcoded fallback)
            $tokensIn = (int) (mb_strlen($resumo) / 4) * 25; // proxy: resumo é ~4% do input
            $tokensOut = (int) (mb_strlen($resumo) / 4);
            $costUsd = ($tokensIn / 1_000_000) * 0.15 + ($tokensOut / 1_000_000) * 0.60;
            $costBrl = round($costUsd * 5.0, 6);

            $existing = DB::table('mcp_handoff_summaries')
                ->where('filename', $filename)
                ->where('content_hash', $hash)
                ->first(['id']);

            if ($existing !== null) {
                DB::table('mcp_handoff_summaries')
                    ->where('id', $existing->id)
                    ->update([
                        $col => $resumo,
                        'tokens_in' => DB::raw("tokens_in + {$tokensIn}"),
                        'tokens_out' => DB::raw("tokens_out + {$tokensOut}"),
                        'cost_brl' => DB::raw("cost_brl + {$costBrl}"),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('mcp_handoff_summaries')->insert([
                    'filename' => $filename,
                    'content_hash' => $hash,
                    'summary_compact' => $format === 'compact' ? $resumo : null,
                    'summary_detailed' => $format === 'detailed' ? $resumo : null,
                    'tokens_in' => $tokensIn,
                    'tokens_out' => $tokensOut,
                    'cost_brl' => $costBrl,
                    'model' => 'gpt-4o-mini',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('HandoffFetchSummarizedTool: erro salvando cache', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Chama LLM (gpt-4o-mini via laravel/ai AnonymousAgent) pra resumir 1 handoff.
     * Retorna texto resumido ou null em falha.
     */
    protected function resumirViaLlm(string $content, string $format): ?string
    {
        try {
            // Trunca handoffs gigantes (>20k chars ~5k tokens input) pra não estourar contexto
            $contentTruncado = mb_strlen($content) > 20_000
                ? mb_substr($content, 0, 20_000) . "\n\n[... truncado por tamanho ...]"
                : $content;

            $agent = new AnonymousAgent(
                instructions: $this->systemPrompt($format),
                messages: [],
                tools: [],
            );

            $response = $agent->prompt(
                "Handoff pra resumir:\n\n{$contentTruncado}"
            );

            $texto = trim((string) $response);

            return $texto !== '' ? $texto : null;
        } catch (\Throwable $e) {
            Log::warning('HandoffFetchSummarizedTool: erro LLM', [
                'error' => $e->getMessage(),
                'format' => $format,
            ]);

            return null;
        }
    }

    /**
     * Prompt sistema pro LLM resumir handoff.
     */
    protected function systemPrompt(string $format): string
    {
        if ($format === 'detailed') {
            return <<<'PROMPT'
            Você é um summarizer de handoffs do projeto oimpresso (ERP brasileiro).
            OBJETIVO: comprimir handoff técnico em ~400 tokens preservando:
              1. PRs/ADRs mergeadas (números literais)
              2. Decisões arquiteturais tomadas
              3. Pendências/blockers pro próximo agente
              4. Estado MCP (cycle, tasks ativas)
              5. Tom (sucesso/parcial/blocked)

            FORMATO obrigatório:
            - 5-8 bullets curtos (NÃO frases longas)
            - Final: "Status: <ativo|encerrado|continuation>"
            - Final: "Próximo passo: <1 frase clara>"
            - Português brasileiro
            - Sem markdown headers (sem `#`)

            EVITAR:
            - Repetir filenames longos sem necessidade
            - Inventar PR numbers ou ADRs não mencionados
            - Detalhes técnicos demais (foque no WHAT, não HOW)
            PROMPT;
        }

        return <<<'PROMPT'
        Você é um summarizer de handoffs do projeto oimpresso (ERP brasileiro).
        OBJETIVO: comprimir handoff técnico em ~150 tokens (3-5 bullets) preservando:
          1. Principal decisão/entrega (1 bullet)
          2. PRs/ADRs chave (números literais)
          3. Status atual (1 frase)
          4. Próximo passo (1 frase)

        FORMATO obrigatório:
        - 3-5 bullets curtos (cada bullet ≤20 palavras)
        - Final: "Status: <ativo|encerrado|continuation>"
        - Final: "Próximo passo: <1 frase clara>"
        - Português brasileiro
        - Sem markdown headers

        EVITAR:
        - Repetir filenames
        - Inventar dados não mencionados
        - Frases longas — bullets atômicos
        PROMPT;
    }

    /**
     * Formata 1 resumo de handoff pra output final.
     *
     * @param array{filename: string, path: string, date: CarbonImmutable, slug: string} $file
     */
    protected function renderResumo(array $file, string $resumo): string
    {
        $header = sprintf(
            "### %s — %s",
            $file['date']->format('Y-m-d H:i'),
            $file['slug']
        );

        return $header . "\n\n" . $resumo;
    }
}
