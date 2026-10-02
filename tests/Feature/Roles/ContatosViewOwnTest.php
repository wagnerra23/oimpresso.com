<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui.

/**
 * /contacts (React · Cliente/Index): quem tem só `customer.view_own` vê só os PRÓPRIOS contatos.
 *
 * Regra do caminho antigo (Contact::scopeOnlyCustomers / scopeOnlySuppliers / scopeOnlyOwnContact):
 * sem `X.view` mas com `X.view_own`, a lista se restringe a `contacts.created_by = usuário` OU
 * contato compartilhado em `user_contact_access`. O caminho React (buildClienteIndexCustomers,
 * buildClienteIndexKpis, buildClienteIndexTabCounts) não aplicava a regra: quem tinha só
 * view_own via a lista inteira da própria empresa (achado 2026-10-01, MAPA-DE-DADOS-v1 D8;
 * não cruza tenant).
 *
 * Os headers são os do @inertiajs/core de verdade (carga parcial da prop, X-Requested-With).
 * Controle POSITIVO: com `customer.view` a mesma requisição traz os três contatos.
 *
 * Tenant fictício 98 (ADR 0358). Transação revertida por caso.
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const CVO_BIZ = 98;

function cvoUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => CVO_BIZ]);
    $papel = Role::create(['name' => 'CvoTeste' . uniqid() . '#' . CVO_BIZ, 'business_id' => CVO_BIZ, 'guard_name' => 'web']);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function cvoContato(string $nome, int $criadoPor): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => CVO_BIZ, 'type' => 'customer', 'name' => $nome, 'is_customer' => 1,
        'contact_id' => 'CVO' . random_int(10000, 99999), 'mobile' => '00000000000',
        'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Carga parcial de uma prop de Cliente/Index, com os headers que o navegador manda. */
function cvoPedir($test, string $prop, string $busca = '')
{
    $manifest = public_path('build-inertia/manifest.json');

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index',
        'X-Inertia-Partial-Data' => $prop,
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/contacts?type=customer' . ($busca !== '' ? '&q=' . urlencode($busca) : ''));
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', CVO_BIZ)->exists() || ! \Illuminate\Support\Facades\Schema::hasTable('user_contact_access')) {
        $this->markTestSkipped('Tenant fictício 98 ou user_contact_access ausente nesta lane.');
    }
    DB::beginTransaction();
    config()->set('mwart.cliente_index.enabled', true);
    config()->set('mwart.cliente_index.business_ids', []);

    $this->dono = cvoUsuario(['customer.view_own']);
    $outro = (int) User::factory()->create(['business_id' => CVO_BIZ])->id;
    $sufixo = uniqid();
    $this->sufixo = $sufixo;
    $this->nomeProprio = "CVO Proprio {$sufixo}";
    $this->nomeAlheio = "CVO Alheio {$sufixo}";
    $this->nomeCompartilhado = "CVO Compartilhado {$sufixo}";
    cvoContato($this->nomeProprio, $this->dono->id);
    cvoContato($this->nomeAlheio, $outro);
    $compartilhado = cvoContato($this->nomeCompartilhado, $outro);
    DB::table('user_contact_access')->insert(['user_id' => $this->dono->id, 'contact_id' => $compartilhado]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('NEGATIVO: com só customer.view_own a lista NÃO traz contato de outro usuário', function () {
    expect($this->dono->can('customer.view'))->toBeFalse();
    $this->actingAs($this->dono);
    session(['user.business_id' => CVO_BIZ, 'business.id' => CVO_BIZ]);

    $corpo = cvoPedir($this, 'customers', $this->sufixo)->assertOk()->getContent();

    expect($corpo)->toContain($this->nomeProprio);
    expect($corpo)->toContain($this->nomeCompartilhado);
    expect($corpo)->not->toContain($this->nomeAlheio);
});

it('NEGATIVO: com só customer.view_own os contadores das abas não contam contato alheio', function () {
    $this->actingAs($this->dono);
    session(['user.business_id' => CVO_BIZ, 'business.id' => CVO_BIZ]);

    $todos = DB::table('contacts')->where('business_id', CVO_BIZ)->where('is_customer', 1)->count();
    $r = cvoPedir($this, 'tab_counts')->assertOk();
    $contagem = (int) ($r->json('props.tab_counts.customer') ?? -1);

    // O dono vê os dele + os compartilhados com ele: exatamente 2 dos 3 contatos deste teste,
    // e nunca o total de clientes do tenant (que inclui o alheio).
    expect($contagem)->toBe(2);
    expect($contagem)->toBeLessThan($todos);
});

it('POSITIVO: com customer.view a lista traz os três contatos', function () {
    $u = cvoUsuario(['customer.view']);
    $this->actingAs($u);
    session(['user.business_id' => CVO_BIZ, 'business.id' => CVO_BIZ]);

    $corpo = cvoPedir($this, 'customers', $this->sufixo)->assertOk()->getContent();

    expect($corpo)->toContain($this->nomeProprio);
    expect($corpo)->toContain($this->nomeAlheio);
    expect($corpo)->toContain($this->nomeCompartilhado);
});
