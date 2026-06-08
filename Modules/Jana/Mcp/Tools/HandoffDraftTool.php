<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Onda 5 H1 (ONDA-5-DOSSIER-2026-05-13 §4 P0) — Auto-skeleton handoff via LLM.
 *
 * Wagner gasta ~10-20min por handoff escrevendo narrativa estado (~250 handoffs/mês
 * = ~50h/mês). Esta tool ORQUESTRA `handoff-diff` (H3 Onda 3, já existe) +
 * último handoff como referência de formato + gpt-4o-mini pra rascunhar um
 * skeleton ADR 0130-compliant que Wagner edita antes de commit.
 *
 * Diferente de `handoff-fetch-summarized` (G4 — resume 1 handoff EXISTENTE):
 *  - este CRIA arquivo novo (draft) em `memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md`
 *  - reusa eventos coletados via HandoffDiffTool (composição, não duplicação)
 *  - Wagner edita ANTES de git add (princípio append-only respeitado: tool
 *    rascunha, Wagner finaliza)
 *
 * Custo: ~R$ 0.005 por handoff-draft (gpt-4o-mini, ~6k input + ~800 output).
 * NÃO cacheia (handoff é one-shot per sessão; cada draft é único contexto vivo).
 *
 * Multi-tenant: repo-wide, sem business_id (handoffs são governança projeto).
 *
 * Lições adotadas:
 *  - Mock mode obrigatório via Ai::fakeAgent(AnonymousAgent::class, [...])
 *    pra Pest local sem chave OpenAI (pattern proven HandoffFetchSummarizedTool).
 *  - Prompt PT-BR + force "ONLY use facts from eventos abaixo; do NOT infer".
 *  - Wagner revisa antes de Write final (não substitui ADR 0130 append-only).
 */
class HandoffDraftTool extends Tool
{
    protected string $name = 'handoff-draft';

    protected string $title = 'Rascunho skeleton de handoff';

    protected string $description = 'Gera draft de handoff em memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md cruzando handoff-diff (eventos desde data) + último handoff (formato referência) + gpt-4o-mini. Wagner edita antes de commit (ADR 0130 append-only preservado). Custo ~R$ 0.005/draft.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()
                ->description('Janela temporal pros eventos: `1d|3d|7d`, `last-handoff` (default — usa data do último handoff), ou data ISO `YYYY-MM-DD`.')
                ->default('last-handoff'),
            'slug' => $schema->string()
                ->description('Slug kebab-case pro filename. Default: auto-gerado a partir dos eventos (ex: `5prs-merged-cycle-05`).'),
            'force_llm' => $schema->boolean()
                ->description('Força chamada LLM mesmo se diff vazio. Default false — se nada mudou, retorna mensagem sem custo.')
                ->default(false),
        ];
    }

    public function handle(Request $request): Response
    {
        $since = (string) $request->get('since', 'last-handoff');
        $slugInput = trim((string) $request->get('slug', ''));
        $forceLlm = (bool) $request->get('force_llm', false);

        // Resolve `since` (alias `last-handoff` mapeia pro `last` do HandoffDiffTool)
        $sinceForDiff = $this->normalizarSince($since);

        // 1. Reusa HandoffDiffTool pra coletar eventos (PRs + US + ADRs + cycles + files)
        $diffTool = new HandoffDiffTool;
        $diffResponse = $diffTool->handle(new Request([
            'since' => $sinceForDiff,
            'format' => 'json',
        ]));
        $diffJson = (string) $diffResponse->content();
        $diffPayload = json_decode($diffJson, true);

        if (! is_array($diffPayload) || ! isset($diffPayload['eventos'])) {
            return Response::text(
                "Erro: HandoffDiffTool não retornou payload válido. Verifique `since={$since}`."
            );
        }

        $eventos = $diffPayload['eventos'];
        $counts = $diffPayload['counts'] ?? [
            'prs' => 0, 'us' => 0, 'adrs' => 0, 'cycles' => 0, 'files' => 0,
        ];
        $totalEventos = array_sum($counts);

        if ($totalEventos === 0 && ! $forceLlm) {
            return Response::text(
                "# handoff-draft\n\nNenhum evento desde `{$since}` (PRs=0, US=0, ADRs=0). " .
                "Passe `force_llm=true` pra gerar skeleton mesmo sem eventos."
            );
        }

        // 2. Lê último handoff como referência de formato (best-effort, não bloqueia)
        $handoffDir = config('jana.handoffs_dir') ?? base_path('memory/handoffs');
        $ultimoHandoff = $this->lerUltimoHandoff($handoffDir);

        // 3. Snapshot estado MCP no momento (cycle + my-work counts)
        $estadoMcp = $this->coletarEstadoMcp();

        // 4. Chama LLM (ou mock) pra montar narrativa
        $now = CarbonImmutable::now('America/Sao_Paulo');
        $slug = $slugInput !== ''
            ? $this->sanitizarSlug($slugInput)
            : $this->gerarSlugAuto($eventos, $now);
        $filename = sprintf(
            '%s-%s-%s.md',
            $now->format('Y-m-d'),
            $now->format('Hi'),
            $slug
        );

        [$conteudo, $custoBrl] = $this->montarSkeletonViaLlm(
            $now,
            $slug,
            $eventos,
            $counts,
            $estadoMcp,
            $ultimoHandoff
        );

        if ($conteudo === null) {
            return Response::text(
                "Erro: LLM falhou em gerar skeleton. Verifique logs Laravel + OPENAI_API_KEY."
            );
        }

        // 5. Salva direto em memory/handoffs/ (draft — Wagner edita depois)
        $path = rtrim($handoffDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $writeOk = $this->salvarDraft($path, $conteudo);

        // 6. Tracking de custo (mesmo padrão HandoffFetchSummarizedTool, sem cache)
        $this->trackCusto($filename, $custoBrl);

        $statusFile = $writeOk
            ? "✅ salvo em `memory/handoffs/{$filename}`"
            : "⚠️ NÃO foi possível salvar arquivo (permissões?) — copie o conteúdo abaixo manualmente";

        $output = "# handoff-draft\n\n"
            . "{$statusFile}\n\n"
            . "_Custo: R$ " . number_format($custoBrl, 4, ',', '.') . " · "
            . "{$counts['prs']} PRs · {$counts['us']} US · {$counts['adrs']} ADRs · "
            . "{$counts['cycles']} cycles · {$counts['files']} files tocados_\n\n"
            . "**ATENÇÃO:** este é um SKELETON. Wagner edita antes de git add + commit "
            . "(ADR 0130 append-only — sua revisão é o que finaliza).\n\n"
            . "---\n\n"
            . $conteudo;

        return Response::text($output);
    }

    /**
     * Normaliza `since` pro formato esperado por HandoffDiffTool.
     * `last-handoff` (alias H1) → `last` (token HandoffDiffTool).
     */
    protected function normalizarSince(string $since): string
    {
        $s = strtolower(trim($since));
        if ($s === 'last-handoff' || $s === 'last_handoff' || $s === '') {
            return 'last';
        }

        return $since;
    }

    /**
     * Lê o último handoff em memory/handoffs/ pra usar como referência de formato.
     * Retorna trecho de até 3000 chars (header + TL;DR + algumas seções) ou null.
     */
    protected function lerUltimoHandoff(string $dir): ?string
    {
        if (! is_dir($dir)) {
            return null;
        }
        $entries = @scandir($dir);
        if ($entries === false) {
            return null;
        }

        $candidates = [];
        foreach ($entries as $entry) {
            if (! str_ends_with($entry, '.md') || str_starts_with($entry, '_')) {
                continue;
            }
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})-(\d{4})-/', $entry, $m)) {
                continue;
            }
            try {
                $date = CarbonImmutable::parse(
                    $m[1] . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2)
                );
                $candidates[] = ['path' => $dir . DIRECTORY_SEPARATOR . $entry, 'date' => $date];
            } catch (\Throwable $e) {
                continue;
            }
        }
        if (empty($candidates)) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $b['date']->getTimestamp() <=> $a['date']->getTimestamp());

        $content = @file_get_contents($candidates[0]['path']);
        if ($content === false) {
            return null;
        }

        // Trunca pra ~3000 chars (~750 tokens) — só queremos formato/tom
        return mb_strlen($content) > 3000
            ? mb_substr($content, 0, 3000) . "\n[... truncado ...]"
            : $content;
    }

    /**
     * Snapshot leve do estado MCP (cycle ativo + count my-work).
     * Best-effort — se tabelas não existem (Pest com SQLite stub), retorna defaults.
     *
     * @return array{cycle_key: ?string, cycle_goal: ?string, cycle_dias_restantes: ?int, my_work_count: int}
     */
    protected function coletarEstadoMcp(): array
    {
        $defaults = [
            'cycle_key' => null,
            'cycle_goal' => null,
            'cycle_dias_restantes' => null,
            'my_work_count' => 0,
        ];

        try {
            $cycle = DB::table('mcp_cycles')
                ->where('status', 'active')
                ->orderByDesc('updated_at')
                ->first(['key', 'goal', 'end_date']);

            if ($cycle !== null) {
                $defaults['cycle_key'] = (string) ($cycle->key ?? '');
                $defaults['cycle_goal'] = $cycle->goal !== null ? (string) $cycle->goal : null;
                if (! empty($cycle->end_date)) {
                    try {
                        $end = CarbonImmutable::parse($cycle->end_date);
                        $defaults['cycle_dias_restantes'] = (int) max(0, CarbonImmutable::now()->diffInDays($end, false));
                    } catch (\Throwable $e) {
                        // ignora — end_date malformado
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('HandoffDraftTool: query mcp_cycles falhou', ['error' => $e->getMessage()]);
        }

        try {
            $defaults['my_work_count'] = (int) DB::table('mcp_tasks')
                ->whereIn('status', ['doing', 'review', 'blocked'])
                ->count();
        } catch (\Throwable $e) {
            Log::debug('HandoffDraftTool: query mcp_tasks falhou', ['error' => $e->getMessage()]);
        }

        return $defaults;
    }

    /**
     * Gera slug auto a partir dos eventos (ex: 5prs-merged-cycle-05).
     *
     * @param array<string, array<int, mixed>> $eventos
     */
    protected function gerarSlugAuto(array $eventos, CarbonImmutable $now): string
    {
        $partes = [];
        if (! empty($eventos['prs'])) {
            $partes[] = count($eventos['prs']) . 'prs';
        }
        if (! empty($eventos['us'])) {
            $partes[] = count($eventos['us']) . 'us';
        }
        if (! empty($eventos['adrs'])) {
            $partes[] = count($eventos['adrs']) . 'adrs';
        }
        if (empty($partes)) {
            $partes[] = 'sessao';
        }
        $partes[] = $now->format('His');

        return implode('-', $partes);
    }

    /**
     * Sanitiza slug fornecido pelo usuário pra kebab-case válido.
     */
    protected function sanitizarSlug(string $slug): string
    {
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? 'sessao';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'sessao';
    }

    /**
     * Chama LLM (gpt-4o-mini) pra montar skeleton estruturado.
     * Retorna [conteúdo, custo_brl]. Custo 0 em mock mode (Ai::fakeAgent).
     *
     * @param array<string, array<int, mixed>> $eventos
     * @param array<string, int> $counts
     * @param array{cycle_key: ?string, cycle_goal: ?string, cycle_dias_restantes: ?int, my_work_count: int} $estadoMcp
     * @return array{0: ?string, 1: float}
     */
    protected function montarSkeletonViaLlm(
        CarbonImmutable $now,
        string $slug,
        array $eventos,
        array $counts,
        array $estadoMcp,
        ?string $ultimoHandoff
    ): array {
        try {
            $promptUser = $this->montarPromptUsuario(
                $now,
                $slug,
                $eventos,
                $counts,
                $estadoMcp,
                $ultimoHandoff
            );

            $agent = new AnonymousAgent(
                instructions: $this->systemPrompt(),
                messages: [],
                tools: [],
            );

            $response = $agent->prompt($promptUser);
            $texto = trim((string) $response);

            if ($texto === '') {
                return [null, 0.0];
            }

            // Estimativa custo gpt-4o-mini: ~6k input + ~800 output (Onda 5 §4)
            // $0.15/M input + $0.60/M output → ~R$ 0.005 com câmbio 5x
            $tokensIn = (int) (mb_strlen($promptUser) / 4) + 400; // +400 do system prompt
            $tokensOut = (int) (mb_strlen($texto) / 4);
            $costUsd = ($tokensIn / 1_000_000) * 0.15 + ($tokensOut / 1_000_000) * 0.60;
            $costBrl = round($costUsd * 5.0, 6);

            return [$texto, $costBrl];
        } catch (\Throwable $e) {
            Log::warning('HandoffDraftTool: erro LLM', [
                'error' => $e->getMessage(),
                'slug' => $slug,
            ]);

            return [null, 0.0];
        }
    }

    /**
     * System prompt PT-BR pro LLM. Force "ONLY use facts from input".
     */
    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
        Você é um assistente que rascunha SKELETONS de handoffs técnicos pro
        projeto oimpresso (ERP brasileiro, ADR 0130 append-only).

        REGRA CRÍTICA: use APENAS fatos dos eventos+estado MCP fornecidos abaixo.
        NÃO INVENTE PR numbers, US IDs, ADRs nem decisões. Se faltar dado, escreva
        explicitamente `<a preencher>` pra Wagner completar.

        FORMATO obrigatório (siga literalmente — Wagner copia direto):

        ```
        # YYYY-MM-DD HH:MM BRT — <slug-da-sessão>

        > Tipo: handoff (skeleton automático — Wagner edita antes de commit)

        ## TL;DR
        <3-5 linhas resumindo o que aconteceu na sessão; foque no WHY, não no HOW>

        ## N PRs mergeados
        - #<numero> <titulo> (@<autor>)
        ...

        ## N US movidas
        - <task_id> <título> [<owner>]
        ...

        ## ADRs novas
        - <titulo da ADR>
        ...

        ## Estado MCP no momento
        ```
        cycles-active: <key cycle> · <dias> dias restantes · goal: <goal>
        my-work: <N> tasks ativas (doing/review/blocked)
        ```

        ## Próximo passo (sugestão LLM)
        <1-3 frases concretas baseadas nos eventos — ex: "validar PR #123 em prod
        biz=1", "fechar US-COPI-099 que ficou em review", "aprovar ADR 0145">
        ```

        REGRAS DURAS:
        - PT-BR em tudo (nunca inglês)
        - Bullets curtos (≤25 palavras cada)
        - Sem emojis, sem markdown extra além do template
        - Datas em formato ISO YYYY-MM-DD
        - Se eventos.prs vazio, escreva "## 0 PRs mergeados\n_nenhum_"
        - NUNCA invente PR #X ou US-ID se não estiver na lista de eventos
        PROMPT;
    }

    /**
     * Monta prompt usuário com eventos + estado MCP + referência último handoff.
     *
     * @param array<string, array<int, mixed>> $eventos
     * @param array<string, int> $counts
     * @param array{cycle_key: ?string, cycle_goal: ?string, cycle_dias_restantes: ?int, my_work_count: int} $estadoMcp
     */
    protected function montarPromptUsuario(
        CarbonImmutable $now,
        string $slug,
        array $eventos,
        array $counts,
        array $estadoMcp,
        ?string $ultimoHandoff
    ): string {
        $eventosJson = json_encode($eventos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $estadoJson = json_encode($estadoMcp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        $referencia = $ultimoHandoff !== null
            ? "REFERÊNCIA DO ÚLTIMO HANDOFF (formato/tom — use como guia, mas NÃO copie conteúdo):\n\n```markdown\n{$ultimoHandoff}\n```\n\n"
            : "(Sem handoff anterior — primeira sessão. Use FORMATO obrigatório do system prompt literalmente.)\n\n";

        $dataHeader = $now->format('Y-m-d H:i') . ' BRT';

        return <<<PROMPT
        Rascunhe SKELETON de handoff pra sessão atual.

        DATA HEADER: {$dataHeader}
        SLUG: {$slug}

        EVENTOS COLETADOS (HandoffDiffTool — fonte autoritativa, NÃO infira nada além disso):
        Counts: PRs={$counts['prs']} · US={$counts['us']} · ADRs={$counts['adrs']} · cycles={$counts['cycles']} · files={$counts['files']}

        ```json
        {$eventosJson}
        ```

        ESTADO MCP NO MOMENTO (snapshot leve):
        ```json
        {$estadoJson}
        ```

        {$referencia}Agora gere o skeleton seguindo o FORMATO obrigatório do system prompt.
        Lembre: PT-BR, sem inventar, `<a preencher>` em campos sem dado.
        PROMPT;
    }

    /**
     * Salva o draft em memory/handoffs/. Cria diretório se não existir.
     */
    protected function salvarDraft(string $path, string $conteudo): bool
    {
        try {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                File::makeDirectory($dir, 0o755, recursive: true);
            }
            $result = @file_put_contents($path, $conteudo);

            return $result !== false;
        } catch (\Throwable $e) {
            Log::warning('HandoffDraftTool: erro salvando draft', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Tracking de custo (sem cache — tabela mcp_handoff_drafts opcional).
     * Best-effort: se tabela não existe, só loga.
     */
    protected function trackCusto(string $filename, float $costBrl): void
    {
        try {
            DB::table('mcp_handoff_drafts')->insert([
                'filename' => $filename,
                'cost_brl' => $costBrl,
                'model' => 'gpt-4o-mini',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Tabela ainda não migrada — não bloqueia (tracking secundário)
            Log::debug('HandoffDraftTool: tabela mcp_handoff_drafts ausente', [
                'filename' => $filename,
                'cost_brl' => $costBrl,
            ]);
        }
    }
}
