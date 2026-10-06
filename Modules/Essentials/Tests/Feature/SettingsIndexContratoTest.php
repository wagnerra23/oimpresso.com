<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da tela /hrm/settings (Configurações do Essentials) —
 * `EssentialsSettingsController@edit` + `@update`.
 *
 * UC-ESET-01        — o admin abre a tela e recebe as 5 chaves vivas, e só elas.
 * UC-ESET-02 `[T0]` — salvar grava no JSON do MEU business e não toca o de outro.
 * UC-ESET-03        — quem não é admin recebe 403 ao abrir e ao salvar, e nada é gravado.
 * UC-ESET-04        — prefixo acima de 32 caracteres é recusado e nada é gravado.
 *
 * Os UC derivam da US-ESS-014 (SPEC) + `Index.charter.md` §Goals/§Non-Goals, nunca do `.tsx`.
 * Trio: resources/js/Pages/Essentials/Settings/{Index.charter.md,Index.casos.md}
 *
 * COMPLEMENTA o `HrmPresencaCedeAoPontoTest.php` (UC-HRM-PRES-05), que prova que as 5 chaves
 * de presença aposentadas não voltam. Este prova o resto do contrato: quem pode, onde grava.
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 (fictício) × 99 (adversário). NUNCA biz=4.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    if (! Schema::hasColumn('business', 'essentials_settings')) {
        $this->markTestSkipped('Coluna business.essentials_settings ausente — rode migrate Modules/Essentials.');
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->admin = $user;

    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->tenant->id, 'guard_name' => 'web'],
        ['business_id' => $this->tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role->name);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
    $this->actingAs($user);
});

function esetGravado(int $bizId): ?string
{
    return DB::table('business')->where('id', $bizId)->value('essentials_settings');
}

it('UC-ESET-01 · o admin abre a tela e recebe as 5 chaves vivas, e só elas', function () {
    $resposta = $this->get('/hrm/settings');
    $resposta->assertOk();

    $page = $resposta->viewData('page');
    expect($page['component'])->toBe('Essentials/Settings/Index');

    $chaves = array_keys($page['props']['settings']);
    sort($chaves);
    expect($chaves)->toBe([
        'calculate_sales_target_commission_without_tax',
        'essentials_todos_prefix',
        'leave_instructions',
        'leave_ref_no_prefix',
        'payroll_ref_no_prefix',
    ]);
    expect($page['props']['settings']['calculate_sales_target_commission_without_tax'])->toBeBool();
});

it('UC-ESET-02 · salvar grava no JSON do meu business e não toca o de outro', function () {
    $alheioAntes = esetGravado($this->adversario->id);
    $prefixo = 'E'.substr(uniqid(), -6);

    $this->post('/hrm/settings', [
        'essentials_todos_prefix' => $prefixo,
        'leave_ref_no_prefix' => 'LV',
        'payroll_ref_no_prefix' => 'FP',
        'leave_instructions' => 'Avisar com 7 dias.',
        'calculate_sales_target_commission_without_tax' => true,
    ])->assertRedirect();

    $meu = json_decode((string) esetGravado($this->tenant->id), true);
    // Controle positivo: o save aconteceu no meu tenant.
    expect($meu['essentials_todos_prefix'] ?? null)->toBe($prefixo);
    expect($meu['calculate_sales_target_commission_without_tax'] ?? null)->toBe(1);

    // Tier 0: o JSON do outro business segue byte a byte igual.
    expect(esetGravado($this->adversario->id))->toBe($alheioAntes);
});

it('UC-ESET-03 · quem não é admin recebe 403 ao abrir e ao salvar, e nada é gravado', function () {
    $antes = esetGravado($this->tenant->id);

    $comum = $this->usuarioComPermissoes([], $this->tenant);
    session()->flush();
    $this->actingAs($comum);

    $this->get('/hrm/settings')->assertForbidden();
    $this->post('/hrm/settings', ['essentials_todos_prefix' => 'INVASOR'])->assertForbidden();

    expect(esetGravado($this->tenant->id))->toBe($antes);
});

it('UC-ESET-04 · prefixo acima de 32 caracteres é recusado e nada é gravado', function () {
    $antes = esetGravado($this->tenant->id);

    $this->post('/hrm/settings', ['essentials_todos_prefix' => str_repeat('X', 33)])
        ->assertSessionHasErrors('essentials_todos_prefix');

    expect(esetGravado($this->tenant->id))->toBe($antes);
});
