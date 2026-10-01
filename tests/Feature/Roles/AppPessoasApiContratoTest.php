<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

use App\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * API de Pessoas do app das lojas (oimpresso-app) — GET /api/app/pessoas e /{id}, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §4 (sem papel Transportadora, D11) e a
 * MESMA regra de "só os próprios" da tela web (ContatosViewOwnTest, #8469) — agora num serviço só
 * (App\Services\Pessoas\PessoaEscopo). NÃO derivado do controller.
 *
 * Tier 0: tenant fictício 98 (ADR 0358) contra o business 2 da lane. Transação revertida.
 */

const APP_PES_BIZ = 98;
const APP_PES_OUTRO = 2;

function appPesUsuario(array $permissoes, int $biz = APP_PES_BIZ): User
{
    $user = User::factory()->create(['business_id' => $biz]);
    $papel = Role::create(['name' => 'AppPes' . uniqid() . '#' . $biz, 'business_id' => $biz, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function appPesContato(string $nome, int $criadoPor, int $biz = APP_PES_BIZ, array $extra = []): int
{
    return (int) DB::table('contacts')->insertGetId(array_merge([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'is_customer' => 1,
        'contact_id' => 'APS' . random_int(10000, 99999), 'mobile' => '00000000000',
        'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

function appPesNomes($teste, string $query): array
{
    return collect($teste->getJson('/api/app/pessoas?' . $query)->assertOk()->json('itens'))->pluck('nome')->all();
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (DB::table('business')->whereIn('id', [APP_PES_BIZ, APP_PES_OUTRO])->count() !== 2
        || ! \Illuminate\Support\Facades\Schema::hasTable('user_contact_access')) {
        $this->markTestSkipped('Tenants 98/2 ou user_contact_access ausentes nesta lane.');
    }
    DB::beginTransaction();
    $this->sufixo = uniqid();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('sem token responde 401; sem permissão de pessoas responde 403', function () {
    $this->getJson('/api/app/pessoas')->assertStatus(401);

    Passport::actingAs(appPesUsuario([]), [], 'api');
    $this->getJson('/api/app/pessoas')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('com customer.view vejo as pessoas do meu business e nunca as de outro', function () {
    $u = appPesUsuario(['customer.view']);
    $minha = "APS Minha {$this->sufixo}";
    $deFora = "APS DeFora {$this->sufixo}";
    appPesContato($minha, $u->id);
    appPesContato($deFora, $u->id, APP_PES_OUTRO);
    Passport::actingAs($u, [], 'api');

    $nomes = appPesNomes($this, 'q=' . $this->sufixo);
    expect($nomes)->toContain($minha);
    expect($nomes)->not->toContain($deFora);
});

it('com só customer.view_own vejo as minhas e as compartilhadas, não as de outro usuário (#8469)', function () {
    $dono = appPesUsuario(['customer.view_own']);
    $outro = (int) User::factory()->create(['business_id' => APP_PES_BIZ])->id;
    $propria = "APS Propria {$this->sufixo}";
    $alheia = "APS Alheia {$this->sufixo}";
    $compart = "APS Compart {$this->sufixo}";
    appPesContato($propria, $dono->id);
    $idAlheia = appPesContato($alheia, $outro);
    $idCompart = appPesContato($compart, $outro);
    DB::table('user_contact_access')->insert(['user_id' => $dono->id, 'contact_id' => $idCompart]);
    Passport::actingAs($dono, [], 'api');

    $nomes = appPesNomes($this, 'q=' . $this->sufixo);
    expect($nomes)->toContain($propria);
    expect($nomes)->toContain($compart);
    expect($nomes)->not->toContain($alheia);

    $this->getJson('/api/app/pessoas/' . $idAlheia)->assertStatus(404);
});

it('papéis: fornecedor aparece no filtro de fornecedores e não no de clientes; sem Transportadora', function () {
    $u = appPesUsuario(['customer.view', 'supplier.view']);
    $forn = "APS Forn {$this->sufixo}";
    appPesContato($forn, $u->id, APP_PES_BIZ, ['type' => 'supplier', 'is_customer' => 0, 'is_supplier' => 1]);
    Passport::actingAs($u, [], 'api');

    expect(appPesNomes($this, 'papel=fornecedores&q=' . $this->sufixo))->toContain($forn);
    expect(appPesNomes($this, 'papel=clientes&q=' . $this->sufixo))->not->toContain($forn);

    $item = collect($this->getJson('/api/app/pessoas?papel=fornecedores&q=' . $this->sufixo)->json('itens'))
        ->firstWhere('nome', $forn);
    expect($item['papeis'])->toBe(['fornecedor']);
    expect(array_keys($this->getJson('/api/app/pessoas')->json('contadores')))
        ->toBe(['todos', 'clientes', 'fornecedores', 'funcionarios', 'em_debito']);
});

it('detalhe traz papéis, contato e kpis da pessoa do meu business', function () {
    $u = appPesUsuario(['customer.view']);
    $id = appPesContato("APS Det {$this->sufixo}", $u->id);
    Passport::actingAs($u, [], 'api');

    $r = $this->getJson('/api/app/pessoas/' . $id)->assertOk();
    expect($r->json('papeis'))->toBe(['cliente']);
    expect($r->json('kpis'))->toHaveKeys(['pedidos', 'ticket_medio', 'saldo_aberto']);
    expect($r->json('pedidos_recentes'))->toBeArray();
});
