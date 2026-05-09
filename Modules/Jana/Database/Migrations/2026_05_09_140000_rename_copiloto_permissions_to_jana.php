<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename permissions Spatie `copiloto.*` → `jana.*` (Fase 2b Jana naming alignment).
 *
 * Wagner 2026-05-09: completar rename Copiloto → Jana — Fase 2a (PR #294)
 * já cobriu Pages/Components, este completa permissions + URLs.
 *
 * Strategy:
 *   - UPDATE preserva permission_id, role_has_permissions, model_has_permissions
 *   - Cache Spatie é flushed automaticamente no próximo request via PermissionRegistrar
 *   - Idempotente: se name já é jana.*, REPLACE no-op
 *
 * Refs: ADR 0011 alinhamento-padrao-jana, ADR 0090 rename-modulos-snake-case-pre-deploy,
 *       ADR 0092 tabela-rename-copiloto-para-jana (mesmo padrão DB),
 *       commit 8f7a5138 Fase 3.7 PR-2 (rename PHP-only)
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $renamed = DB::table('permissions')
            ->where('name', 'LIKE', 'copiloto.%')
            ->update(['name' => DB::raw("REPLACE(name, 'copiloto.', 'jana.')")]);

        if (app()->runningInConsole()) {
            echo "  [rename_copiloto_permissions_to_jana] {$renamed} permissions renomeadas\n";
        }

        if (function_exists('app') && app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        // Inversão: jana.* → copiloto.*
        // CUIDADO: se houver permission jana.* pré-existente (não-renomeada por
        // esta migration), down() vai renomeá-la pra copiloto.* incorretamente.
        // Em 2026-05-09 todas as jana.* SÃO consequência desta migration.
        $reverted = DB::table('permissions')
            ->where('name', 'LIKE', 'jana.%')
            ->update(['name' => DB::raw("REPLACE(name, 'jana.', 'copiloto.')")]);

        if (app()->runningInConsole()) {
            echo "  [rename_copiloto_permissions_to_jana DOWN] {$reverted} permissions revertidas\n";
        }

        if (function_exists('app') && app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
