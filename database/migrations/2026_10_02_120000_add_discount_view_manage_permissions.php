<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Separa VER × EDITAR descontos (decisão D1 de [W], 2026-10-02 — playbook de Vendas, thread 04).
 *
 * Cria `discount.view` (ver a lista de descontos) e `discount.manage` (criar, editar, excluir,
 * desativar e reativar) e as CONCEDE a quem hoje chega na tela.
 *
 * QUEM CHEGA HOJE (medido no código em 2026-10-02): o DiscountController checava UMA permissão,
 * `discount.access`, em todos os 8 métodos — ver, criar, editar, excluir, desativar em massa e
 * reativar. A view Blade checava `brand.view`/`brand.create` nos botões, mas isso só escondia
 * botão: sem `discount.access` o servidor devolvia 403 de qualquer jeito. Logo a ORIGEM do
 * acesso real é `discount.access`, e é dela que o backfill puxa — não de `brand.*`.
 *
 * POR QUE O BACKFILL É OBRIGATÓRIO: o controller troca as guardas para `discount.view|manage`
 * no mesmo PR. Sem esta migration, no dia do deploy TODO papel não-admin perde a tela.
 *
 * MAPA (simétrico, porque a permissão antiga dava tudo):
 *   discount.view   <- quem tem `discount.access`
 *   discount.manage <- quem tem `discount.access`
 * Ninguém perde nada no deploy. Quem deve passar a só VER é decisão do admin de cada negócio,
 * desmarcando `discount.manage` em /roles/{id}/edit depois.
 *
 * ALCANCE HONESTO: o dono do negócio NÃO depende disto — `Gate::before`
 * (app/Providers/AuthServiceProvider.php) libera qualquer ability para `Admin#{business_id}`.
 * Quem está em risco são os papéis criados à mão pelo cliente.
 *
 * DOIS PIVÔS: o Spatie concede permissão por papel (`role_has_permissions`) E direto ao usuário
 * (`model_has_permissions`). O precedente (2026_08_20_120000_add_commission_agent_permissions)
 * só percorria papéis; aqui os dois, para que nenhum caminho de acesso existente se perca.
 *
 * MULTI-TENANT (ADR 0093): `permissions` é GLOBAL por desenho do Spatie (não tem business_id),
 * mas `roles` é por negócio (`nome#{biz}`) e `users` também. O backfill só COPIA vínculos que já
 * existem — cada papel/usuário recebe a permissão nova dentro do próprio negócio. Nenhum ganha
 * acesso a dado de outro negócio.
 *
 * `discount.access` NÃO é apagada: deixa de ser lida pelo código e sai da tela de papéis, mas a
 * linha e os vínculos ficam (o down() precisa dela para voltar atrás sem perder nada).
 *
 * IDEMPOTENTE: firstOrCreate nas permissões + insertOrIgnore nos pivôs.
 *
 * ANTES -> DEPOIS, leitura pura (rodar antes do deploy):
 *
 *   SELECT r.business_id, r.id AS role_id, r.name AS papel
 *   FROM roles r
 *   JOIN role_has_permissions rhp ON rhp.role_id = r.id
 *   JOIN permissions p ON p.id = rhp.permission_id
 *   WHERE p.name = 'discount.access'
 *   ORDER BY r.business_id, r.name;
 *   -- cada linha ganha discount.view E discount.manage.
 *
 *   SELECT mhp.model_type, mhp.model_id
 *   FROM model_has_permissions mhp
 *   JOIN permissions p ON p.id = mhp.permission_id
 *   WHERE p.name = 'discount.access';
 */
return new class extends Migration
{
    private const ORIGEM = 'discount.access';

    private const NOVAS = ['discount.view', 'discount.manage'];

    public function up(): void
    {
        $origemId = DB::table('permissions')
            ->where('name', self::ORIGEM)
            ->where('guard_name', 'web')
            ->value('id');

        foreach (self::NOVAS as $nova) {
            $permissao = Permission::firstOrCreate([
                'name' => $nova,
                'guard_name' => 'web',
            ]);

            if ($origemId === null) {
                // Instalação nova: não há acesso antigo a preservar.
                continue;
            }

            $this->copiarVinculosDePapel((int) $origemId, (int) $permissao->id);
            $this->copiarVinculosDiretos((int) $origemId, (int) $permissao->id);
        }

        $this->limparCacheDePermissoes();
    }

    public function down(): void
    {
        // FK ON DELETE CASCADE nos dois pivôs derruba os vínculos junto.
        // `discount.access` ficou intacta no up(), então voltar atrás não tira acesso.
        Permission::whereIn('name', self::NOVAS)
            ->where('guard_name', 'web')
            ->delete();

        $this->limparCacheDePermissoes();
    }

    private function copiarVinculosDePapel(int $origemId, int $novaId): void
    {
        $papeis = DB::table('role_has_permissions')
            ->where('permission_id', $origemId)
            ->pluck('role_id');

        foreach ($papeis->chunk(500) as $lote) {
            $linhas = [];
            foreach ($lote as $roleId) {
                $linhas[] = ['permission_id' => $novaId, 'role_id' => (int) $roleId];
            }
            if ($linhas !== []) {
                DB::table('role_has_permissions')->insertOrIgnore($linhas);
            }
        }
    }

    private function copiarVinculosDiretos(int $origemId, int $novaId): void
    {
        $vinculos = DB::table('model_has_permissions')
            ->where('permission_id', $origemId)
            ->get(['model_type', 'model_id']);

        foreach ($vinculos->chunk(500) as $lote) {
            $linhas = [];
            foreach ($lote as $v) {
                $linhas[] = [
                    'permission_id' => $novaId,
                    'model_type' => $v->model_type,
                    'model_id' => $v->model_id,
                ];
            }
            if ($linhas !== []) {
                DB::table('model_has_permissions')->insertOrIgnore($linhas);
            }
        }
    }

    private function limparCacheDePermissoes(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Tolerante a ambiente sem cache configurado.
        }
    }
};
