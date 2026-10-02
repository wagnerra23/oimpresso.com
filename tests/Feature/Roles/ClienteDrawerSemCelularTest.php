<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

/**
 * Drawer de cliente (Modules/Crm ClienteAutosaveController) sem celular.
 *
 * Dois caminhos, mesma coluna: `contacts.mobile` é NOT NULL (database/schema/mysql-schema.sql).
 *   - POST /cliente/draft: o botão "Novo cliente" cria o placeholder SEM mandar `mobile`.
 *   - PATCH /cliente/{id}/contato: o autosave da aba Contato manda `mobile: ''` quando o
 *     telefone é apagado; ConvertEmptyStringsToNull vira null e updateAndRespond() só trata
 *     UniqueConstraintViolation — um 1048 sairia como 500.
 * É o mesmo defeito que quebrava o cadastro web (run 37035885785).
 *
 * Controle POSITIVO: o autosave trocando o celular por outro número grava. Sem ele, um
 * vermelho no caso vazio poderia ser rota/permissão/sessão, não o celular.
 *
 * Tenant fictício 98 (ADR 0358). Transação revertida por caso.
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const CDC_BIZ = 98;

function cdcUsuario(): User
{
    $user = User::factory()->create(['business_id' => CDC_BIZ]);
    $papel = Role::create(['name' => 'CdcTeste' . uniqid() . '#' . CDC_BIZ, 'business_id' => CDC_BIZ, 'guard_name' => 'web']);
    foreach (['customer.create', 'customer.update'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions(['customer.create', 'customer.update']);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** O drawer chama por fetch/axios: JSON, sem X-Inertia. */
function cdcHeaders(): array
{
    return ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', CDC_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();

    $usuario = cdcUsuario();
    $this->actingAs($usuario);
    session(['user.business_id' => CDC_BIZ, 'business.id' => CDC_BIZ, 'user.id' => $usuario->id]);

    $this->contatoId = (int) DB::table('contacts')->insertGetId([
        'business_id' => CDC_BIZ, 'type' => 'customer', 'name' => 'CDC Cliente ' . uniqid(), 'is_customer' => 1,
        'contact_id' => 'CDC' . random_int(10000, 99999), 'mobile' => '48999990000',
        'created_by' => $usuario->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('MEDIÇÃO: o rascunho de novo cliente (POST /cliente/draft) é criado sem celular', function () {
    $r = $this->withHeaders(cdcHeaders())->postJson('/cliente/draft', ['type' => 'customer']);

    $this->assertSame(201, $r->getStatusCode(), 'Rascunho sem celular não foi criado. Corpo: ' . mb_substr((string) $r->getContent(), 0, 400));
    $mobile = DB::table('contacts')->where('id', (int) $r->json('id'))->where('business_id', CDC_BIZ)->value('mobile');
    $this->assertSame('', $mobile, 'O rascunho deve existir com celular vazio.');
});

it('POSITIVO: o autosave da aba Contato troca o celular por outro número', function () {
    $r = $this->withHeaders(cdcHeaders())->patchJson("/cliente/{$this->contatoId}/contato", ['mobile' => '48988887777']);

    $this->assertSame(200, $r->getStatusCode(), 'Controle positivo falhou — o caminho quebra antes do celular. Corpo: ' . mb_substr((string) $r->getContent(), 0, 400));
    $this->assertSame('48988887777', DB::table('contacts')->where('id', $this->contatoId)->value('mobile'));
});

it('MEDIÇÃO: o autosave da aba Contato aceita apagar o celular', function () {
    $r = $this->withHeaders(cdcHeaders())->patchJson("/cliente/{$this->contatoId}/contato", ['mobile' => '']);

    $this->assertSame(200, $r->getStatusCode(), 'Apagar o celular no drawer falhou. Corpo: ' . mb_substr((string) $r->getContent(), 0, 400));
    $this->assertSame('', DB::table('contacts')->where('id', $this->contatoId)->value('mobile'), 'Celular apagado deve ficar vazio.');
});

it('MEDIÇÃO: o autosave com a chave PT-BR do front (tel) aceita apagar o celular', function () {
    $r = $this->withHeaders(cdcHeaders())->patchJson("/cliente/{$this->contatoId}/contato", ['tel' => '']);

    $this->assertSame(200, $r->getStatusCode(), 'Apagar o telefone pela chave tel falhou. Corpo: ' . mb_substr((string) $r->getContent(), 0, 400));
    $this->assertSame('', DB::table('contacts')->where('id', $this->contatoId)->value('mobile'), 'Celular apagado deve ficar vazio.');
});
