<?php

declare(strict_types=1);

// Cobre UC-PEDIT-31 (Edit.casos.md) — G-2 rastreabilidade caso-teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Qual Editar abre em /products/{id}/edit: o Blade ou o React (UC-PEDIT-31).
 *
 * Até 2026-11-25 o `ProductController@edit` só olhava o cabeçalho `X-Inertia`: um GET de página
 * inteira (F5 na tela, link direto) servia sempre o Blade, mesmo com a chave `useV2ProdutoCadastro`
 * ligada. Agora o Editar decide com o mesmo resolvedor do Novo (`abreCadastroReact`), então o
 * molde é o `CadastroProdutoChaveReactTest` (UC-PCAD-08).
 *
 * Isolamento: a chave é por empresa e a empresa vem da sessão. O falso do serviço de chaves só
 * liga para UMA empresa; quem é da outra recebe o Blade.
 *
 * Tenant: o seeded do CI, nunca biz=4 (ADR 0358).
 */
uses(DatabaseTransactions::class);

/** Liga a chave só para as empresas listadas (o `business_id` que o controller manda). */
function pedrChave(array $empresasLigadas): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($empresasLigadas) extends \App\Services\FeatureFlagService
    {
        public function __construct(private array $ligadas) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2ProdutoCadastro'
                && in_array((int) ($attrs['business_id'] ?? 0), $this->ligadas, true);
        }
    });
}

function pedrUsuario(int $bizId): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Editar React', 'username' => 'pedr_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    $user->givePermissionTo(Permission::findOrCreate('product.update', 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function pedrLogin(object $test, User $user): object
{
    // O Blade formata números com @num_format, que lê session('currency') (mesmo do UC-PCAD-08).
    session([
        'user.business_id' => (int) $user->business_id, 'user.id' => $user->id, 'business.id' => (int) $user->business_id,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    return $test->actingAs($user);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->produtoId = EstoqueFixture::singleProduct((int) $this->biz->id)->productId;
});

afterEach(function () {
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
});

it('UC-PEDIT-31 · chave desligada: o Editar (página inteira) abre o Blade', function () {
    pedrChave([]);
    $user = pedrUsuario((int) $this->biz->id);

    pedrLogin($this, $user)->get('/products/' . $this->produtoId . '/edit')->assertOk()->assertViewIs('product.edit');
});

it('UC-PEDIT-31 · chave ligada: o Editar (página inteira) abre o React', function () {
    pedrChave([(int) $this->biz->id]);
    $user = pedrUsuario((int) $this->biz->id);

    pedrLogin($this, $user)->get('/products/' . $this->produtoId . '/edit')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Produto/Edit', false)
            ->where('product.id', $this->produtoId));
});

it('UC-PEDIT-31 · chave ligada com ?classico=1: volta ao Blade', function () {
    pedrChave([(int) $this->biz->id]);
    $user = pedrUsuario((int) $this->biz->id);

    pedrLogin($this, $user)->get('/products/' . $this->produtoId . '/edit?classico=1')->assertOk()->assertViewIs('product.edit');
});

it('UC-PEDIT-31 · visita Inertia abre o React mesmo com a chave desligada (regra de antes)', function () {
    pedrChave([]);
    $user = pedrUsuario((int) $this->biz->id);

    pedrLogin($this, $user)->withHeaders([
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ])->get('/products/' . $this->produtoId . '/edit')->assertOk()
        ->assertJsonPath('component', 'Produto/Edit');
});

it('UC-PEDIT-31 · a chave de uma empresa não abre o React para a outra', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par para provar o isolamento.');
    }
    // Ligada só para a empresa seeded; o usuário e o produto são da outra.
    pedrChave([(int) $this->biz->id]);
    $produtoAlheio = EstoqueFixture::singleProduct($outroBizId)->productId;
    $user = pedrUsuario($outroBizId);

    pedrLogin($this, $user)->get('/products/' . $produtoAlheio . '/edit')->assertOk()->assertViewIs('product.edit');
});
