<?php

declare(strict_types=1);

use App\Business;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Modules\AssetManagement\Notifications\AssetAssignedForMaintenance;
use Modules\AssetManagement\Notifications\AssetSentForMaintenance;
use Modules\AssetManagement\Utils\AssetUtil;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Lote 0.2 da auditoria do Patrimonio (2026-11-05) — responsavel e destinatarios de aviso.
 *
 * Achados do eixo 5:
 *  - A3: o `update()` da manutencao aceitava `assigned_to` de usuario de OUTRA empresa e
 *    disparava para ele o aviso (banco e, se ligado, e-mail com codigo do bem e nota).
 *  - A4: os destinatarios de "enviado para manutencao" saiam da configuracao sem filtro de
 *    empresa, e a tela de configuracoes gravava qualquer id.
 *
 * ADR 0358: tenant 98 (dono) x 99 (adversario). biz=1 e biz=4 nunca.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('asset_maintenances') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Tabelas asset_maintenances/business ausentes — rode migrate primeiro');
    }
});

/** `$role = 'Admin'` usa o role `Admin#{biz}` que o `is_admin()` confere (nao e apagado). */
function manutNotUsuario(int $businessId, ?string $role = null): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'manut_not_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    if ($role !== null) {
        $nome = $role === 'Admin' ? 'Admin#'.$businessId : 'manut-not#'.$businessId;
        $papel = Role::firstOrCreate(['name' => $nome, 'guard_name' => 'web'], ['business_id' => $businessId]);
        $papel->givePermissionTo(Permission::firstOrCreate(['name' => 'asset.view_all_maintenance', 'guard_name' => 'web']));
        $user->assignRole($papel);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    return $user;
}

function manutNotBemEManutencao(int $businessId, int $ownerId): AssetMaintenance
{
    $bem = Asset::create([
        'business_id' => $businessId,
        'name' => 'MANUT-NOT bem',
        'asset_code' => 'MANUT-NOT-'.uniqid(),
        'quantity' => 1,
        'unit_price' => 100,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);

    return AssetMaintenance::create([
        'business_id' => $businessId,
        'asset_id' => $bem->id,
        'maitenance_id' => 'MANUT-NOT-'.uniqid(),   // typo do schema, preservado
        'status' => 'in_progress',
        'priority' => 'high',
        'created_by' => $ownerId,
        'details' => 'Fixture MANUT-NOT',
    ]);
}

function manutNotComo(User $user, int $businessId)
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);

    return test()->actingAs($user)->withSession([
        'user.business_id' => $businessId,
        'user.id' => $user->id,
        'user' => ['business_id' => $businessId, 'id' => $user->id],
    ]);
}

function manutNotLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);
    $bens = Asset::where('asset_code', 'like', 'MANUT-NOT-%')->pluck('id');
    AssetMaintenance::whereIn('asset_id', $bens)->forceDelete();
    Asset::whereIn('id', $bens)->forceDelete();
    foreach (User::withTrashed()->where('username', 'like', 'manut_not_%')->get() as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'manut-not#%')->delete();
}

it('A3: responsavel de outra empresa e recusado e nao recebe aviso; colega da empresa e aceito e avisado', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = manutNotUsuario($dono, 'manut');
    $colega = manutNotUsuario($dono);
    $uAdv = manutNotUsuario($adv);

    try {
        Notification::fake();
        $m = manutNotBemEManutencao($dono, $u->id);

        manutNotComo($u, $dono)->from('/asset/asset-maintenance')
            ->put("/asset/asset-maintenance/{$m->id}", [
                'status' => 'in_progress', 'priority' => 'high', 'details' => 'troca', 'assigned_to' => (string) $uAdv->id,
            ])
            ->assertSessionHasErrors(['assigned_to']);
        expect($m->refresh()->assigned_to)->toBeNull();
        Notification::assertNotSentTo($uAdv, AssetAssignedForMaintenance::class);

        // Controle: o mesmo PUT com colega da empresa grava e avisa.
        manutNotComo($u, $dono)
            ->put("/asset/asset-maintenance/{$m->id}", [
                'status' => 'in_progress', 'priority' => 'high', 'details' => 'troca', 'assigned_to' => (string) $colega->id,
            ])
            ->assertSessionHasNoErrors();
        expect((int) $m->refresh()->assigned_to)->toBe($colega->id);
        Notification::assertSentTo($colega, AssetAssignedForMaintenance::class);
    } finally {
        manutNotLimpar();
    }
});

it('A4: aviso de enviado para manutencao so chega a destinatario da propria empresa', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $u = manutNotUsuario($dono);
    $colega = manutNotUsuario($dono);
    $uAdv = manutNotUsuario($adv);
    $original = Business::where('id', $dono)->value('asset_settings');

    try {
        Notification::fake();
        // Configuracao corrompida (o que a tela gravava antes): um colega e um id de fora.
        Business::where('id', $dono)->update(['asset_settings' => json_encode([
            'send_for_maintenence_recipients' => [(string) $colega->id, (string) $uAdv->id],
        ])]);
        $m = manutNotBemEManutencao($dono, $u->id);

        app(AssetUtil::class)->sendAssetSentForMaintenanceNotification($m->id);

        // Ancora positiva: o envio aconteceu (o colega recebeu).
        Notification::assertSentTo($colega, AssetSentForMaintenance::class);
        Notification::assertNotSentTo($uAdv, AssetSentForMaintenance::class);
    } finally {
        Business::where('id', $dono)->update(['asset_settings' => $original]);
        manutNotLimpar();
    }
});

it('A4: configuracoes recusam destinatario de outra empresa e nao gravam; so da empresa grava', function () {
    $dono = (int) $this->seededTenant()->id;
    $adv = (int) $this->seededSupportClientTenant()->id;
    $admin = manutNotUsuario($dono, 'Admin');
    $uAdv = manutNotUsuario($adv);
    $original = Business::where('id', $dono)->value('asset_settings');

    try {
        manutNotComo($admin, $dono)->from('/asset/settings')
            ->post('/asset/settings', [
                'asset_code_prefix' => 'MNT', 'send_for_maintenence_recipients' => [(string) $admin->id, (string) $uAdv->id],
            ])
            ->assertSessionHasErrors(['send_for_maintenence_recipients']);
        expect(Business::where('id', $dono)->value('asset_settings'))->toBe($original);

        manutNotComo($admin, $dono)->from('/asset/settings')
            ->post('/asset/settings', [
                'asset_code_prefix' => 'MNT', 'send_for_maintenence_recipients' => [(string) $admin->id],
            ])
            ->assertSessionHasNoErrors();
        $gravado = json_decode((string) Business::where('id', $dono)->value('asset_settings'), true);
        expect(array_map('strval', (array) ($gravado['send_for_maintenence_recipients'] ?? [])))->toBe([(string) $admin->id]);
    } finally {
        Business::where('id', $dono)->update(['asset_settings' => $original]);
        manutNotLimpar();
    }
});
