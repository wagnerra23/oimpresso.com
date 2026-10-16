<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissões da tela Sistema › Fluxos e regras (ADR 0435).
 *
 *   fluxos.ver        — abrir a tela e ler modelos, processos e casos de uso
 *   fluxos.configurar — trocar o modelo de fluxo da empresa (ação do PR 3)
 *
 * QUEM RECEBE POR PADRÃO: o dono (`Admin#{biz}`) já passa por `Gate::before`
 * (AuthServiceProvider) e não depende disto. O pedido de [W] é "admin/gerente"; no ERP o
 * papel que configura a empresa é quem tem `business_settings.access` (Configurações da
 * empresa), então é dele que as duas permissões são copiadas. O admin de cada negócio pode
 * desmarcar depois em /roles/{id}/edit.
 *
 * MULTI-TENANT (ADR 0093): `permissions` é global no Spatie; o backfill só copia vínculos que
 * já existem (papel `nome#{biz}` e usuário do próprio negócio). Ninguém ganha acesso a outro
 * negócio.
 *
 * IDEMPOTENTE: firstOrCreate + insertOrIgnore.
 */
return new class extends Migration
{
    private const ORIGEM = 'business_settings.access';

    private const NOVAS = ['fluxos.ver', 'fluxos.configurar'];

    public function up(): void
    {
        $origemId = DB::table('permissions')->where('name', self::ORIGEM)->where('guard_name', 'web')->value('id');

        foreach (self::NOVAS as $nova) {
            $id = (int) Permission::firstOrCreate(['name' => $nova, 'guard_name' => 'web'])->id;
            if ($origemId === null) {
                continue;
            }
            foreach (DB::table('role_has_permissions')->where('permission_id', $origemId)->pluck('role_id')->chunk(500) as $lote) {
                DB::table('role_has_permissions')->insertOrIgnore(
                    $lote->map(fn ($roleId) => ['permission_id' => $id, 'role_id' => (int) $roleId])->values()->all()
                );
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $origemId)->get(['model_type', 'model_id'])->chunk(500) as $lote) {
                DB::table('model_has_permissions')->insertOrIgnore(
                    $lote->map(fn ($v) => ['permission_id' => $id, 'model_type' => $v->model_type, 'model_id' => $v->model_id])->values()->all()
                );
            }
        }

        $this->limparCache();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::NOVAS)->where('guard_name', 'web')->delete();
        $this->limparCache();
    }

    private function limparCache(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ambiente sem cache configurado
        }
    }
};
