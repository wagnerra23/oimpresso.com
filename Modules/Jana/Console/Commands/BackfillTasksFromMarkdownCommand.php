<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpCycleGoal;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * ADR 0070 — Backfill 1× das tasks/cycles que viviam em CURRENT.md + TASKS.md
 * pra estrutura jira-style mcp_*.
 *
 * Estratégia conservadora:
 *   - Cria Cycle 01 no project COPI (29-abr → 12-mai-2026) com 3 goals do CURRENT.md
 *   - Tasks ativas (🔥 NOVO FOCO) → ad-hoc com cycle_id=Cycle01 + status=doing
 *   - Tasks concluídas (✅) → ad-hoc com cycle_id=Cycle01 + status=done + completed_at
 *   - On-deck (O1..O19) → ad-hoc com status=backlog (cycle_id=null se Cycle 02)
 *   - TASKS.md backlog por módulo → ad-hoc no project correspondente, status=backlog
 *
 * Idempotente: usa firstOrCreate por title hash.
 *
 * Uso:
 *   php artisan mcp:tasks:backfill-from-markdown
 *   php artisan mcp:tasks:backfill-from-markdown --dry-run
 *   php artisan mcp:tasks:backfill-from-markdown --force  # re-cria mesmo se já tem
 */
class BackfillTasksFromMarkdownCommand extends Command
{
    protected $signature = 'mcp:tasks:backfill-from-markdown
                            {--dry-run    : Só relata o que faria}
                            {--force      : Recria tasks mesmo se já existirem}';

    protected $description = 'Backfill 1× CURRENT.md + TASKS.md → mcp_cycles/mcp_cycle_goals/mcp_tasks (ADR 0070)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dry) {
            $this->warn('DRY-RUN — nenhuma escrita persistida.');
        }

        // Garantir projects (chama seeder se faltar)
        if (McpProject::count() === 0) {
            $this->error("mcp_jira_projects vazia. Rode antes: php artisan db:seed --class=Modules\\\\Copiloto\\\\Database\\\\Seeders\\\\McpDefaultsSeeder");
            return self::FAILURE;
        }

        $copi = McpProject::where('key', 'COPI')->first();
        if (! $copi) {
            $this->error('Project COPI não encontrado.');
            return self::FAILURE;
        }

        // ---------- CYCLE 01 ----------
        $cycleData = [
            'project_id' => $copi->id,
            'key'        => 'CYCLE-01',
            'name'       => 'Cycle 01 — Copiloto memória + MCP',
            'start_date' => '2026-04-29',
            'end_date'   => '2026-05-12',
            'goal'       => "Copiloto assertivo e econômico em produção: Larissa pergunta faturamento e recebe resposta correta, com cache semântico reduzindo custos de token ≥50%.",
            'status'     => 'active',
        ];

        $cycle = McpCycle::firstWhere(['project_id' => $copi->id, 'key' => 'CYCLE-01']);
        if (! $cycle && ! $dry) {
            $cycle = McpCycle::create($cycleData);
            $this->info("  ✅ Cycle CYCLE-01 criado (id={$cycle->id})");
        } elseif ($cycle) {
            $this->line("  – Cycle CYCLE-01 já existe (id={$cycle->id})");
        } else {
            $this->line('  [dry] Cycle CYCLE-01 seria criado');
        }

        // ---------- GOALS ----------
        $goals = [
            [
                'description'   => 'Copiloto responde faturamento/metas Larissa corretamente — chat real prod 3 perguntas → 3 respostas distintas (caixa ≠ bruto ≠ líquido)',
                'metric_name'   => 'larissa_faturamento_correto',
                'target_value'  => 'true',
                'achieved_value'=> 'true',
                'status'        => 'done',
                'sort_order'    => 1,
            ],
            [
                'description'   => 'memoria_recall_chars > 0 nos logs de produção',
                'metric_name'   => 'memoria_recall_chars',
                'target_value'  => '>0',
                'achieved_value'=> '190',
                'status'        => 'done',
                'sort_order'    => 2,
            ],
            [
                'description'   => 'Dashboard /copiloto/admin/custos validado em test + merged (US-COPI-070)',
                'metric_name'   => 'us_copi_070_done',
                'target_value'  => 'merged',
                'achieved_value'=> null,
                'status'        => 'open',
                'sort_order'    => 3,
            ],
        ];

        if ($cycle && ! $dry) {
            foreach ($goals as $g) {
                McpCycleGoal::firstOrCreate(
                    ['cycle_id' => $cycle->id, 'sort_order' => $g['sort_order']],
                    array_merge($g, ['cycle_id' => $cycle->id])
                );
            }
            $this->info("  ✅ " . count($goals) . " goals do Cycle 01 sincronizados");
        }

        // ---------- TASKS DO CYCLE 01 ----------
        $cycleId = $cycle?->id;

        $cycleTasks = $this->cycle01Tasks($cycleId, $copi->id);
        // Injeta cycle_id e project_id em cada task pra createTasks gravar correto
        $cycleTasks = array_map(fn ($t) => array_merge(['cycle_id' => $cycleId, 'project_id' => $copi->id], $t), $cycleTasks);
        $insertedActive = $this->createTasks($cycleTasks, $dry, $force);
        $this->info("  ✅ Cycle 01 active/done tasks: {$insertedActive} criadas");

        // ---------- ON-DECK (Cycle 02 candidates) ----------
        $onDeckTasks = $this->cycle02OnDeckTasks($copi->id);
        $insertedOnDeck = $this->createTasks($onDeckTasks, $dry, $force);
        $this->info("  ✅ On-deck Cycle 02: {$insertedOnDeck} criadas");

        // ---------- TASKS.md BACKLOG POR MÓDULO ----------
        $tasksByModule = $this->tasksMdBacklog();
        $totalBacklog = 0;
        foreach ($tasksByModule as $projectKey => $tasks) {
            $project = McpProject::where('key', $projectKey)->first();
            if (! $project) {
                $this->warn("  ⚠ Project '{$projectKey}' não existe — pulando " . count($tasks) . ' tasks');
                continue;
            }
            $taskRows = array_map(function ($t) use ($project) {
                $t['project_id'] = $project->id;
                return $t;
            }, $tasks);
            $totalBacklog += $this->createTasks($taskRows, $dry, $force);
        }
        $this->info("  ✅ TASKS.md backlog: {$totalBacklog} criadas em " . count($tasksByModule) . ' projects');

        $this->newLine();
        $this->info('🎯 Backfill concluído. Próximo: php artisan mcp:tasks:sync (parser pega SPECs canônicas).');

        return self::SUCCESS;
    }

    /**
     * Tasks do Cycle 01 (ativas + concluídas) extraídas do CURRENT.md.
     */
    protected function cycle01Tasks(?int $cycleId, int $projectId): array
    {
        $today = now();

        return [
            // Tasks fechadas durante Cycle 01
            ['title' => 'MEM-MET-3: Scheduler diário copiloto:metrics:apurar', 'status' => 'done', 'completed_at' => '2026-04-29', 'commit' => '01e4e214'],
            ['title' => 'A4 rodada 2: Validar Larissa em prod (3 perguntas faturamento)', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-CC-team-1 Sprint A+B: .mcp.json + skill onboarding + watcher + 3 tabelas mcp_cc_*', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-TEAM-1 Self-host equiv Anthropic Team plan + QuotaEnforcer + alertas + ADR 0055', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-MEM-MCP-1 MCP-as-memory-source + ADR 0056 + Copiloto chat usa MCP', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-CC-team-2 Wagner roda watcher (V1 Node em scripts/cc-watcher/)', 'status' => 'done', 'completed_at' => '2026-04-30'],
            ['title' => 'COP-002 = MEM-MET-5 Golden set v1 (50 perguntas + AvaliarGabaritoCommand)', 'status' => 'done', 'completed_at' => '2026-04-30'],
            ['title' => 'MEM-MET-4 Page /copiloto/admin/qualidade V1 (KPIs + sparklines + gates)', 'status' => 'done', 'completed_at' => '2026-04-30'],
            ['title' => 'MemoriaAutonoma F1: copiloto:sintese-semanal + cron sex 18h + SinteseSemanalAgent', 'status' => 'done', 'completed_at' => '2026-04-30'],
            ['title' => 'TaskRegistry F0+F1 (parser + tools + webhook + CRUD + comments + events)', 'status' => 'done', 'completed_at' => '2026-05-01'],
            ['title' => 'MEM-HOT-1: Hybrid embedder MeilisearchDriver (recall 0→190)', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-HOT-2: ContextoNegocio injetado no ChatCopilotoAgent (164 tokens)', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-MET-1: Tabela copiloto_memoria_metricas em prod (14 colunas)', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-OTEL-1: Emissão gen_ai.* OpenTelemetry GenAI', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-MET-2: Comando copiloto:metrics:apurar + baseline em prod', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-FAT-1: ContextoNegocio expor 3 ângulos faturamento + ADR 0052', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-MCP-1.a Schema 9 migrations + 9 Entities + sync job + comando + endpoint webhook', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-MCP-1.b: Container CT 100 vivo em prod (mcp.oimpresso.com)', 'status' => 'done', 'completed_at' => '2026-04-29'],
            ['title' => 'MEM-KB-1: Page /copiloto/admin/memoria (KB browser do MCP server)', 'status' => 'done', 'completed_at' => '2026-04-30'],
            ['title' => 'MEM-KB-2 F1: sync expansion (488/488 docs)', 'status' => 'done', 'completed_at' => '2026-04-30'],

            // Tasks ativas (foco atual)
            ['title' => 'MEM-KB-3 F2: frontmatter YAML obrigatório + migração 57 ADRs antigos', 'status' => 'doing', 'priority' => 'p0', 'due_date' => '2026-05-09'],
            ['title' => 'MEM-MEM-MCP-1.b: ligar driver MCP no Copiloto (gerar token + .env Hostinger + smoke)', 'status' => 'doing', 'priority' => 'p0', 'due_date' => '2026-05-06'],

            // Em espera
            ['title' => 'MEM-MEM-WIRE Phase 2 (HyDE + Reranker + Negative cache)', 'status' => 'blocked', 'labels' => ['em-espera']],
            ['title' => 'F2 migração auto-mems P1 (englobada por MEM-KB-3)', 'status' => 'blocked', 'labels' => ['em-espera']],
        ];
    }

    /**
     * On-deck Cycle 02 (puxar quando A1/A2 fechar).
     */
    protected function cycle02OnDeckTasks(int $projectId): array
    {
        return [
            ['title' => 'MEM-EVAL-3 backfill facts + re-rodar gabarito (mede ΔR@3)', 'status' => 'backlog', 'priority' => 'p1'],
            ['title' => 'Fix ProfileDistiller (output vazio com biz=4) — -30% system prompt', 'status' => 'backlog', 'priority' => 'p1'],
            ['title' => 'MEM-S8-4 Auto-promote logic (facts hits≥5 → core_memory)', 'status' => 'backlog', 'priority' => 'p2'],
            ['title' => 'MEM-MET-4 = /copiloto/admin/qualidade trend 30d das 8 métricas + RAGAS + HITL', 'status' => 'backlog', 'priority' => 'p1'],
            ['title' => 'MEM-P2-2 RRF tuning A/B semantic_ratio 0.3 vs 0.7', 'status' => 'backlog', 'priority' => 'p2'],
            ['title' => 'MEM-GAP-1: Knowledge UI equivalente Anthropic Projects', 'status' => 'backlog', 'priority' => 'p2'],
            ['title' => 'MEM-GAP-2/3/4: Projects shared / file restrictions / centralized policy', 'status' => 'backlog', 'priority' => 'p3'],
            ['title' => 'MEM-KB-3 F2 (Cycle 02): frontmatter YAML + migração 57 ADRs + colunas tipadas', 'status' => 'backlog', 'priority' => 'p1'],
            ['title' => 'MEM-KB-4 F3: taxonomia 2 tabelas + tag cloud sidebar', 'status' => 'backlog', 'priority' => 'p2'],
            ['title' => 'MEM-KB-5 F4: grafo mcp_memory_relations + tool memory-graph', 'status' => 'backlog', 'priority' => 'p2'],
            ['title' => 'MEM-KB-6 F5: chunking semântico + Scout searchable + Meilisearch dedicado', 'status' => 'backlog', 'priority' => 'p1', 'labels' => ['biggest-win']],
            ['title' => 'MEM-KB-7 F6: signals dinâmicos + auto-promote hits>=5', 'status' => 'backlog', 'priority' => 'p2'],
            ['title' => 'MEM-KB-8 F7: integração log retrieval + dashboard "docs mais lidos"', 'status' => 'backlog', 'priority' => 'p2'],
            ['title' => 'INFRA-RT-1: Centrifugo + FrankenPHP no CT 100 (ADR 0058) — realtime canônico', 'status' => 'backlog', 'priority' => 'p1'],
            ['title' => 'MEM-CC-UI-1: Page /copiloto/admin/cc-sessions — KB sessões CC do time', 'status' => 'backlog', 'priority' => 'p1', 'labels' => ['maior-lacuna-estrategica']],
        ];
    }

    /**
     * Backlog por módulo extraído do TASKS.md (resumo executivo).
     */
    protected function tasksMdBacklog(): array
    {
        return [
            'COPI' => [
                ['title' => 'MEM-S8-1 SemanticCacheMiddleware (-68.8% tokens LLM)', 'status' => 'backlog', 'priority' => 'p0'],
                ['title' => 'MEM-S8-2 ConversationSummarizer (>15 turnos → resumo <200 tokens)', 'status' => 'backlog', 'priority' => 'p0'],
                ['title' => 'MEM-S8-3 ProfileDistiller (job diário perfil <300 tokens Redis)', 'status' => 'backlog', 'priority' => 'p0'],
                ['title' => 'PII redactor BR (regex CPF/CNPJ/email/tel) — LGPD-blocker', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'COP-005 Langfuse self-host CT 100 + OTEL no LaravelAiSdkDriver', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'COP-006 ApurarQualidadeJob + tabela copiloto_qualidade_scores', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'COP-009 ApurarMetasAtivasJob (scheduler diário)', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'COP-010 SuggestionEngine parsear JSON → Sugestao rows', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'COP-011 Tela LGPD /copiloto/memoria (listar + esquecer + opt-out)', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'COP-013 Drivers php e http (além de SqlDriver)', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'COP-014 Wizard 3 passos /copiloto/metas/create', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'COP-MCP-d-fine: granularidade fina por Tool (DecisionsSearchTool checa decisions.read etc)', 'status' => 'backlog', 'priority' => 'p1', 'due_date' => '2026-05-07'],
                ['title' => 'MEM-CHAT-ENT-2 polish UI Copiloto: avatar real + separadores de dia + indicador "pensando..."', 'status' => 'backlog', 'priority' => 'p1', 'due_date' => '2026-05-08'],
                ['title' => 'MEM-CHAT-ENT-3 edit + regenerate em mensagens', 'status' => 'backlog', 'priority' => 'p1', 'due_date' => '2026-05-09'],
                ['title' => 'MEM-CHAT-ENT-4 anexar imagem (vision) + GPT-4o', 'status' => 'backlog', 'priority' => 'p1', 'due_date' => '2026-05-12'],
                ['title' => 'MEM-CHAT-ENT-5 anexar PDF/Excel + extração contexto', 'status' => 'backlog', 'priority' => 'p1', 'due_date' => '2026-05-13'],
            ],
            'FIN' => [
                ['title' => 'FIN-001 Backfill purchases legadas em "due"', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'FIN-002 Rodar ContaBancariaIndexTest + RelatoriosTest em MySQL local', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'FIN-003 Audit "cache/estado preservado entre navegações" Financeiro', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'FIN-004 Atualizar cobrança ROTA LIVRE', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'FIN-005 Tela unificada US-FIN-013 (4 estados juntos)', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'FIN-006 Take rate de boleto (CNAB-only mode)', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'FIN-007 Conciliação Pix automática', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'FIN-008 DRE gerencial revisão UX como usuária real', 'status' => 'backlog', 'priority' => 'p2'],
            ],
            'PONTO' => [
                ['title' => 'PNT-001 Tier A — Dashboard vivo (3 personas, 8 capacidades)', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'PNT-002 Validar Eliana(WR2) — o que mudou em 6m sem PontoWr2', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'PNT-003 Comparativo pontowr2_vs_concorrentes_capterra_*.md', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'PNT-004 10 moves Tier A/B/C priorizados em SPEC', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'PNT-005 ADR formal requisitos/PontoWr2/adr/ui/0002', 'status' => 'backlog', 'priority' => 'p2'],
            ],
            'MEMCOFRE' => [
                ['title' => 'MEM-001 UI de upload de evidência', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'MEM-002 Página listagem Doc* entidades', 'status' => 'backlog', 'priority' => 'p2'],
            ],
            'CMS' => [
                ['title' => 'CMS-001 Hidratação Site/Home com cms_pages (re-tentar com fallback)', 'status' => 'blocked', 'priority' => 'p2'],
                ['title' => 'CMS-002 PR2+ redesign Inertia/React (blog + contact)', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'CMS-003 Decidir migrar landing inteira pro Inertia', 'status' => 'backlog', 'priority' => 'p3'],
            ],
            'OFFICE' => [
                ['title' => 'OFF-002 Auditoria untracked Modules/Connector no servidor', 'status' => 'backlog', 'priority' => 'p3'],
            ],
            'NFE' => [
                ['title' => 'NFE-001 NFe Brasil — implementar do SPEC', 'status' => 'blocked', 'priority' => 'p2'],
                ['title' => 'NFE-002 CT-e + MDF-e (ADR 0026 diferencial CV)', 'status' => 'backlog', 'priority' => 'p2'],
            ],
            'REC' => [
                ['title' => 'REC-001 Implementação do SPEC RecurringBilling', 'status' => 'blocked', 'priority' => 'p2'],
            ],
            'GROW' => [
                ['title' => 'GRO-001 Reunião de elicitação de escopo Grow', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'GRO-002 SPEC memory/requisitos/Grow/SPEC.md', 'status' => 'backlog', 'priority' => 'p2'],
            ],
            'INFRA' => [
                ['title' => 'INF-004 Mergear PRs deploy SSH pendentes', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'INF-005 Rebase PR #18 (DRAFT)', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'INF-006 Rebuild assets npm run build:inertia formalizar receita', 'status' => 'backlog', 'priority' => 'p1'],
                ['title' => 'INF-007 Sentry (observabilidade aplicação)', 'status' => 'backlog', 'priority' => 'p2'],
                ['title' => 'INF-008 Backup automático pré-deploy (formalizar)', 'status' => 'backlog', 'priority' => 'p2'],
            ],
            'EVO' => [
                ['title' => 'EVO-001 Fase 1 implementação (CC + Vizra ADK + Prism PHP)', 'status' => 'backlog', 'priority' => 'p2'],
            ],
        ];
    }

    /**
     * Cria tasks ad-hoc com idempotência via title+project_id+cycle_id.
     */
    protected function createTasks(array $tasks, bool $dry, bool $force): int
    {
        $count = 0;

        foreach ($tasks as $t) {
            $projectId = $t['project_id'] ?? McpProject::where('key', 'COPI')->value('id');
            $project = McpProject::find($projectId);
            if (! $project) continue;

            $cycleId = $t['cycle_id'] ?? null;
            $title = $t['title'];

            // Idempotência: mesmo title + project + cycle
            $exists = McpTask::where('project_id', $projectId)
                ->where('title', $title)
                ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
                ->exists();

            if ($exists && ! $force) {
                continue;
            }

            if ($dry) {
                $this->line("    [dry] {$project->key}: {$t['status']} — {$title}");
                $count++;
                continue;
            }

            $identifier = $project->allocateNextIdentifier();

            $row = [
                'task_id'        => $identifier,
                'identifier'     => $identifier,
                'project_id'     => $projectId,
                'cycle_id'       => $cycleId,
                'module'         => $project->key,
                'title'          => $title,
                'description'    => null,
                'status'         => $t['status'] ?? 'backlog',
                'type'           => $t['type'] ?? 'task',
                'priority'       => $t['priority'] ?? 'p2',
                'owner'          => $t['owner'] ?? 'wagner',
                'estimate_unit'  => 'points',
                'labels'         => $t['labels'] ?? null,
                'started_at'     => isset($t['status']) && in_array($t['status'], ['doing', 'review'], true) ? now()->subDays(2) : null,
                'completed_at'   => isset($t['completed_at']) ? Carbon::parse($t['completed_at']) : null,
                'due_date'       => isset($t['due_date']) ? Carbon::parse($t['due_date']) : null,
                'source_path'    => 'backfill-from-markdown',
                'parsed_at'      => now(),
            ];

            McpTask::create($row);
            McpTaskEvent::log(
                taskId: $identifier,
                eventType: 'created',
                author: 'backfill',
                note: 'Backfilled from CURRENT.md/TASKS.md (ADR 0070)',
            );

            if (isset($t['commit'])) {
                DB::table('mcp_git_links')->insert([
                    'task_id'         => $identifier,
                    'action'          => 'fixes',
                    'repo_full_name'  => 'wagnerra23/oimpresso.com',
                    'commit_sha'      => $t['commit'],
                    'occurred_at'     => Carbon::parse($t['completed_at'] ?? now()),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $count++;
        }

        return $count;
    }
}
