<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Services\Kb\KbAnswerService;
use Modules\Jana\Services\Summarizer\AutoSummarizerHelper;
use Throwable;

/**
 * KbAnswerTool (G3 — AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5).
 *
 * Tool MCP de Q&A natural sobre a KB do oimpresso. Substitui o fluxo manual
 * "rodar decisions-search → ler 3 ADRs full → sintetizar" por uma chamada
 * única que devolve resposta + citações + nível de confiança.
 *
 * Pipeline:
 *   FASE 1 — Hybrid retrieval determinístico:
 *     - Busca FULLTEXT em `mcp_memory_documents` (mesmo motor de
 *       DecisionsSearchTool/MemoriaSearchTool), respeitando filtros multi-tenant
 *       (business_id scope) + permissões Spatie + status lifecycle ativo
 *       (`porStatusAtivo`).
 *     - Filtra por categoria (adr/spec/session/handoff/all) + module opcional.
 *     - Retorna top 5-10 docs com slug + path + título + excerpt.
 *
 *   FASE 2 — Síntese IA (Camada A laravel/ai, ADR 0035):
 *     - Injeta bloco "FONTES" no prompt do `KbAnswerAgent` (gpt-4o-mini).
 *     - Single-shot — sem tools, sem loops. Custo previsível (~R$ [redacted Tier 0]/call).
 *     - Degradação silenciosa: se IA falhar, devolve markdown dos snippets.
 *
 *   FASE 3 — Resposta formatada:
 *     - Markdown estruturado: "Resposta:" + "Citações:" + "Confiança:".
 *     - Citações limitadas por `max_citacoes` (default 5, max 10).
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - `McpMemoryDocument::acessiveisPara($user)` aplica permissões Spatie.
 *   - `doBusiness($businessId)` aplica scope (NULL = global/compartilhado OK).
 *
 * @see memory/requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md §5
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class KbAnswerTool extends Tool
{
    protected string $name = 'kb-answer';

    protected string $title = 'Q&A natural sobre a KB do oimpresso';

    protected string $description = 'Q&A natural sobre KB do oimpresso (ADRs, SPECs, sessions, handoffs, requisitos). Sintetiza resposta com 3-5 citações inline. Usa memoria-search + decisions-search internamente (FULLTEXT em mcp_memory_documents) + síntese gpt-4o-mini. Idiomático: "qual ADR fala sobre X", "como decidimos Y", "estado de Z módulo". Custo ~R$ [redacted Tier 0]/call.';

    /** Tipos canônicos persistidos em `mcp_memory_documents.type`. */
    protected const TIPOS_VALIDOS = ['adr', 'spec', 'session', 'handoff', 'all'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'pergunta' => $schema->string()
                ->required()
                ->description('Pergunta em linguagem natural PT-BR (ex: "qual ADR fala sobre MCP server", "como decidimos multi-tenant", "estado do módulo Repair")'),
            'categoria' => $schema->string()
                ->enum(['adr', 'spec', 'session', 'handoff', 'all'])
                ->default('all')
                ->description('Filtra fontes por tipo de doc. Default "all" cobre tudo.'),
            'module' => $schema->string()
                ->description('Filtra por módulo (ex: "Whatsapp", "Sells", "Jana"). Omitido = todos os módulos.'),
            'max_citacoes' => $schema->integer()
                ->min(1)
                ->max(10)
                ->default(5)
                ->description('Quantidade máxima de citações no output (default 5, max 10).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $pergunta = trim((string) $request->get('pergunta', ''));
        $categoria = (string) $request->get('categoria', 'all');
        $module = (string) $request->get('module', '');
        $maxCitacoes = (int) $request->get('max_citacoes', 5);
        $maxCitacoes = max(1, min(10, $maxCitacoes));

        if ($pergunta === '') {
            return Response::error('Parâmetro "pergunta" obrigatório.');
        }

        if (! in_array($categoria, self::TIPOS_VALIDOS, true)) {
            return Response::error(sprintf(
                'Categoria inválida "%s". Use: %s',
                $categoria,
                implode(', ', self::TIPOS_VALIDOS),
            ));
        }

        // User pode ser null (CLI/test) — `acessiveisPara(null)` filtra só docs
        // públicas (mesmo pattern do DecisionsSearchTool). Em prod o middleware
        // McpAuth injeta user via Bearer token.
        $user = $request->user();

        // Pipeline retrieval → síntese vive em KbAnswerService (reusado pelo
        // RAGAS real-eval — mata a tautologia answer=ground_truth). SoC brutal §5.
        $svc = app(KbAnswerService::class);

        // FASE 1 — Retrieval híbrido determinístico (Princípio 2 Constituição
        // v2: tiered cost — SQL primeiro, IA só na síntese final).
        // Pegamos top 10 docs no DB pra dar margem ao LLM filtrar relevância,
        // mas limitamos citações finais em max_citacoes.
        $docs = $svc->retrieve(
            user: $user,
            pergunta: $pergunta,
            categoria: $categoria,
            module: $module,
            topK: max(5, $maxCitacoes * 2),
        );

        if ($docs->isEmpty()) {
            return Response::text(
                "Resposta: Não encontrei nada conclusivo na KB sobre \"{$pergunta}\". Tente reformular ou rodar `decisions-search` com termos diferentes.\n\n"
                . "Citações:\n- (nenhuma fonte recuperada)\n\n"
                . "Confiança: baixa"
            );
        }

        // FASE 2 — Síntese IA (laravel/ai SDK, gpt-4o-mini).
        $blocoFontes = $svc->renderFontes($docs);

        try {
            $texto = $svc->synthesize($pergunta, $blocoFontes, $maxCitacoes);

            Log::channel('copiloto-ai')->info('kb-answer', [
                'pergunta_chars' => strlen($pergunta),
                'categoria' => $categoria,
                'module' => $module ?: null,
                'docs_recuperados' => $docs->count(),
                'max_citacoes' => $maxCitacoes,
                'business_id' => (int) data_get($user, 'business_id', 0),
            ]);

            // Sanity check: se LLM não seguiu formato, faz fallback determinístico.
            if (! str_starts_with($texto, 'Resposta:')) {
                return Response::text(
                    AutoSummarizerHelper::summarizeAndRender(
                        $svc->fallbackSemIa($pergunta, $docs, $maxCitacoes)
                    )
                );
            }

            // A1 Onda 5 (dossier 2026-05-13 §6) — auto-summary se response > 8KB.
            // KB synthesis 10+ citações pode estourar quando fontes longas
            // vazam pra resposta (anti-padrão LLM). Helper passthrough abaixo
            // do threshold (caso comum: 2-3KB).
            return Response::text(AutoSummarizerHelper::summarizeAndRender($texto));
        } catch (Throwable $e) {
            Log::channel('copiloto-ai')->warning('kb-answer falhou (degradação)', [
                'error' => $e->getMessage(),
                'pergunta_chars' => strlen($pergunta),
            ]);

            return Response::text(
                AutoSummarizerHelper::summarizeAndRender(
                    $svc->fallbackSemIa($pergunta, $docs, $maxCitacoes)
                )
            );
        }
    }

}
