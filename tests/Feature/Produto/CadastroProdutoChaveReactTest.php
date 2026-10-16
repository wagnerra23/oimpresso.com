<?php

declare(strict_types=1);

// Cobre UC-PCAD-08 (Create.casos.md) — G-2 rastreabilidade caso-teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Qual cadastro abre em /products/create: o Blade ou o React (UC-PCAD-08).
 *
 * Decisão [F] 2026-10-16: o "Novo produto" da /products passa a abrir o cadastro React. A lista
 * navega com página inteira (Index.tsx, `window.location.assign`), então a escolha mora no
 * `ProductController@create`, atrás da chave por empresa `useV2ProdutoCadastro` (default OFF).
 * Desligada, o Blade segue sendo o cadastro — o da ROTA LIVRE não muda até alguém ligar a chave
 * para ela, e isso só depois do preço no formulário React (Create.casos.md §Pendência de CONTRATO).
 *
 * Tenant: o seeded do CI, nunca biz=4 (ADR 0358).
 */
uses(DatabaseTransactions::class);

function pcadrChave(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2ProdutoCadastro' && $this->ligada;
        }
    });
}

function pcadrUsuario(int $bizId): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Cadastro React', 'username' => 'pcadr_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    $user->givePermissionTo(Permission::findOrCreate('product.create', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function pcadrLogin(object $test, User $user): object
{
    // O cadastro Blade formata a margem com @num_format, que lê session('currency'): o
    // SetSessionData do login real preenche (mesmo conserto do ContasContratoTest).
    session([
        'user.business_id' => (int) $user->business_id, 'user.id' => $user->id, 'business.id' => (int) $user->business_id,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    return $test->actingAs($user);
}

function pcadrVisitaInertia(): array
{
    return [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
});

afterEach(function () {
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
});

it('UC-PCAD-08 · chave desligada: o Novo produto (página inteira) abre o cadastro Blade', function () {
    pcadrChave(false);
    $user = pcadrUsuario((int) $this->biz->id);

    pcadrLogin($this, $user)->get('/products/create')->assertOk()->assertViewIs('product.create');
});

it('UC-PCAD-08 · chave ligada: o Novo produto (página inteira) abre o cadastro React', function () {
    pcadrChave(true);
    $user = pcadrUsuario((int) $this->biz->id);

    pcadrLogin($this, $user)->get('/products/create')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/Create', false));
});

it('UC-PCAD-08 · chave ligada com ?classico=1: volta ao cadastro Blade', function () {
    pcadrChave(true);
    $user = pcadrUsuario((int) $this->biz->id);

    pcadrLogin($this, $user)->get('/products/create?classico=1')->assertOk()->assertViewIs('product.create');
});

it('UC-PCAD-08 · visita Inertia abre o React mesmo com a chave desligada (regra de antes)', function () {
    pcadrChave(false);
    $user = pcadrUsuario((int) $this->biz->id);

    // A visita Inertia volta JSON da página; o contrato é o componente (molde: ProdutoBulkEditContratoTest).
    pcadrLogin($this, $user)->withHeaders(pcadrVisitaInertia())->get('/products/create')->assertOk()
        ->assertJsonPath('component', 'Produto/Create');
});
