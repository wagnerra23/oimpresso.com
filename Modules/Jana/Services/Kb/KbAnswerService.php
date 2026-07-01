<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Kb;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\KbAnswerAgent;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * KbAnswerService — pipeline reutilizável de Q&A sobre a KB do oimpresso.
 *
 * Extraído de {@see \Modules\Jana\Mcp\Tools\KbAnswerTool} (2026-07-01) pra que o
 * mesmo pipeline retrieval → síntese seja reusado por:
 *   - a tool MCP `kb-answer` (fachada user-facing);
 *   - o RAGAS real-eval (`jana:ragas-real-eval`) — mede a saída REAL da Jana
 *     em vez de `answer = ground_truth` (tautologia banida por ADR 0271 +
 *     memory/proibicoes.md §"Teste que deriva do código").
 *
 * SoC brutal (Constituição v2 §5) + reuse-check (rule reuse-check.md): a lógica
 * de retrieval e síntese vive AQUI; quem precisa de resposta/contexto reais
 * (Tool ou eval) chama este service. Zero duplicação.
 *
 * Multi-tenant Tier 0 (ADR 0093): `acessiveisPara($user)` aplica permissões
 * Spatie; `doBusiness($businessId)` aplica scope (NULL = global/compartilhado).
 *
 * @see Modules/Jana/Mcp/Tools/KbAnswerTool.php
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class KbAnswerService
{
    /** Tipos canônicos persistidos em `mcp_memory_documents.type`. */
    public const TIPOS_VALIDOS = ['adr', 'spec', 'session', 'handoff', 'all'];

    /**
     * FASE 1 — Retrieval híbrido determinístico (Princípio 2 Constituição v2:
     * tiered cost — SQL primeiro, IA só na síntese).
     *
     * Reusa exatamente os mesmos scopes de DecisionsSearchTool / MemoriaSearchTool —
     * invariante "MCP server (Proxmox) só lê de mcp_memory_documents" (ADR 0053).
     *
     * @return Collection<int,McpMemoryDocument>
     */
    public function retrieve(
        ?User $user,
        string $pergunta,
        string $categoria = 'all',
        string $module = '',
        int $topK = 10,
    ): Collection {
        $businessId = (int) data_get($user, 'business_id', 0);

        // Gap #2 (US-RET-001) — recall HYBRID atrás de flag, fallback FULLTEXT.
        // Corpus MCP é GLOBAL (sem filtro business_id — verificado no índice CT 100).
        if (config('copiloto.mcp_search.docs_pipeline', false)) {
            try {
                $hybrid = McpMemoryDocument::buscarHybrid(
                    $pergunta,
                    $topK,
                    $user,
                    $categoria !== 'all' ? $categoria : null,
                    $module !== '' ? $module : null,
                    $businessId, // Tier 0 — simétrico ao FULLTEXT (revisão 2026-05-29)
                );
                if ($hybrid->isNotEmpty()) {
                    return $hybrid;
                }
            } catch (\Throwable $e) {
                Log::channel('copiloto-ai')->warning('kb-answer: hybrid falhou, fallback FULLTEXT: '.$e->getMessage());
            }
        }

        $query = McpMemoryDocument::query()
            ->acessiveisPara($user)
            ->porStatusAtivo(false)   // só docs ativos
            ->buscarTexto($pergunta);

        if ($businessId > 0) {
            $query->doBusiness($businessId);
        }

        if ($categoria !== 'all') {
            $query->doTipo($categoria);
        }

        if ($module !== '') {
            $query->doModulo($module);
        }

        return $query->limit($topK)->get([
            'id', 'slug', 'title', 'type', 'module', 'content_md', 'git_path',
        ]);
    }

    /**
     * Renderiza bloco "FONTES" pro prompt do LLM. Cada doc vira bloco markdown
     * numerado com slug + title + path + excerpt 400 chars.
     *
     * @param  Collection<int,McpMemoryDocument>  $docs
     */
    public function renderFontes(Collection $docs): string
    {
        $blocos = [];
        $i = 1;

        foreach ($docs as $doc) {
            $path = $doc->git_path ?: "memory/{$doc->type}s/{$doc->slug}.md";
            $excerpt = $this->extrairExcerpt($doc->content_md ?? '', 400);

            $blocos[] = sprintf(
                "### Fonte #%d — `%s`\n**%s** _(tipo: %s · módulo: %s)_\nPath: `%s`\n\n%s",
                $i++,
                $doc->slug,
                $doc->title ?? $doc->slug,
                $doc->type,
                $doc->module ?? 'core',
                $path,
                $excerpt,
            );
        }

        return implode("\n\n---\n\n", $blocos);
    }

    /**
     * FASE 2 — Síntese IA (laravel/ai SDK, gpt-4o-mini, ADR 0035). Single-shot,
     * sem tools. Devolve o markdown cru do agent ("Resposta:/Citações:/Confiança:").
     *
     * Lança exceção do provider IA pra cima — o CHAMADOR decide fallback. A Tool
     * cai em `fallbackSemIa`; o RAGAS eval conta como falha (NUNCA tautologia).
     */
    public function synthesize(string $pergunta, string $fontes, int $maxCitacoes = 5): string
    {
        $agent = new KbAnswerAgent(
            pergunta: $pergunta,
            fontes: $fontes,
            maxCitacoes: $maxCitacoes,
        );

        return trim((string) $agent->prompt($agent->montarPrompt()));
    }

    /**
     * Extrai excerpt pulando frontmatter YAML (mesmo critério do
     * `McpMemoryDocument::toSearchableArray`).
     */
    public function extrairExcerpt(string $body, int $maxLen): string
    {
        $semFrontmatter = preg_replace('/^\s*---\n.*?\n---\n?/s', '', $body);
        $clean = trim($semFrontmatter ?? '');

        if (mb_strlen($clean) <= $maxLen) {
            return $clean;
        }

        return mb_substr($clean, 0, $maxLen).'...';
    }

    /**
     * Fallback determinístico quando IA falha — markdown estruturado só com
     * snippets recuperados (sem síntese). Garante que a Tool nunca crasha por
     * provider IA indisponível. NÃO usado pelo RAGAS eval (lá, falha = sinal).
     *
     * @param  Collection<int,McpMemoryDocument>  $docs
     */
    public function fallbackSemIa(string $pergunta, Collection $docs, int $maxCitacoes): string
    {
        $topDocs = $docs->take($maxCitacoes);

        $out = "Resposta: Síntese IA indisponível no momento — devolvo os {$topDocs->count()} docs mais relevantes pra \"{$pergunta}\". Confira manualmente.\n\n";
        $out .= "Citações:\n";

        foreach ($topDocs as $doc) {
            $path = $doc->git_path ?: "memory/{$doc->type}s/{$doc->slug}.md";
            $quote = mb_substr(trim(preg_replace('/\s+/', ' ', $doc->content_md ?? '')), 0, 120);
            $out .= "- [{$doc->slug}]({$path}) — {$quote}\n";
        }

        $out .= "\nConfiança: baixa";

        return $out;
    }
}
