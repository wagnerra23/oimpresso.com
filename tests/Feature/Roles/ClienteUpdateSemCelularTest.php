<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

/**
 * Edição web de cliente (Cliente/Edit.tsx → PUT /contacts/{id} → ContactController::update)
 * apagando o campo "Celular".
 *
 * Hipótese medida aqui: o Edit manda `mobile: ''`, o ConvertEmptyStringsToNull transforma em
 * null, a UpdateContactRequest aceita null e ContactUtil::updateContact atribui o null ao
 * Model e salva → `contacts.mobile` é NOT NULL, o UPDATE cai (1048) e o catch devolve só
 * "Algo deu errado". É o mesmo caminho que quebrava o store() (run 37035885785).
 *
 * Controle POSITIVO: a mesma requisição trocando o celular por outro número grava — sem ele,
 * um vermelho no caso vazio poderia ser permissão/assinatura/validação, não o celular.
 *
 * Tenant fictício 98 (ADR 0358). Transação revertida por caso. A assinatura é forçada por
 * mock parcial do ModuleUtil: o que se mede aqui é a gravação, não o pacote do tenant.
 */

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const CSU_BIZ = 98;

function csuUsuario(): User
{
    $user = User::factory()->create(['business_id' => CSU_BIZ]);
    $papel = Role::create(['name' => 'CsuTeste' . uniqid() . '#' . CSU_BIZ, 'business_id' => CSU_BIZ, 'guard_name' => 'web']);
    Permission::findOrCreate('customer.update', 'web');
    $papel->syncPermissions(['customer.update']);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** Mesmo payload que o useForm de Cliente/Edit.tsx manda (campos vazios = ''). */
function csuPayload(string $nome, string $celular): array
{
    return [
        'type' => 'customer', 'contact_type_radio' => 'person',
        'first_name' => $nome, 'middle_name' => '', 'last_name' => '',
        'supplier_business_name' => '', 'tax_number' => '', 'mobile' => $celular,
        'landline' => '', 'email' => '', 'address_line_1' => '', 'city' => '', 'state' => '',
        'zip_code' => '', 'shipping_address' => '', 'customer_group_id' => '',
        'opening_balance' => '0', 'credit_limit' => '', 'cpf_cnpj' => '', 'rg' => '',
        'inscricao_estadual' => '', 'inscricao_municipal' => '', 'indicador_ie' => '',
        'nome_fantasia' => '', 'consumidor_final' => false, 'contribuinte' => true,
        'regime' => '', 'suframa' => '',
    ];
}

function csuEditar($test, int $id, array $payload)
{
    $manifest = public_path('build-inertia/manifest.json');

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->put("/contacts/{$id}", $payload);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', CSU_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();

    $mod = Mockery::mock(ModuleUtil::class)->makePartial();
    $mod->shouldReceive('isSubscribed')->andReturn(true);
    $this->instance(ModuleUtil::class, $mod);

    $this->logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) {
        $this->logs[] = $e->level . ': ' . $e->message;
    });

    $usuario = csuUsuario();
    $this->actingAs($usuario);
    session(['user.business_id' => CSU_BIZ, 'business.id' => CSU_BIZ, 'user.id' => $usuario->id]);

    $this->nome = 'CSU Cliente ' . uniqid();
    $this->contatoId = (int) DB::table('contacts')->insertGetId([
        'business_id' => CSU_BIZ, 'type' => 'customer', 'name' => $this->nome, 'is_customer' => 1,
        'contact_id' => 'CSU' . random_int(10000, 99999), 'mobile' => '48999990000',
        'created_by' => $usuario->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('POSITIVO: trocar o celular por outro número grava a edição', function () {
    csuEditar($this, $this->contatoId, csuPayload($this->nome, '48988887777'))->assertRedirect();

    $this->assertSame(
        '48988887777',
        DB::table('contacts')->where('id', $this->contatoId)->value('mobile'),
        'Controle positivo não gravou a edição — o caminho quebra antes do celular. Logs: ' . implode(' | ', $this->logs),
    );
});

it('MEDIÇÃO: apagar o celular grava a edição com celular vazio', function () {
    csuEditar($this, $this->contatoId, csuPayload($this->nome, ''))->assertRedirect();

    $this->assertSame(
        '',
        DB::table('contacts')->where('id', $this->contatoId)->value('mobile'),
        'Apagar o celular NÃO gravou a edição. Logs: ' . implode(' | ', $this->logs),
    );
});
