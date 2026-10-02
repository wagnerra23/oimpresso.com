<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * API de Nova pessoa do app das lojas (tela 09) — POST /api/app/pessoas.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §4.2. Papéis só cliente/fornecedor (D11);
 * sem saldo inicial nem limite de crédito (valor fica no ERP web). Permissão por papel pedido,
 * como na web (customer.create / supplier.create). NÃO derivado do controller.
 *
 * Tier 0: a pessoa nasce no business do usuário do token (fictício 98, ADR 0358), mesmo que o
 * corpo traga outro business_id. Transação revertida.
 */

const APP_NOVA_BIZ = 98;
const APP_NOVA_OUTRO = 2;

function appNovaUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => APP_NOVA_BIZ]);
    $papel = Role::create(['name' => 'AppNova' . uniqid() . '#' . APP_NOVA_BIZ, 'business_id' => APP_NOVA_BIZ, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/** CPF fictício com dígitos verificadores válidos, gerado aqui (nenhum CPF real no repositório). */
function appNovaCpf(): string
{
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[] = random_int(0, 9);
    }
    foreach ([10, 11] as $peso) {
        $soma = 0;
        foreach ($n as $i => $d) {
            $soma += $d * ($peso - $i);
        }
        $r = ($soma * 10) % 11;
        $n[] = $r === 10 ? 0 : $r;
    }

    return implode('', $n);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_NOVA_BIZ, APP_NOVA_OUTRO])->count() !== 2) {
        $this->markTestSkipped('Tenants 98/2 ausentes nesta lane.');
    }
    // Assinatura ativa sem depender do pacote semeado na lane; o resto do ModuleUtil é o real.
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('isSubscribed')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);

    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('cria cliente PF no business do token com endereço, prazo e consentimento', function () {
    $cpf = appNovaCpf();
    $u = appNovaUsuario(['customer.create', 'customer.view']);
    Passport::actingAs($u, [], 'api');

    $r = $this->postJson('/api/app/pessoas', [
        'business_id' => APP_NOVA_OUTRO,
        'tipo' => 'PF', 'nome' => 'Ana Teste App', 'documento' => $cpf, 'indicador_ie' => 9,
        'papeis' => ['cliente'], 'telefone' => '48999990000', 'email' => 'ana@example.test',
        'email_nfe' => 'nfe@example.test', 'cep' => '88000000', 'logradouro' => 'Rua A',
        'numero' => '10', 'complemento' => 'sala 2', 'bairro' => 'Centro', 'cidade' => 'Florianópolis',
        'uf' => 'SC', 'codigo_ibge' => '4205407', 'prazo_padrao_dias' => 28,
        'consentimento' => ['whatsapp' => true, 'email_nfe' => false],
    ])->assertStatus(201);

    $id = (int) $r->json('id');
    $c = DB::table('contacts')->where('id', $id)->first();
    expect((int) $c->business_id)->toBe(APP_NOVA_BIZ);
    expect($c->name)->toBe('Ana Teste App');
    expect($c->cpf_cnpj)->toBe($cpf);
    expect((int) $c->is_customer)->toBe(1);
    expect((int) $c->is_supplier)->toBe(0);
    expect($c->type)->toBe('customer');
    expect($c->tipo)->toBe('PF');
    expect($c->address_line_1)->toBe('Rua A');
    expect($c->numero)->toBe('10');
    expect($c->neighborhood)->toBe('Centro');
    expect($c->city_code)->toBe('4205407');
    expect($c->email_nfe)->toBe('nfe@example.test');
    expect((int) $c->pay_term_number)->toBe(28);
    expect($c->pay_term_type)->toBe('days');
    expect((int) $c->whatsapp_consent)->toBe(1);
    expect((int) $c->email_consent)->toBe(0);
    expect($c->consent_updated_at)->not->toBeNull();
    expect($c->contact_id)->not->toBeEmpty();

    $this->getJson("/api/app/pessoas/{$id}")->assertOk()->assertJsonPath('nome', 'Ana Teste App');
});

it('PJ cliente e fornecedor vira type both com razão social', function () {
    $u = appNovaUsuario(['customer.create', 'supplier.create']);
    Passport::actingAs($u, [], 'api');

    $id = (int) $this->postJson('/api/app/pessoas', [
        'tipo' => 'PJ', 'nome' => 'Gráfica Teste Ltda', 'papeis' => ['cliente', 'fornecedor'],
    ])->assertStatus(201)->json('id');

    $c = DB::table('contacts')->where('id', $id)->first();
    expect($c->type)->toBe('both');
    expect($c->supplier_business_name)->toBe('Gráfica Teste Ltda');
    expect($c->contact_type)->toBe('business');
    expect($c->consent_updated_at)->toBeNull();
});

it('validação: nome, papéis, CPF e e-mail respondem 422 com o campo; nada é gravado', function () {
    $u = appNovaUsuario(['customer.create']);
    Passport::actingAs($u, [], 'api');
    $antes = DB::table('contacts')->where('business_id', APP_NOVA_BIZ)->count();

    $r = $this->postJson('/api/app/pessoas', [
        'tipo' => 'PF', 'papeis' => [], 'documento' => '11111111112', 'email' => 'nao-e-email', // pii-allowlist: dígitos repetidos, inválido de propósito
    ])->assertStatus(422)->assertJsonPath('erro', 'validacao');

    expect(array_keys($r->json('campos')))->toContain('nome', 'papeis', 'documento', 'email');
    expect(DB::table('contacts')->where('business_id', APP_NOVA_BIZ)->count())->toBe($antes);
});

it('papel sem a permissão de criar responde 403 e não grava; com a permissão grava (controle)', function () {
    $u = appNovaUsuario(['customer.create']);
    Passport::actingAs($u, [], 'api');
    $antes = DB::table('contacts')->where('business_id', APP_NOVA_BIZ)->count();

    $this->postJson('/api/app/pessoas', ['tipo' => 'PJ', 'nome' => 'Fornecedor X', 'papeis' => ['fornecedor']])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(DB::table('contacts')->where('business_id', APP_NOVA_BIZ)->count())->toBe($antes);

    $this->postJson('/api/app/pessoas', ['tipo' => 'PF', 'nome' => 'Cliente Y', 'papeis' => ['cliente']])
        ->assertStatus(201);
    expect(DB::table('contacts')->where('business_id', APP_NOVA_BIZ)->count())->toBe($antes + 1);
});

it('funcionário não é papel aceito pelo app', function () {
    $u = appNovaUsuario(['customer.create']);
    Passport::actingAs($u, [], 'api');

    $r = $this->postJson('/api/app/pessoas', ['tipo' => 'PF', 'nome' => 'Z', 'papeis' => ['funcionario']])
        ->assertStatus(422);
    expect(array_keys($r->json('campos')))->toContain('papeis.0');
});
