<?php

namespace Modules\Copiloto\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * MEM-MCP-1.d (ADR 0053) — Seeder de scopes MCP + Spatie permissions.
 *
 * Cria entradas em:
 *   - mcp_scopes — catálogo legível com descrições e patterns de tools/resources
 *   - permissions (Spatie) — gates `copiloto.mcp.*` que o middleware/tools checam
 *
 * Idempotente: pode rodar quantas vezes precisar — usa firstOrCreate.
 *
 * Roda: `php artisan db:seed --class=Modules\\Copiloto\\Database\\Seeders\\McpScopesSeeder`
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
            'slug'              => 'copiloto.mcp.use',
            'nome'              => 'Acessar MCP server',
            'descricao'         => 'Gate básico: precisa pra fazer qualquer chamada MCP autenticada. Sem isso o middleware retorna 403.',
            'resources_pattern' => null,
            'tools_pattern'     => null,
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'copiloto.mcp.tasks.read',
            'nome'              => 'Ler tarefas do cycle (CURRENT.md)',
            'descricao'         => 'Permite tools/resources que retornam estado vivo do projeto: CURRENT.md + handoff. Padrão pra todos os devs.',
            'resources_pattern' => 'oimpresso://memory/(handoff|current)',
            'tools_pattern'     => 'tasks-.*',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'copiloto.mcp.decisions.read',
            'nome'              => 'Ler ADRs (decisões arquiteturais)',
            'descricao'         => 'Permite buscar e ler ADRs em memory/decisions/. Padrão pra todos os devs (decisões são públicas no time).',
            'resources_pattern' => 'oimpresso://memory/decisions/.*',
            'tools_pattern'     => 'decisions-.*',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'copiloto.mcp.sessions.read',
            'nome'              => 'Ler session logs',
            'descricao'         => 'Permite listar/ler logs de sessões anteriores. Padrão pra todos os devs.',
            'resources_pattern' => 'oimpresso://memory/sessions/.*',
            'tools_pattern'     => 'sessions-.*',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'copiloto.mcp.usage.self',
            'nome'              => 'Ver próprio uso/custo do Claude Code',
            'descricao'         => 'Permite consultar resumo de uso pessoal (tokens, R$, top tools). Sempre concedido a quem tem `copiloto.mcp.use`.',
            'resources_pattern' => null,
            'tools_pattern'     => 'claude-code-usage-self',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'copiloto.mcp.usage.all',
            'nome'              => 'Ver uso/custo de TODOS os devs',
            'descricao'         => 'Dashboards consolidados de governança: spend total, top users, top tools cross-team. Apenas Wagner/admin.',
            'resources_pattern' => null,
            'tools_pattern'     => 'claude-code-usage-all',
            'is_destructive'    => false,
            'business_required' => false,
            'admin_only'        => true,
        ],
        [
            'slug'              => 'copiloto.mcp.governanca.financeiro',
            'nome'              => 'Acessar contexto financeiro via MCP',
            'descricao'         => 'Acesso a tools/resources financeiros (faturamento, ticket médio, contas a pagar/receber). Wagner + Eliana[E].',
            'resources_pattern' => 'oimpresso://financeiro/.*',
            'tools_pattern'     => 'financeiro-.*',
            'is_destructive'    => false,
            'business_required' => true,
            'admin_only'        => false,
        ],
        [
            'slug'              => 'copiloto.mcp.governanca.tecnico',
            'nome'              => 'Acessar contexto técnico via MCP',
            'descricao'         => 'Acesso a tools/resources de qualidade IA (faithfulness, latência, recall, alertas). Felipe[F] + Wagner.',
            'resources_pattern' => 'oimpresso://qualidade/.*',
            'tools_pattern'     => '(qualidade|faithfulness|latency)-.*',
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
        $this->command?->line('  Role::findByName("Admin#1")->givePermissionTo("copiloto.mcp.use", ...);');
    }
}
