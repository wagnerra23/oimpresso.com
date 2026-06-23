<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria a permissão delegável `officeimpresso.clientes.liberar`.
 *
 * Permite conceder o "liberar clientes" (gestão das credenciais OAuth que cada
 * Delphi usa pra autenticar) a um login próprio de funcionário via uma Função
 * (role) comum — SEM precisar do `superadmin`, que abriria junto o Financeiro.
 *
 * Idempotente (firstOrCreate) — re-run não duplica. Flush do cache Spatie pra a
 * próxima request enxergar (prod usa cache).
 *
 * @see Modules\Officeimpresso\Http\Controllers\ClientController::authorizeLiberar()
 * @see Modules\Officeimpresso\Http\Controllers\DataController::user_permissions()
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate([
            'name' => 'officeimpresso.clientes.liberar',
            'guard_name' => 'web',
        ]);

        $this->flushPermissionCache();
    }

    public function down(): void
    {
        Permission::where('name', 'officeimpresso.clientes.liberar')
            ->where('guard_name', 'web')
            ->delete();

        $this->flushPermissionCache();
    }

    private function flushPermissionCache(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Tolerante a ambiente sem cache configurado (smoke local).
        }
    }
};
