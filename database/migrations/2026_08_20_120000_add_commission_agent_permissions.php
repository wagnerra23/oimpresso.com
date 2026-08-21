<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria `commission_agent.view` e `commission_agent.manage` e as CONCEDE a quem hoje
 * chega na tela de comissionados pelas permissoes de USUARIO.
 *
 * POR QUE o backfill e obrigatorio: o SalesCommissionAgentController trocou as guardas de
 * `user.*` para `commission_agent.*` no mesmo PR. Sem esta migration, no dia do deploy TODO
 * papel nao-admin perde a tela — a permissao nova nasce sem nenhum papel apontando pra ela.
 *
 * ALCANCE HONESTO: o dono do negocio NAO depende disto. `Gate::before`
 * (app/Providers/AuthServiceProvider.php) devolve true pra quem tem `Admin#{business_id}`,
 * entao admin passa com ou sem a permissao. Quem esta em risco — e o motivo desta migration
 * existir — sao os papeis criados a mao pelo cliente.
 *
 * MAPA DE ORIGEM (nao e simetrico de proposito):
 *   commission_agent.view   <- papel que tem `user.view`
 *   commission_agent.manage <- papel que tem `user.create` OU `user.update` OU `user.delete`
 *
 * O `manage` puxa das TRES porque a tela antiga distribuia a escrita entre elas (criar =
 * user.create, editar = user.update, excluir = user.delete). Puxar so de `user.delete`
 * tiraria o criar/editar de quem tinha criar/editar mas nao excluir.
 *
 * MULTI-TENANT (ADR 0093): `permissions` e GLOBAL (nao tem business_id — ver
 * 2017_07_26_083429_create_permission_tables), mas `roles` E por negocio. Como o backfill
 * percorre o pivo `role_has_permissions`, cada papel leva a permissao dentro do proprio
 * business_id. Nenhum papel ganha acesso a dado de outro negocio.
 *
 * IDEMPOTENTE: firstOrCreate nas permissoes + insertOrIgnore no pivo. Re-run nao duplica
 * e nao derruba concessao feita a mao depois.
 *
 * ANTES -> DEPOIS, sem escrever nada (rodar como leitura pura antes do deploy):
 *
 *   SELECT r.business_id, r.id AS role_id, r.name AS papel,
 *          MAX(p.name = 'user.view')   AS ganha_view,
 *          MAX(p.name IN ('user.create','user.update','user.delete')) AS ganha_manage
 *   FROM roles r
 *   JOIN role_has_permissions rhp ON rhp.role_id = r.id
 *   JOIN permissions p ON p.id = rhp.permission_id
 *   WHERE p.name IN ('user.view','user.create','user.update','user.delete')
 *   GROUP BY r.business_id, r.id, r.name
 *   ORDER BY r.business_id, r.name;
 */
return new class extends Migration
{
    /** Origem de cada permissao nova: nome novo => permissoes antigas que a concedem. */
    private const BACKFILL = [
        'commission_agent.view' => ['user.view'],
        'commission_agent.manage' => ['user.create', 'user.update', 'user.delete'],
    ];

    public function up(): void
    {
        foreach (self::BACKFILL as $nova => $origens) {
            $permissao = Permission::firstOrCreate([
                'name' => $nova,
                'guard_name' => 'web',
            ]);

            $this->concederAosPapeisQueJaTinham((int) $permissao->id, $origens);
        }

        $this->limparCacheDePermissoes();
    }

    public function down(): void
    {
        // O delete em `permissions` derruba o pivo por FK ON DELETE CASCADE
        // (role_has_permissions_permission_id_foreign) — nao precisa limpar a mao.
        Permission::whereIn('name', array_keys(self::BACKFILL))
            ->where('guard_name', 'web')
            ->delete();

        $this->limparCacheDePermissoes();
    }

    /**
     * Liga a permissao nova a todo papel que ja tem qualquer uma das de origem.
     *
     * @param  array<int,string>  $origens
     */
    private function concederAosPapeisQueJaTinham(int $permissaoNovaId, array $origens): void
    {
        $origemIds = DB::table('permissions')
            ->whereIn('name', $origens)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($origemIds->isEmpty()) {
            // Instalacao nova: as permissoes de usuario ainda nao existem. Nao ha o que
            // preservar, e o papel Admin passa pelo Gate::before de qualquer jeito.
            return;
        }

        $papeis = DB::table('role_has_permissions')
            ->whereIn('permission_id', $origemIds)
            ->distinct()
            ->pluck('role_id');

        foreach ($papeis->chunk(500) as $lote) {
            $linhas = [];
            foreach ($lote as $roleId) {
                $linhas[] = [
                    'permission_id' => $permissaoNovaId,
                    'role_id' => (int) $roleId,
                ];
            }

            if ($linhas !== []) {
                // insertOrIgnore = INSERT IGNORE: re-run nao estoura na PK composta.
                DB::table('role_has_permissions')->insertOrIgnore($linhas);
            }
        }
    }

    private function limparCacheDePermissoes(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Tolerante a ambiente sem cache configurado (smoke local).
        }
    }
};
