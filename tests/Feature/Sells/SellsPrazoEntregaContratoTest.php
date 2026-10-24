<?php

// @covers-us US-PCP-028

declare(strict_types=1);

// Cobre UC-QUO-09 (resources/js/Pages/Sells/Quotations.casos.md) e UC-VSHOW-14
// (resources/js/Pages/Sells/Show.casos.md) — UC-FLX-06 do catálogo de fluxos.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Prazo de entrega na tela de venda (UC-FLX-06 · US-PCP-028).
 *
 * ÂNCORA: UC-FLX-06 (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php`) + decisão [W] 2026-10-20
 * registrada no `PrometerPrazoDaVenda`: a data prometida mora em `transactions.due_date`
 * (PROJETO_DT_FIM) e a estimativa vem de `GET /producao/vendas/{id}/prazo`. O cálculo em si
 * já é contrato do `ProducaoPrazoPelaFilaContratoTest` (UC-PCPRZ-01..04); aqui fica só o que a
 * tela de venda recebe:
 *   - Cotações: a URL do prazo chega na Page e responde para a cotação do business; a de outro
 *     business é 404 (ADR 0093).
 *   - Detalhe da venda: `headline.prazo_prometido` é a data gravada, ou null sem data.
 *
 * Tenants: 98 (canônico, ADR 0358) × 2º business seedado. Nunca biz=4.
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function przVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Headers que o cliente Inertia manda de fato (X-Inertia E X-Requested-With — §5 2026-09-08). */
function przInertia(object $test, string $url)
{
    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => przVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get($url);
}

function przUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'PRZ Prazo',
        'username' => 'prz_' . uniqid(),
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

function przLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);
}

function przCotacao(int $bizId, int $userId, string $invoiceNo): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId),
        'created_by' => $userId,
        'type' => 'sell',
        'status' => 'draft',
        'sub_status' => 'quotation',
        'is_direct_sale' => 1,
        'payment_status' => 'due',
        'invoice_no' => $invoiceNo,
        'transaction_date' => now(),
        'total_before_tax' => 10,
        'final_total' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite ou DB vazio) — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_sell_lines', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }
    // A data prometida mora em transactions.due_date (migration 2026_05_11_170001). Sem ela o
    // caso não tem o que provar — falha, não pula: a coluna é pré-condição do contrato.
    expect(Schema::hasColumn('transactions', 'due_date'))->toBeTrue();

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);
    $this->prefixo = 'PRZ' . strtoupper(substr(uniqid(), -6)) . '-';
});

it('UC-QUO-09 UC-FLX-06 a Page de cotações recebe a URL do prazo e ela responde para a cotação do business', function () {
    expect(\Illuminate\Support\Facades\Route::has('producao.vendas.prazo'))->toBeTrue();

    $user = przUsuario($this->bizId, ['superadmin', 'sell.view', 'quotation.view_all', 'access_all_locations']);
    przLogin($this, $user);
    $cotacao = przCotacao($this->bizId, $user->id, $this->prefixo . 'q');

    $resp = przInertia($this, '/sells/quotations');
    $resp->assertOk();
    $page = json_decode($resp->getContent(), true);
    expect($page['component'] ?? null)->toBe('Sells/Quotations');
    $url = $page['props']['urls']['prazo'] ?? null;
    expect($url)->toBe('/producao/vendas/{id}/prazo');

    // A URL que a tela monta responde com a estimativa (sem roteiro: data nula, não erro).
    $json = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get(str_replace('{id}', (string) $cotacao, $url))
        ->assertOk()
        ->json();
    expect($json['transaction_id'] ?? null)->toBe($cotacao);
    expect(array_key_exists('data_prevista', $json))->toBeTrue();
});

it('UC-QUO-09 [T0] UC-FLX-06 a cotação de outro business não tem prazo pela mesma URL (404)', function () {
    $user = przUsuario($this->bizId, ['superadmin', 'sell.view', 'quotation.view_all', 'access_all_locations']);
    $estranho = przUsuario($this->outroBizId, ['sell.view']);
    $alheia = przCotacao($this->outroBizId, $estranho->id, $this->prefixo . 'x');
    $propria = przCotacao($this->bizId, $user->id, $this->prefixo . 'p');
    przLogin($this, $user);

    $headers = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    // Controle positivo: o mesmo usuário lê o prazo da cotação dele — a recusa é pela empresa.
    $this->withHeaders($headers)->get("/producao/vendas/{$propria}/prazo")->assertOk();
    $this->withHeaders($headers)->get("/producao/vendas/{$alheia}/prazo")->assertNotFound();
});

it('UC-VSHOW-14 UC-FLX-06 o detalhe da venda traz a data prometida gravada, e null sem data', function () {
    $user = przUsuario($this->bizId, ['sell.view', 'access_all_locations']);
    przLogin($this, $user);

    $produto = EstoqueFixture::singleProduct($this->bizId);
    $comData = (int) EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($this->bizId), 1, 10)['transaction_id'];
    $semData = (int) EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($this->bizId), 1, 10)['transaction_id'];
    DB::table('transactions')->where('id', $comData)->update(['due_date' => '2026-11-30']);
    DB::table('transactions')->where('id', $semData)->update(['due_date' => null]);

    $page = json_decode(przInertia($this, "/sells/{$comData}")->assertOk()->getContent(), true);
    expect($page['component'] ?? null)->toBe('Sells/Show');
    expect($page['props']['headline']['prazo_prometido'] ?? 'ausente')->toBe('2026-11-30');

    $page = json_decode(przInertia($this, "/sells/{$semData}")->assertOk()->getContent(), true);
    expect($page['component'] ?? null)->toBe('Sells/Show');
    expect(array_key_exists('prazo_prometido', $page['props']['headline']))->toBeTrue();
    expect($page['props']['headline']['prazo_prometido'])->toBeNull();
});
