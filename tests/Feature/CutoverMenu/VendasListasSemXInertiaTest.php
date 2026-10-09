<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * Cutover pelo menu · thread 02 — Rascunhos, Cotações e Assinaturas abrem a Page React
 * por padrão (playbook `cutover-menu`, D1 [W] 2026-10-07: "por tela: React vira padrão,
 * Blade só com ?classico=1"). Molde: `UnitController@index`.
 *
 * O menu navega com carga cheia (`<a href>`, sem `X-Inertia`). Antes, estas 3 telas só
 * devolviam a Page com o cabeçalho, então pelo menu caíam sempre no Blade.
 *
 * Por tela, os 3 caminhos do índice:
 *   GET comum         → Page React
 *   ?classico=1       → Blade
 *   AJAX sem X-Inertia → o que o fetch/DataTable já lia (JSON em Assinaturas)
 * mais a visita Inertia real (X-Inertia **e** X-Requested-With — §5 2026-09-08).
 *
 * E os dois endereços que a Page lê, porque virar padrão uma tela que lista vazio é
 * correção inerte (LC-30): Rascunhos lia `/sells/drafts` (HTML) e Assinaturas mandava o
 * parar/retomar pra `/sells/recurring-toggle` (rota inexistente).
 *
 * Tenant 98 (ADR 0358). Nunca biz=4. Pest só no CI / CT 100 (proibicoes.md §Ambiente).
 */
uses(DatabaseTransactions::class);

function vlsUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'VLS Cutover',
        'username' => 'vls_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function vlsLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);

    return $test->actingAs($user);
}

function vlsVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** As 3 telas: rota, componente, Blade e as permissões que abrem cada uma. */
function vlsTelas(): array
{
    return [
        'rascunhos' => ['/sells/drafts', 'Sells/Drafts', 'sale_pos.draft', ['draft.view_all', 'access_all_locations']],
        'cotações' => ['/sells/quotations', 'Sells/Quotations', 'sale_pos.quotations', ['quotation.view_all', 'access_all_locations']],
        'assinaturas' => ['/sells/subscriptions', 'Sells/Subscriptions', 'sale_pos.subscriptions', ['sell.view', 'access_all_locations']],
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'business', 'business_locations', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
});

it('GET comum (como o menu navega) abre a Page React das 3 telas', function () {
    foreach (vlsTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = vlsLogin($this, vlsUsuario($this->bizId, $permissoes))->get($rota);
        $resp->assertOk();
        $resp->assertInertia(fn (AssertableInertia $p) => $p->component($componente, false));
    }
});

it('?classico=1 mantém o Blade das 3 telas', function () {
    foreach (vlsTelas() as $nome => [$rota, , $blade, $permissoes]) {
        vlsLogin($this, vlsUsuario($this->bizId, $permissoes))
            ->get($rota . '?classico=1')
            ->assertOk()
            ->assertViewIs($blade);
    }
});

it('a visita Inertia (X-Inertia + X-Requested-With) recebe a Page — Assinaturas não cai mais no JSON do DataTable', function () {
    foreach (vlsTelas() as $nome => [$rota, $componente, , $permissoes]) {
        $resp = vlsLogin($this, vlsUsuario($this->bizId, $permissoes))->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => vlsVersao(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get($rota);

        $resp->assertOk();
        expect($resp->json('component'))->toBe($componente);
    }
});

it('Assinaturas: o fetch sem X-Inertia continua recebendo o JSON do DataTable', function () {
    $resp = vlsLogin($this, vlsUsuario($this->bizId, ['sell.view', 'access_all_locations']))
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/subscriptions?ajax=1');

    $resp->assertOk();
    $this->assertNull($resp->headers->get('X-Inertia'), 'o fetch da lista não pode virar resposta Inertia');
    $this->assertArrayHasKey('data', (array) $resp->json(), 'o fetch da lista precisa do JSON do DataTable');
});

it('Rascunhos: a Page lê os dados do endpoint do DataTable (JSON), não da própria rota (HTML)', function () {
    $user = vlsUsuario($this->bizId, ['draft.view_all', 'access_all_locations']);

    $props = vlsLogin($this, $user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => vlsVersao(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/sells/drafts')->json('props');

    $url = $props['urls']['datatable'] ?? null;
    expect($url)->toBe('/sells/draft-dt?is_quotation=0');

    // O endereço devolve JSON de DataTable — a Page faz `res.json()` nele.
    $json = vlsLogin($this, $user)
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get($url);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o endpoint que a Page lê tem de devolver o JSON do DataTable');
    expect($json->json('error'))->toBeNull();
});

it('Assinaturas: o parar/retomar aponta pra rota que existe', function () {
    $props = vlsLogin($this, vlsUsuario($this->bizId, ['sell.view', 'access_all_locations']))->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => vlsVersao(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/sells/subscriptions')->json('props');

    $base = $props['urls']['toggle'] ?? null;
    expect($base)->toBe('/toggle-subscription');

    // A Page chama `${toggle}/${id}` com GET: confere no registro de rotas vivo, não no disco.
    $uris = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods(), true))
        ->map(fn ($r) => '/' . ltrim($r->uri(), '/'))
        ->all();
    expect($uris)->toContain($base . '/{id}');
});
