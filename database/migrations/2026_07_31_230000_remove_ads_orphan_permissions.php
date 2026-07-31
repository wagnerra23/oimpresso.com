<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove as permissions Spatie orfas do Modules/ADS (ADR 0363, parte 6).
 *
 * As 10 abaixo governavam telas que deixaram de existir no #5135:
 *   - 6 do `AdsAdminSkillsPermissionsSeeder` (ADR 0076) — as 5 telas de Skills
 *   - 4 do `DataController::user_permissions()` do ADS — Decisoes e Policy
 *
 * Decisao [W] 2026-07-31 ("remova as permissions Spatie ads.admin.skills.*").
 * As outras 4 seguem pela MESMA razao: a tela que cada uma protegia caiu no
 * mesmo PR, e o DataController que as declarava na tela de Roles ja nao
 * existe — ninguem consegue mais marca-las. Sao linhas mortas no banco.
 *
 * ORDEM IMPORTA: a ADR 0363 §3 registra que concessoes vivem em
 * role_has_permissions/model_has_permissions POR ID DE LINHA. Apagar a
 * permission sem apagar a concessao deixaria referencia orfa — por isso
 * concessoes primeiro, permission depois.
 *
 * NAO toca `ads_module` em package_details (chave de assinatura = ato de
 * superadmin, ADR 0363 §4) nem as URLs/names `ads.admin.*` que sobreviveram
 * na Forja (congelados pela ADR 0087, que segue aceito/ativo).
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'ads.admin.skills.read',
        'ads.admin.skills.edit',
        'ads.admin.skills.test',
        'ads.admin.skills.approve',
        'ads.admin.skills.publish',
        'ads.admin.skills.config',
        'ads.access',
        'ads.decisoes.review',
        'ads.decisoes.approve',
        'ads.policy.manage',
    ];

    public function up(): void
    {
        if (! $this->tabelasExistem()) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    /**
     * Recria as permissions, SEM as concessoes: os ids originais morreram com
     * as linhas, entao quem tinha acesso precisa ser re-marcado em
     * /roles/{id}/edit. Reversibilidade honesta, nao completa.
     */
    public function down(): void
    {
        if (! $this->tabelasExistem()) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            if (DB::table('permissions')->where('name', $name)->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                'name'       => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function tabelasExistem(): bool
    {
        return Schema::hasTable('permissions')
            && Schema::hasTable('role_has_permissions')
            && Schema::hasTable('model_has_permissions');
    }
};
