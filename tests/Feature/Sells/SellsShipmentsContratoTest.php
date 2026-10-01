<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da tela Remessas (`GET /shipments` → `SellController@shipments`, thread 02 do
 * playbook venda-menu). UCs: resources/js/Pages/Sells/Shipments/Index.casos.md (UC-REM-01..07;
 * 03/04 são do front e entram junto com a tela).
 *
 * O que a migração NÃO pode mudar: a lista vem do DataTables de `index()` (only_shipments=true)
 * e a escrita do `updateShipping` existente. A tela nova só ganha o branch Inertia em
 * `shipments()` e o JSON em `editShipping()` (o modal Blade segue recebendo HTML).
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário, `seededSupportClientTenant()`, criado se
 * faltar). Nunca biz=4. Não roda local (proibicoes.md): lane sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function remInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Os dois headers que o cliente Inertia manda (X-Inertia E X-Requested-With). */
function remInertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => remInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

/** Os headers do fetch da tela (padrão Drafts): JSON + AJAX, sem X-Inertia. */
function remAjaxHeaders(): array
{
    return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
}

function remUsuario(int $bizId, array $permissoes, string $nome = 'REM Remessa'): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => $nome,
        'username' => 'rem_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function remVenda(int $bizId, int $userId, ?int $locationId, ?string $envio, ?int $entregador = null): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'invoice_no' => 'REM-' . uniqid(),
        'transaction_date' => now()->subDay(),
        'total_before_tax' => 10,
        'final_total' => 10,
        'shipping_status' => $envio,
        'delivery_person' => $entregador,
        'shipping_details' => 'Retira na portaria',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Login com a sessão que o SetSessionData monta em produção (mesma receita do SellsEditContratoTest). */
function remLogin(object $test, User $user): void
{
    $test->actingAs($user);
    $bizId = (int) $user->business_id;
    session([
        'user.business_id' => $bizId,
        'user.id' => $user->id,
        'business.date_format' => 'd/m/Y',
        'business.time_format' => 24,
        'business.enabled_modules' => (array) json_decode(
            (string) DB::table('business')->where('id', $bizId)->value('enabled_modules'),
            true
        ),
    ]);
    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $bizId)->value('currency_id'))
        ->first();
    session(['currency' => [
        'id' => (int) ($currency->id ?? 1),
        'code' => (string) ($currency->code ?? 'BRL'),
        'symbol' => (string) ($currency->symbol ?? 'R$'),
        'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
        'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
    ]]);
}

/** Ids da lista DataTables. `index()` faz removeColumn('id'): o id sai do link de edição da remessa. */
function remIdsDaLista(array $json): array
{
    $ids = [];
    foreach ($json['data'] ?? [] as $linha) {
        if (preg_match('#edit-shipping/(\d+)#', (string) ($linha['shipping_status'] ?? '') . (string) ($linha['action'] ?? ''), $m)) {
            $ids[] = (int) $m[1];
        }
    }

    return $ids;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'business', 'users', 'business_locations'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->user = remUsuario($this->bizId, ['access_shipping', 'access_all_locations']);
    $this->entregadorA = remUsuario($this->bizId, [], 'REM Entregador A');
    $this->entregadorB = remUsuario($this->bizId, [], 'REM Entregador B');
    $loc = EstoqueFixture::locationId($this->bizId);
    $uid = $this->user->id;

    $this->vendaA = remVenda($this->bizId, $uid, $loc, 'ordered', $this->entregadorA->id);
    $this->vendaB = remVenda($this->bizId, $uid, $loc, 'packed', $this->entregadorB->id);
    $this->vendaX = remVenda($this->outroBizId, $uid, null, 'ordered');

    remLogin($this, $this->user);
});

it('UC-REM-01 salvar pelo endpoint existente grava o novo status na própria venda', function () {
    $antes = DB::table('transactions')->count();

    $res = $this->withHeaders(remAjaxHeaders())->putJson('/sells/update-shipping/' . $this->vendaA, [
        'shipping_status' => 'shipped',
        'shipping_details' => 'Saiu com o entregador',
        'delivery_person' => $this->entregadorA->id,
    ]);
    $res->assertOk();
    expect((int) $res->json('success'))->toBe(1);

    expect(DB::table('transactions')->where('id', $this->vendaA)->value('shipping_status'))->toBe('shipped');
    expect(DB::table('transactions')->count())->toBe($antes); // atualiza a venda, não cria documento

    // O drawer relê pelo MESMO endpoint de edição, agora em JSON.
    $dados = $this->withHeaders(remAjaxHeaders())->get('/sells/edit-shipping/' . $this->vendaA);
    $dados->assertOk();
    expect($dados->json('shipping_status'))->toBe('shipped');
    expect($dados->json('shipping_statuses.shipped'))->not->toBeNull();
    expect((int) $dados->json('delivery_person'))->toBe($this->entregadorA->id);
});

it('UC-REM-02 filtro por entregador deixa só as remessas dele', function () {
    // CONTROLE POSITIVO: sem filtro, as duas remessas do 98 aparecem.
    $todas = $this->withHeaders(remAjaxHeaders())->get('/sells?only_shipments=true&length=500&start=0');
    $todas->assertOk();
    $idsTodas = remIdsDaLista($todas->json());
    expect($idsTodas)->toContain($this->vendaA);
    expect($idsTodas)->toContain($this->vendaB);

    $filtrada = $this->withHeaders(remAjaxHeaders())
        ->get('/sells?only_shipments=true&length=500&start=0&delivery_person=' . $this->entregadorA->id);
    $filtrada->assertOk();
    $ids = remIdsDaLista($filtrada->json());
    expect($ids)->toContain($this->vendaA);
    expect(in_array($this->vendaB, $ids, true))->toBeFalse();
});

it('UC-REM-05 [T0] remessa de outro business não abre nem muda', function () {
    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a venda alheia existe, com status próprio.
    expect((int) DB::table('transactions')->where('id', $this->vendaX)->value('business_id'))->toBe($this->outroBizId);

    $this->withHeaders(remAjaxHeaders())->get('/sells/edit-shipping/' . $this->vendaX)->assertNotFound();

    $this->withHeaders(remAjaxHeaders())->putJson('/sells/update-shipping/' . $this->vendaX, [
        'shipping_status' => 'delivered',
    ]);
    expect(DB::table('transactions')->where('id', $this->vendaX)->value('shipping_status'))->toBe('ordered');

    // Nem a lista do 98 traz a venda do 99.
    $lista = $this->withHeaders(remAjaxHeaders())->get('/sells?only_shipments=true&length=500&start=0');
    expect(in_array($this->vendaX, remIdsDaLista($lista->json()), true))->toBeFalse();
});

it('UC-REM-06 sem permissão de remessa a tela e o drawer devolvem 403', function () {
    $semPermissao = remUsuario($this->bizId, []);
    remLogin($this, $semPermissao);

    $this->withHeaders(remInertiaHeaders())->get('/shipments')->assertStatus(403);
    $this->withHeaders(remAjaxHeaders())->get('/sells/edit-shipping/' . $this->vendaA)->assertStatus(403);
});

it('UC-REM-07 Inertia renderiza Sells/Shipments/Index e o Blade segue como fallback', function () {
    $res = $this->withHeaders(remInertiaHeaders())->get('/shipments');
    $res->assertOk();
    $page = json_decode($res->getContent(), true);

    expect($page['component'] ?? null)->toBe('Sells/Shipments/Index');
    $p = $page['props'];
    expect(array_keys($p['shippingStatuses']))->toBe(['ordered', 'packed', 'shipped', 'delivered', 'cancelled']);
    expect(array_key_exists((string) $this->entregadorA->id, $p['filters']['deliveryPersons']))->toBeTrue();
    expect($p['urls'])->toBe([
        'datatable' => '/sells',
        'edit' => '/sells/edit-shipping/',
        'update' => '/sells/update-shipping/',
    ]);

    // As URLs apontam para rotas que JÁ existem (nenhuma rota nova).
    $rotas = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri());
    expect($rotas->contains(fn ($r) => str_contains($r, 'GET') && str_ends_with($r, ' sells/edit-shipping/{id}')))->toBeTrue();
    expect($rotas->contains(fn ($r) => str_contains($r, 'PUT') && str_ends_with($r, ' sells/update-shipping/{id}')))->toBeTrue();

    // Fallback Blade preservado (cutover F5 é humano).
    $ctrl = (string) file_get_contents(app_path('Http/Controllers/SellController.php'));
    expect($ctrl)->toContain("Inertia::render('Sells/Shipments/Index'");
    expect($ctrl)->toContain("return view('sell.shipments')");
});
