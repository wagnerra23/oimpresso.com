<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui.

/**
 * /contacts (React · Cliente/Index) respeita `customer.view_own` / `supplier.view_own` na LISTA.
 *
 * O QUE ESTAVA ERRADO (achado do MAPA-DE-DADOS-v1 do app Mobile, item D8, 2026-10-01):
 * ContactController::buildClienteIndexCustomers montava a lista só com `business_id` + papel.
 * Quem tem apenas `customer.view_own` passava pelo gate do index() e recebia TODAS as pessoas
 * da empresa — nome, celular, e-mail, documento. O detalhe (show()) já aplicava a regra; a lista
 * não. Não cruzava tenant, mas furava a permissão dentro dele, e o app Mobile vai levar essa
 * lista ao celular.
 *
 * CONTRATO (fonte = o próprio ERP, que já define "própria" em dois lugares):
 *   - app/Contact.php scopeOnlyCustomers/scopeOnlySuppliers/scopeOnlyOwnContact;
 *   - ContactController::show(): created_by == eu  OU  contato liberado em user_contact_access.
 * Quem tem `customer.view` (ou `supplier.view`) vê tudo — controle POSITIVO abaixo, sem ele um
 * filtro que escondesse tudo passaria nos negativos.
 *
 * Headers reais do @inertiajs/core (§5 2026-09-08). Tenant fictício 98 (ADR 0358), nunca biz=4.
 * Transação revertida por caso.
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const CVO_BIZ = 98;

function cvoUsuario(array $permissoes, int $biz = CVO_BIZ): User
{
    $user = User::factory()->create(['business_id' => $biz]);
    if ($permissoes !== []) {
        $papel = Role::create(['name' => 'CvoTeste' . uniqid() . '#' . $biz, 'business_id' => $biz, 'guard_name' => 'web']);
        foreach ($permissoes as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $papel->syncPermissions($permissoes);
        $user->assignRole($papel);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function cvoContato(int $biz, string $nome, int $criadoPor, string $papel = 'customer'): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => $papel, 'name' => $nome,
        'contact_id' => 'CVO' . random_int(100000, 999999), 'mobile' => '00000000000',
        'created_by' => $criadoPor,
        'is_customer' => $papel === 'customer' ? 1 : 0,
        'is_supplier' => $papel === 'supplier' ? 1 : 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Carga parcial da prop `customers`, com os headers que o navegador manda. */
function cvoPedirLista($test, string $type = 'customer')
{
    $manifest = public_path('build-inertia/manifest.json');
    $versao = file_exists($manifest) ? md5_file($manifest) : '1';

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index',
        'X-Inertia-Partial-Data' => 'customers',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/contacts?type=' . $type . '&per_page=100');
}

function cvoEntrar($test, User $u): void
{
    $test->actingAs($u);
    session(['user.business_id' => (int) $u->business_id, 'business.id' => (int) $u->business_id]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', CVO_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
    config()->set('mwart.cliente_index.enabled', true);
    config()->set('mwart.cliente_index.business_ids', []);

    $tag = uniqid();
    $this->outro = cvoUsuario([]);
    $this->nomeMeu = "CVO Meu {$tag}";
    $this->nomeAlheio = "CVO Alheio {$tag}";
    $this->nomeLiberado = "CVO Liberado {$tag}";
    $this->tag = $tag;
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('NEGATIVO: com só customer.view_own, a lista NÃO traz cliente criado por outro usuário', function () {
    $eu = cvoUsuario(['customer.view_own']);
    expect($eu->can('customer.view'))->toBeFalse();
    cvoContato(CVO_BIZ, $this->nomeMeu, (int) $eu->id);
    cvoContato(CVO_BIZ, $this->nomeAlheio, (int) $this->outro->id);
    cvoEntrar($this, $eu);

    $r = cvoPedirLista($this);

    expect($r->getStatusCode())->toBe(200);
    // Âncora positiva: a requisição chegou ao builder da lista (o meu aparece).
    expect($r->getContent())->toContain($this->nomeMeu);
    expect(str_contains($r->getContent(), $this->nomeAlheio))->toBeFalse();
});

it('POSITIVO: com só customer.view_own, cliente liberado em user_contact_access aparece', function () {
    $eu = cvoUsuario(['customer.view_own']);
    $id = cvoContato(CVO_BIZ, $this->nomeLiberado, (int) $this->outro->id);
    DB::table('user_contact_access')->insert(['user_id' => $eu->id, 'contact_id' => $id]);
    cvoEntrar($this, $eu);

    $r = cvoPedirLista($this);

    expect($r->getStatusCode())->toBe(200);
    expect($r->getContent())->toContain($this->nomeLiberado);
});

it('CONTROLE: com customer.view a mesma lista traz o cliente de outro usuário', function () {
    $eu = cvoUsuario(['customer.view']);
    cvoContato(CVO_BIZ, $this->nomeAlheio, (int) $this->outro->id);
    cvoEntrar($this, $eu);

    $r = cvoPedirLista($this);

    expect($r->getStatusCode())->toBe(200);
    expect($r->getContent())->toContain($this->nomeAlheio);
});

it('NEGATIVO: com só supplier.view_own, a aba Fornecedores NÃO traz fornecedor de outro usuário', function () {
    $eu = cvoUsuario(['supplier.view_own']);
    cvoContato(CVO_BIZ, $this->nomeMeu, (int) $eu->id, 'supplier');
    cvoContato(CVO_BIZ, $this->nomeAlheio, (int) $this->outro->id, 'supplier');
    cvoEntrar($this, $eu);

    $r = cvoPedirLista($this, 'supplier');

    expect($r->getStatusCode())->toBe(200);
    expect($r->getContent())->toContain($this->nomeMeu);
    expect(str_contains($r->getContent(), $this->nomeAlheio))->toBeFalse();
});

it('NEGATIVO: aba "todos" com só customer.view_own também filtra', function () {
    $eu = cvoUsuario(['customer.view_own']);
    cvoContato(CVO_BIZ, $this->nomeMeu, (int) $eu->id);
    cvoContato(CVO_BIZ, $this->nomeAlheio, (int) $this->outro->id);
    cvoEntrar($this, $eu);

    $r = cvoPedirLista($this, 'all');

    expect($r->getStatusCode())->toBe(200);
    expect($r->getContent())->toContain($this->nomeMeu);
    expect(str_contains($r->getContent(), $this->nomeAlheio))->toBeFalse();
});

it('CROSS-TENANT: contato de outra empresa nunca aparece, mesmo criado pelo próprio usuário', function () {
    $outraBiz = (int) DB::table('business')->where('id', '!=', CVO_BIZ)->where('id', '!=', 4)->value('id');
    if ($outraBiz === 0) {
        $this->markTestSkipped('Lane sem segunda empresa para o caso cross-tenant.');
    }
    $eu = cvoUsuario(['customer.view_own']);
    cvoContato(CVO_BIZ, $this->nomeMeu, (int) $eu->id);
    cvoContato($outraBiz, "CVO OutraEmpresa {$this->tag}", (int) $eu->id);
    cvoEntrar($this, $eu);

    $r = cvoPedirLista($this);

    expect($r->getStatusCode())->toBe(200);
    expect($r->getContent())->toContain($this->nomeMeu);
    expect(str_contains($r->getContent(), "CVO OutraEmpresa {$this->tag}"))->toBeFalse();
});
