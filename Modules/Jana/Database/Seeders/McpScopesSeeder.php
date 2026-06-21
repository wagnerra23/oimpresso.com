<?php

namespace Modules\Jana\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * MEM-MCP-1.d (ADR 0053) — Seeder de scopes MCP + Spatie permissions.
 *
 * Cria entradas em:
 *   - mcp_scopes — catálogo legível com descrições e patterns de tools/resources
 *   - permissions (Spatie) — gates `jana.mcp.*` que o middleware/tools checam
 *
 * Idempotente: pode rodar quantas vezes precisar — usa firstOrCreate.
 *
 * Roda: `php artisan db:seed --class=Modules\\Jana\\Database\\Seeders\\McpScopesSeeder`
 *
 * Atribuição aos roles é manual (skill multi-tenant: cada cliente tem
 * seus próprios roles `Admin#{biz}` etc). Um command separado
 * `mcp:assign-default-permissions` pode aplicar defaults futuramente.
 */
class McpScopesSeeder extends Seeder
{
    /**
     * Catálogo canônico de scopes.
     *
     * Cada scope vira UMA Spatie permission + UMA linha em mcp_scopes.
     */
    protected array $catalogo = [
        [
            'slug'              => 'jana.mcp.use',
            'nome'              => 'Acessar MCP server',
            'descricao'         => 'Gate básico: precisa pra fazer qualquer chamada MCP autenticada. Sem isso o middleware retorna 403.',
            'resources_pattern' => null,
            'tools_pattern'     => null,
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.tasks.read',
            'nome'              => 'Ler tarefas e cycle ativo',
            'descricao'         => 'Permite tools de leitura: tasks-list, tasks-detail, cycles-active, my-work, my-inbox, triage, cycle-goals-track (read), handoff. Padrão pra todos os devs (ADR 0070).',
            'resources_pattern' => 'oimpresso://memory/handoff',
            'tools_pattern'     => '(tasks-(list|detail|current)|cycles-active|cycle-goals-track|my-work|my-inbox|triage)',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.tasks.write',
            'nome'              => 'Criar/atualizar/comentar tarefas',
            'descricao'         => 'Permite tasks-create, tasks-update, tasks-comment, tasks-bulk-update. Default pra dev sênior; Luiz/Eliana com supervisão (ver TEAM.md). ADR 0070.',
            'resources_pattern' => null,
            'tools_pattern'     => '(tasks-(create|update|comment|move|link|assign|bulk-update))',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.tasks.advance',
            'nome'              => 'Avançar tarefas (mutação não-terminal)',
            'descricao'         => 'Sub-scope fino de tasks.write (A3, ADR 0070/0278): criar/atualizar/comentar/mover tasks SEM fechar (status != done/cancelled). Fechar exige jana.mcp.tasks.close. O umbrella legado jana.mcp.tasks.write autoriza ambos (backward-safe).',
            'resources_pattern' => null,
            'tools_pattern'     => '(tasks-(create|update|comment|move|link|assign|bulk-update))',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.tasks.close',
            'nome'              => 'Fechar tarefas (done/cancelled — terminal)',
            'descricao'         => 'Sub-scope fino de tasks.write (A3, ADR 0070/0278): mover task pra done/cancelled (transição terminal de estado). Separado de advance pra granularidade. O umbrella legado jana.mcp.tasks.write também autoriza.',
            'resources_pattern' => null,
            'tools_pattern'     => '(tasks-update)',
            'is_destructive'    => true,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.cycles.manage',
            'nome'              => 'Gerenciar cycles + goals',
            'descricao'         => 'Criar cycle, fechar cycle (com rollover), atualizar achieved_value de goals. Wagner + leads de projeto. ADR 0070.',
            'resources_pattern' => null,
            'tools_pattern'     => '(cycles-(create|close)|cycle-goals-(add|track))',
            'is_destructive'    => true,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.projects.manage',
            'nome'              => 'Gerenciar projetos + epics + componentes',
            'descricao'         => 'Criar/atualizar projects, epics, components, workflows, issue templates, saved views. Wagner + admin. ADR 0070.',
            'resources_pattern' => null,
            'tools_pattern'     => '(projects-.*|epics-.*|components-.*|workflows-.*|views-.*|issue-templates-.*)',
            'is_destructive'    => true,
            'business_required' => false,
            'admin_only'        => true,
        ],
        [
            'slug'              => 'jana.mcp.decisions.read',
            'nome'              => 'Ler ADRs (decisões arquiteturais)',
            'descricao'         => 'Permite buscar e ler ADRs em memory/decisions/. Padrão pra todos os devs (decisões são públicas no time).',
            'resources_pattern' => 'oimpresso://memory/decisions/.*',
            'tools_pattern'     => 'decisions-.*',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.sessions.read',
            'nome'              => 'Ler session logs',
            'descricao'         => 'Permite listar/ler logs de sessões anteriores. Padrão pra todos os devs.',
            'resources_pattern' => 'oimpresso://memory/sessions/.*',
            'tools_pattern'     => 'sessions-.*',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.usage.self',
            'nome'              => 'Ver próprio uso/custo do Claude Code',
            'descricao'         => 'Permite consultar resumo de uso pessoal (tokens, R$, top tools). Sempre concedido a quem tem `jana.mcp.use`.',
            'resources_pattern' => null,
            'tools_pattern'     => 'claude-code-usage-self',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.usage.all',
            'nome'              => 'Ver uso/custo de TODOS os devs',
            'descricao'         => 'Dashboards consolidados de governança: spend total, top users, top tools cross-team. Apenas Wagner/admin.',
            'resources_pattern' => null,
            'tools_pattern'     => 'claude-code-usage-all',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => true,
        ],
        [
            'slug'              => 'jana.mcp.governanca.financeiro',
            'nome'              => 'Acessar contexto financeiro via MCP',
            'descricao'         => 'Acesso a tools/resources financeiros (faturamento, ticket médio, contas a pagar/receber). Wagner + Eliana[E].',
            'resources_pattern' => 'oimpresso://financeiro/.*',
            'tools_pattern'     => 'financeiro-.*',
            'is_destructive'    => false,
            'business_required' => true,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.governanca.tecnico',
            'nome'              => 'Acessar contexto técnico via MCP',
            'descricao'         => 'Acesso a tools/resources de qualidade IA (faithfulness, latência, recall, alertas). Felipe[F] + Wagner.',
            'resources_pattern' => 'oimpresso://qualidade/.*',
            'tools_pattern'     => '(qualidade|faithfulness|latency)-.*',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.memory.manage',
            'nome'              => 'Gerenciar KB MCP (mcp_memory_documents)',
            'descricao'         => 'Acesso à tela /jana/admin/memoria — listar, ler, soft-delete LGPD e ver history dos docs sincronizados. Wagner/superadmin v1.',
            'resources_pattern' => 'oimpresso://memory/.*',
            'tools_pattern'     => null,
            'is_destructive'    => true,
            'business_required' => false,
            'admin_only'        => true,
        ],
        [
            'slug'              => 'jana.cc.read.self',
            'nome'              => 'Ver minhas sessões Claude Code',
            'descricao'         => 'Ver sessões CC do próprio user em /jana/admin/cc-sessions. Default pra todos com jana.mcp.use.',
            'resources_pattern' => null,
            'tools_pattern'     => 'cc-search',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.cc.read.team',
            'nome'              => 'Ver sessões Claude Code do time',
            'descricao'         => 'Cross-dev search e read em /jana/admin/cc-sessions. Felipe[F], Maiara[M], devs sêniores.',
            'resources_pattern' => null,
            'tools_pattern'     => 'cc-search',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.cc.read.all',
            'nome'              => 'Ver todas sessões CC + governança',
            'descricao'         => 'Acesso total à tela /jana/admin/cc-sessions, drill-down per-dev, KPIs cross-team. Wagner/superadmin.',
            'resources_pattern' => null,
            'tools_pattern'     => 'cc-search',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => true,
        ],
        [
            'slug'              => 'jana.cc.curate',
            'nome'              => 'Curar sessões Claude Code',
            'descricao'         => 'Marcar sessions como useful/noise/duplicate/wip — influencia ranking de cc-search. Wagner only.',
            'resources_pattern' => null,
            'tools_pattern'     => null,
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => true,
        ],
        [
            'slug'              => 'jana.cc.ingest.self',
            'nome'              => 'Ingerir minhas sessões CC',
            'descricao'         => 'Permite POST /api/cc/ingest pra watcher Node empurrar JSONL local. Default todos com mcp.use.',
            'resources_pattern' => null,
            'tools_pattern'     => null,
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.handoff.ack',
            'nome'              => 'Fechar handoff de design (handoff-ack)',
            'descricao'         => 'Permite handoff-ack (applied/rejected) na fila cowork_handoffs — loop zero-paste (ADR 0283). Mutação: applied exige gate_status verde (conformance && critique_score>=80 && a11y). Só o ator-Code (A7 do adversário [AH]) — emitir token com este scope via McpTokenIssuer. handoff-pending é read (basta jana.mcp.use).',
            'resources_pattern' => null,
            'tools_pattern'     => 'handoff-ack',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.handoff.submit',
            'nome'              => 'Submeter handoff de design assinado (handoff-submit)',
            'descricao'         => 'Permite handoff-submit (PR-6a, ADR 0283) — landing-pad HTTP que cria pending em cowork_handoffs a partir de um handoff ASSINADO (HMAC), sem SSH/commit. Mutação: sig inválida é recusada (A1), conteúdo idêntico é no-op, append-only por slug/version. SEM auto-merge. Só o ator-transporte (a GitHub Action on-push) — emitir token com este scope via McpTokenIssuer e guardá-lo como repo secret HANDOFF_SUBMIT_TOKEN.',
            'resources_pattern' => null,
            'tools_pattern'     => 'handoff-submit',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'jana.mcp.handoff.lever',
            'nome'              => 'Rotear estado de handoff na fila (handoff-lever)',
            'descricao'         => 'Permite handoff-lever (PR-7, ADR 0283) — liga as levers da fila cowork_handoffs: re-disparar (pending parado re-arma), devolver (rejected reabre pra pending) e supersede (pending/applied vira obsoleto, append-only). Mutação auditada e idempotente (só morde no status de origem). SEM auto-merge — o merge segue 1-clique do [W]. Emitir token com este scope via McpTokenIssuer pro ator que gerencia a fila.',
            'resources_pattern' => null,
            'tools_pattern'     => 'handoff-lever',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
    ];

    public function run(): void
    {
        $this->command?->info('Seeding MCP scopes + Spatie permissions...');

        $criadosScopes      = 0;
        $criadosPermissions = 0;

        foreach ($this->catalogo as $entry) {
            $slug = $entry['slug'];

            // 1. mcp_scopes (catálogo descritivo) — usa updateOrInsert pra idempotência.
            $existed = DB::table('mcp_scopes')->where('slug', $slug)->exists();
            DB::table('mcp_scopes')->updateOrInsert(
                ['slug' => $slug],
                array_merge(
                    array_diff_key($entry, ['slug' => true]),
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                )
            );
            if (! $existed) {
                $criadosScopes++;
            }

            // 2. Spatie permission — guard 'web' (default) pra encaixar com user_role atual.
            try {
                $perm = Permission::firstOrCreate(
                    ['name' => $slug, 'guard_name' => 'web'],
                );
                if ($perm->wasRecentlyCreated) {
                    $criadosPermissions++;
                }
            } catch (\Throwable $e) {
                $this->command?->warn("[$slug] Spatie permission falhou: " . $e->getMessage());
            }
        }

        $this->command?->info(sprintf(
            'MCP scopes seeded: %d novos catálogos, %d novas Spatie permissions.',
            $criadosScopes,
            $criadosPermissions
        ));

        $this->command?->line('Próximo: atribua aos roles existentes (ex: Admin#1) via php artisan tinker:');
        $this->command?->line('  Role::findByName("Admin#1")->givePermissionTo("jana.mcp.use", ...);');
    }
}
