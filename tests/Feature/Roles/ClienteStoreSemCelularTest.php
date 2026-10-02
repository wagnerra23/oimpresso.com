<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

/**
 * Cadastro web de cliente (Cliente/Create.tsx → POST /contacts → ContactController::store)
 * com o campo "Celular" vazio.
 *
 * Hipótese medida aqui: `contacts.mobile` é NOT NULL (database/schema/mysql-schema.sql), o
 * formulário manda `mobile: ''`, o middleware ConvertEmptyStringsToNull o transforma em null,
 * a StoreContactRequest aceita null e ContactUtil::createNewContact grava null → o INSERT cai
 * (MySQL 1048), o catch do store() engole a exceção e devolve o erro genérico. Na API do app
 * o mesmo caminho deu 500 (run 37033539499 da lane acessos-pest).
 *
 * Controle POSITIVO: a mesma requisição com celular preenchido cria o contato — sem ele, um
 * vermelho no caso vazio poderia ser assinatura/permissão/validação, não o celular.
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

const CSC_BIZ = 98;

function cscUsuario(): User
{
    $user = User::factory()->create(['business_id' => CSC_BIZ]);
    $papel = Role::create(['name' => 'CscTeste' . uniqid() . '#' . CSC_BIZ, 'business_id' => CSC_BIZ, 'guard_name' => 'web']);
    Permission::findOrCreate('customer.create', 'web');
    $papel->syncPermissions(['customer.create']);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** Mesmo payload que o useForm de Cliente/Create.tsx manda (campos vazios = ''). */
function cscPayload(string $nome, string $celular): array
{
    return [
        'type' => 'customer', 'contact_type_radio' => 'person', 'prefix' => '',
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

function cscPostar($test, array $payload)
{
    $manifest = public_path('build-inertia/manifest.json');

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->post('/contacts', $payload);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', CSC_BIZ)->exists()) {
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

    $this->actingAs(cscUsuario());
    session(['user.business_id' => CSC_BIZ, 'business.id' => CSC_BIZ, 'user.id' => auth()->id()]);
    $this->sufixo = uniqid();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('POSITIVO: com celular preenchido o cadastro web cria o contato no tenant', function () {
    $nome = "CSC Com Celular {$this->sufixo}";

    cscPostar($this, cscPayload($nome, '48999990000'))->assertRedirect();

    $this->assertTrue(
        DB::table('contacts')->where('business_id', CSC_BIZ)->where('name', $nome)->exists(),
        'Controle positivo não criou o contato — o caminho quebra antes do celular. Logs: ' . implode(' | ', $this->logs),
    );
});

it('MEDIÇÃO: com celular vazio o cadastro web cria o contato', function () {
    $nome = "CSC Sem Celular {$this->sufixo}";

    cscPostar($this, cscPayload($nome, ''))->assertRedirect();

    $this->assertTrue(
        DB::table('contacts')->where('business_id', CSC_BIZ)->where('name', $nome)->exists(),
        'Celular vazio NÃO criou o contato. Logs: ' . implode(' | ', $this->logs),
    );
});
