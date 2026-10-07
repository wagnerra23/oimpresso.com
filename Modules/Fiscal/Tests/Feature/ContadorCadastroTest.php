<?php

declare(strict_types=1);

// @covers-us US-NFE-009 — contador da empresa e papel "Contador" (D-CONTADOR caminho 2, thread 15c).
// Contrato da tela: resources/js/Pages/Fiscal/Config.casos.md — UC-FCFG-08 · 09

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Tributacao\ContadorService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Os casos derivam da D-CONTADOR (playbook Fiscal thread 15: "a empresa cadastra o contador — nome,
 * e-mail, CRC — em /fiscal/config"; "papel com nfe.tributacao.manage + nfe.tributacao.aceitar +
 * leitura do cockpit fiscal e do SPED; sem vendas, financeiro, cadastro de cliente ou configuração
 * da empresa"). MySQL-only · tenant 98, vizinho 99 (ADR 0358).
 */

function ctdA(): int
{
    return test()->seededTenant()->id;
}

function ctdB(): int
{
    return test()->seededSupportClientTenant()->id;
}

function ctdLimpar(): void
{
    DB::table('nfe_contadores')->whereIn('business_id', [ctdA(), ctdB()])->delete();
    $papeis = DB::table('roles')->whereIn('name', [ContadorService::nomePapel(ctdA()), ContadorService::nomePapel(ctdB())])->pluck('id');
    DB::table('role_has_permissions')->whereIn('role_id', $papeis)->delete();
    DB::table('model_has_roles')->whereIn('role_id', $papeis)->delete();
    DB::table('roles')->whereIn('id', $papeis)->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function ctdComo(\App\User $u, int $biz): void
{
    test()->actingAs($u)->withSession(['business.id' => $biz, 'user.business_id' => $biz]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: tabela nfe_contadores vem da migração 2026_10_07_000007.');
    }
    if (! Schema::hasTable('nfe_contadores')) {
        $this->markTestSkipped('Migração 2026_10_07_000007 não rodou — rode as migrations do NfeBrasil.');
    }
    ctdLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfe_contadores')) {
        ctdLimpar();
    }
});

// ---------------------------------------------------------------------------------------
// UC-FCFG-08 · O contador é cadastrado por empresa, com o gate da tela  [T0]
// ---------------------------------------------------------------------------------------
it('UC-FCFG-08 · cadastro do contador por empresa, com o gate da tela', function () {
    $semGate = test()->usuarioComPermissoes(['fiscal.access']);
    $editorA = test()->usuarioComPermissoes(['fiscal.config.edit']);
    $editorB = test()->usuarioComPermissoes(['fiscal.config.edit'], test()->seededSupportClientTenant());
    $dados = ['nome' => 'Contadora Teste', 'email' => 'contadora@contador.test', 'crc' => 'SC-000000/O'];

    // Sem fiscal.config.edit → 403, nada gravado.
    ctdComo($semGate, ctdA());
    $this->post('/fiscal/config/contador', $dados)->assertForbidden();
    expect(DB::table('nfe_contadores')->where('business_id', ctdA())->count())->toBe(0);

    // E-mail inválido → erro no campo, nada gravado.
    ctdComo($editorA, ctdA());
    $this->post('/fiscal/config/contador', ['nome' => 'X', 'email' => 'não-é-email'])->assertSessionHasErrors('email');
    expect(DB::table('nfe_contadores')->where('business_id', ctdA())->count())->toBe(0);

    // Cadastro válido → a tela passa a mostrar o contador desta empresa.
    $this->post('/fiscal/config/contador', $dados)->assertSessionHasNoErrors();
    $this->get('/fiscal/config')->assertOk()->assertInertia(fn ($p) => $p
        ->component('Fiscal/Config')
        ->where('contador.email', 'contadora@contador.test')
        ->where('contador.crc', 'SC-000000/O')
        ->where('contador.papel', 'Contador#' . ctdA()));

    // Salvar de novo edita (uma linha por empresa), e CRC vazio vira "sem CRC".
    $this->post('/fiscal/config/contador', ['nome' => 'Outra', 'email' => 'outra@contador.test', 'crc' => ' '])->assertSessionHasNoErrors();
    expect(DB::table('nfe_contadores')->where('business_id', ctdA())->count())->toBe(1)
        ->and(DB::table('nfe_contadores')->where('business_id', ctdA())->value('crc'))->toBeNull();

    // A empresa B não vê o contador da A; o cadastro dela não toca o da A.
    ctdComo($editorB, ctdB());
    $this->get('/fiscal/config')->assertOk()->assertInertia(fn ($p) => $p->where('contador', null));
    $this->post('/fiscal/config/contador', ['nome' => 'B', 'email' => 'b@contador.test'])->assertSessionHasNoErrors();
    expect(DB::table('nfe_contadores')->where('business_id', ctdA())->value('email'))->toBe('outra@contador.test')
        ->and(DB::table('nfe_contadores')->where('business_id', ctdB())->value('email'))->toBe('b@contador.test');
});

// ---------------------------------------------------------------------------------------
// UC-FCFG-09 · O papel "Contador" tem só tributação, aceite, cockpit fiscal e SPED  [T0]
// ---------------------------------------------------------------------------------------
it('UC-FCFG-09 · papel Contador sem venda, financeiro, cliente ou configuração', function () {
    $editorA = test()->usuarioComPermissoes(['fiscal.config.edit']);
    ctdComo($editorA, ctdA());
    $this->post('/fiscal/config/contador', ['nome' => 'Contadora', 'email' => 'c@contador.test'])->assertSessionHasNoErrors();

    $papel = Role::query()->where('name', ContadorService::nomePapel(ctdA()))->where('business_id', ctdA())->firstOrFail();
    $perms = $papel->permissions->pluck('name')->sort()->values()->all();
    $esperado = ContadorService::PERMISSOES_PAPEL;
    sort($esperado);
    expect($perms)->toBe($esperado)
        // O papel é da empresa A; a B não ganha papel por causa disso.
        ->and(Role::query()->where('name', ContadorService::nomePapel(ctdB()))->exists())->toBeFalse();

    // Usuário com o papel: aceita revisão e abre a lista de revisões; não configura a empresa nem vende.
    $u = \App\User::factory()->create(['business_id' => ctdA()]);
    $u->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = \App\User::findOrFail($u->id);
    expect($u->hasPermissionTo('nfe.tributacao.aceitar'))->toBeTrue();
    foreach (['sell.view', 'sell.create', 'customer.view', 'business_settings.access', 'fiscal.config.edit'] as $p) {
        expect($u->can($p))->toBeFalse();
    }
    ctdComo($u, ctdA());
    if (Schema::hasTable('nfe_revisoes_contador')) {
        $this->getJson('/nfe-brasil/tributacao/revisoes')->assertOk();
    }
    $this->get('/fiscal/config')->assertForbidden();

    // CONTROLE POSITIVO — papel já existente não é sobrescrito: o ajuste do administrador fica.
    $papel->revokePermissionTo('fiscal.sped.export');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    ctdComo($editorA, ctdA());
    $this->post('/fiscal/config/contador', ['nome' => 'Contadora', 'email' => 'c2@contador.test'])->assertSessionHasNoErrors();
    expect($papel->fresh()->permissions->pluck('name')->all())->not->toContain('fiscal.sped.export');
});
