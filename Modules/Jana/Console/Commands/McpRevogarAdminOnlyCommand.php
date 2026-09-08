<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Spatie\Permission\Models\Role;

/**
 * Revoga os scopes MCP `admin_only` concedidos dentro de UM business.
 *
 * POR QUE EXISTE: até 2026-09-07 o `DataController@mcpScopePermissions` ofertava o catálogo
 * INTEIRO como checkbox em `/roles/{id}/edit`, tela que exige `roles.update` — permission de
 * admin de BUSINESS, não de superadmin. Os 5 scopes `admin_only` eram, portanto,
 * auto-concedíveis. O PR #6962 fechou a CONCESSÃO (filtro + `__preservaNaoOfertadas`), mas de
 * propósito NÃO revogou o que já estava concedido: derrubar acesso de cliente LIVE num deploy
 * é decisão do dono, não efeito colateral de correção. Este comando é o ato separado.
 *
 * ⚠️ ESCRITA EM PRODUÇÃO. Por isso o default é DRY-RUN: sem `--apply` ele só mede e imprime o
 * antes→depois. Nenhuma linha é tocada sem a flag explícita.
 *
 * IDEMPOTENTE: rodar 2× com `--apply` — a 2ª não encontra nada e sai 0.
 *
 * DOIS CAMINHOS DE CONCESSÃO, não um. O Spatie permite permission via ROLE
 * (`role_has_permissions`) e DIRETA no usuário (`model_has_permissions`). Revogar só o
 * primeiro deixaria o acesso vivo pelo segundo, e o relatório diria "revogado" — por isso os
 * dois são medidos e revogados, e o comando imprime cada um separadamente.
 *
 * TIER 0 (ADR 0093): `Role` é o Spatie puro e NÃO tem global scope de `business_id`; o filtro
 * é explícito nas duas pontas (roles do business, users do business). Sem ele, revogar do
 * biz A alcançaria papel do biz B.
 *
 * NÃO derive a lista de slugs à mão: ela vem do catálogo do `McpScopesSeeder`, a mesma fonte
 * que cria as Spatie permissions (ADR 0256). Scope `admin_only` novo já nasce coberto.
 *
 * Uso:
 *   php artisan jana:mcp-revogar-admin-only 164            # mede e imprime (não escreve)
 *   php artisan jana:mcp-revogar-admin-only 164 --apply    # revoga
 */
class McpRevogarAdminOnlyCommand extends Command
{
    protected $signature = 'jana:mcp-revogar-admin-only
                            {business_id : ID do business cujo acesso será revogado}
                            {--apply : Aplica de fato. Sem esta flag o comando só mede (dry-run).}';

    protected $description = 'Revoga os scopes MCP admin_only concedidos num business (dry-run por padrão).';

    public function handle(): int
    {
        $businessId = (int) $this->argument('business_id');
        $aplicar = (bool) $this->option('apply');

        if ($businessId <= 0) {
            $this->error('business_id inválido.');

            return self::FAILURE;
        }

        $slugs = $this->slugsAdminOnly();

        if (empty($slugs)) {
            // Sem isto o comando sairia "nada a revogar" por AUSÊNCIA DE DADO e o operador
            // leria isso como "está limpo" — o verde tautológico do §5 2026-07-24.
            $this->error('Catálogo sem scope admin_only — o McpScopesSeeder mudou? Abortado sem tocar em nada.');

            return self::FAILURE;
        }

        $this->line(sprintf('Scopes admin_only no catálogo: %d', count($slugs)));
        foreach ($slugs as $s) {
            $this->line('  · '.$s);
        }
        $this->newLine();

        $viaRole = $this->concessoesViaRole($businessId, $slugs);
        $viaUser = $this->concessoesDiretasNoUser($businessId, $slugs);

        $totalConcessoes = array_sum(array_map(static fn (array $r): int => count($r['scopes']), $viaRole))
            + array_sum(array_map(static fn (array $u): int => count($u['scopes']), $viaUser));

        $this->relatar($businessId, $viaRole, $viaUser, $totalConcessoes);

        if ($totalConcessoes === 0) {
            $this->info('Nada a revogar — o business já está limpo.');

            return self::SUCCESS;
        }

        if (! $aplicar) {
            $this->newLine();
            $this->warn('DRY-RUN: nada foi escrito. Repita com --apply para revogar.');

            return self::SUCCESS;
        }

        $revogadas = $this->revogar($viaRole, $viaUser);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info(sprintf('Revogadas %d concessão(ões). Cache de permissões limpo.', $revogadas));

        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function slugsAdminOnly(): array
    {
        return array_values(array_map(
            static fn (array $s): string => $s['slug'],
            array_filter(
                McpScopesSeeder::catalogo(),
                static fn (array $s): bool => ($s['admin_only'] ?? false) === true
            )
        ));
    }

    /**
     * @param  array<int,string>  $slugs
     * @return array<int,array{role: Role, scopes: array<int,string>, users: int}>
     */
    private function concessoesViaRole(int $businessId, array $slugs): array
    {
        // Tier 0: `Role` é Spatie puro, sem global scope — o filtro por business_id é explícito.
        $roles = Role::where('business_id', $businessId)->with('permissions')->get();

        $out = [];
        foreach ($roles as $role) {
            $tem = array_values(array_intersect($role->permissions->pluck('name')->all(), $slugs));

            if ($tem === []) {
                continue;
            }

            $out[] = [
                'role' => $role,
                'scopes' => $tem,
                'users' => DB::table('model_has_roles')->where('role_id', $role->id)->count(),
            ];
        }

        return $out;
    }

    /**
     * Concessão DIRETA no usuário — o segundo caminho do Spatie, que uma revogação só-por-role
     * deixaria vivo enquanto reportava sucesso.
     *
     * @param  array<int,string>  $slugs
     * @return array<int,array{user: User, scopes: array<int,string>}>
     */
    private function concessoesDiretasNoUser(int $businessId, array $slugs): array
    {
        $users = User::where('business_id', $businessId)->with('permissions')->get();

        $out = [];
        foreach ($users as $user) {
            $tem = array_values(array_intersect($user->permissions->pluck('name')->all(), $slugs));

            if ($tem === []) {
                continue;
            }

            $out[] = ['user' => $user, 'scopes' => $tem];
        }

        return $out;
    }

    /**
     * @param  array<int,array{role: Role, scopes: array<int,string>, users: int}>  $viaRole
     * @param  array<int,array{user: User, scopes: array<int,string>}>  $viaUser
     */
    private function relatar(int $businessId, array $viaRole, array $viaUser, int $total): void
    {
        $this->line("=== business_id={$businessId} — ANTES ===");

        if ($viaRole === []) {
            $this->line('  (nenhuma concessão via role)');
        }
        foreach ($viaRole as $r) {
            $this->line(sprintf(
                '  role #%d "%s" — %d usuário(s) — %d scope(s):',
                $r['role']->id,
                $r['role']->name,
                $r['users'],
                count($r['scopes'])
            ));
            foreach ($r['scopes'] as $s) {
                $this->line('      '.$s);
            }
        }

        if ($viaUser === []) {
            $this->line('  (nenhuma concessão direta em usuário)');
        }
        foreach ($viaUser as $u) {
            $this->line(sprintf(
                '  user #%d (direta) — %d scope(s): %s',
                $u['user']->id,
                count($u['scopes']),
                implode(', ', $u['scopes'])
            ));
        }

        $this->newLine();
        $this->line("=== DEPOIS ===");
        $this->line('  0 concessão de scope admin_only neste business.');
        $this->newLine();
        $this->line(sprintf('Concessões a revogar: %d', $total));

        if ($total > 0) {
            $this->warn('Impacto: `jana.mcp.usage.all` é o único gate de /governance/qualidade-ia');
            $this->warn('e das 8 telas do hub de engenharia da Forja. Quem perder este scope');
            $this->warn('deixa de acessar essas telas.');
        }
    }

    /**
     * @param  array<int,array{role: Role, scopes: array<int,string>, users: int}>  $viaRole
     * @param  array<int,array{user: User, scopes: array<int,string>}>  $viaUser
     */
    private function revogar(array $viaRole, array $viaUser): int
    {
        $n = 0;

        foreach ($viaRole as $r) {
            foreach ($r['scopes'] as $slug) {
                $r['role']->revokePermissionTo($slug);
                $n++;
                $this->line(sprintf('  revogado: %s  ←  role #%d "%s"', $slug, $r['role']->id, $r['role']->name));
            }
        }

        foreach ($viaUser as $u) {
            foreach ($u['scopes'] as $slug) {
                $u['user']->revokePermissionTo($slug);
                $n++;
                $this->line(sprintf('  revogado: %s  ←  user #%d (direta)', $slug, $u['user']->id));
            }
        }

        return $n;
    }
}
