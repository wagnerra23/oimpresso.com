<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * P-154 (Sabor Brasil) — a venda bonificada aparece em Vendas.
 *
 * A bonificação é gravada com `transactions.sub_type = 'bonificacao'` (UC-SBON-01). O `sub_type`
 * preenchido sempre separou o que não é venda (reparo, fatura de projeto), e a lista, o drawer e
 * a impressão/exportação em lote filtravam `whereNull('sub_type')`: a bonificação ficava
 * invisível, e com ela o botão de emitir a NF-e de bonificação, que mora no drawer.
 * O critério único é `Transaction::scopeVendaComumOuBonificacao`.
 *
 * Casos (resources/js/Pages/Sells/Index.casos.md): UC-SBON-02 · UC-SBON-03 · UC-SBON-04 · UC-SBON-05.
 * Tenants: 98 (canônico, ADR 0358) × outro business semeado. Nunca biz=1 nem biz=4.
 * Não roda local (proibicoes.md): lane sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function sbvUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SBV',
        'username' => 'sbv_'.uniqid(),
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

/** Venda final crua (sem observers) com o prefixo do teste, para filtrar a lista por `q`. */
function sbvVenda(object $t, int $bizId, string $sufixo, ?string $subType): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId, 'SBV'),
        'created_by' => $t->user->id,
        'type' => 'sell',
        'sub_type' => $subType,
        'status' => 'final',
        'payment_status' => 'due',
        'is_direct_sale' => 1,
        'invoice_no' => $t->prefixo.$sufixo,
        'transaction_date' => now()->format('Y-m-d').' 15:00:00',
        'total_before_tax' => 100,
        'final_total' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sbvHeaders(): array
{
    return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS MySQL ausente — roda na lane sells-pest.yml / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $this->outroBizId = EstoqueFixture::secondBusinessId();
    expect($this->bizId)->toBe(98);

    $this->prefixo = 'SBV'.strtoupper(substr(uniqid(), -6)).'-';
    $this->user = sbvUsuario($this->bizId, ['sell.create', 'direct_sell.view', 'access_all_locations']);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id]);

    $this->comum = sbvVenda($this, $this->bizId, 'COMUM', null);
    $this->bonif = sbvVenda($this, $this->bizId, 'BONIF', 'bonificacao');
    $this->reparo = sbvVenda($this, $this->bizId, 'REPARO', 'repair');
    $this->projeto = sbvVenda($this, $this->bizId, 'PROJ', 'project_invoice');
});

it('UC-SBON-02 a lista de vendas mostra a bonificação e continua sem reparo nem fatura de projeto', function () {
    $ids = collect($this->withHeaders(sbvHeaders())
        ->get('/sells-list-json?'.http_build_query(['q' => $this->prefixo]))
        ->assertOk()->json('data'))->pluck('id')->map(fn ($i) => (int) $i)->all();

    expect($ids)->toContain($this->comum)
        ->and($ids)->toContain($this->bonif)
        ->and($ids)->not->toContain($this->reparo)
        ->and($ids)->not->toContain($this->projeto)
        ->and($ids)->toHaveCount(2);
});

it('UC-SBON-03 o drawer abre a bonificação, onde fica o botão de emitir a NF-e', function () {
    $drawer = $this->withHeaders(sbvHeaders())->get("/sells/{$this->bonif}/sheet-data")->assertOk()->json();
    expect((int) $drawer['id'])->toBe($this->bonif);

    $this->withHeaders(sbvHeaders())->get("/sells/{$this->comum}/sheet-data")->assertOk();
    $this->withHeaders(sbvHeaders())->get("/sells/{$this->reparo}/sheet-data")->assertNotFound();
    $this->withHeaders(sbvHeaders())->get("/sells/{$this->projeto}/sheet-data")->assertNotFound();
});

it('UC-SBON-04 exportação e impressão em lote incluem a bonificação e seguem sem reparo', function () {
    $csv = $this->post('/sells/bulk-export', ['ids' => [$this->comum, $this->bonif, $this->reparo, $this->projeto]])
        ->assertOk()->streamedContent();

    expect($csv)->toContain($this->prefixo.'COMUM')
        ->and($csv)->toContain($this->prefixo.'BONIF')
        ->and($csv)->not->toContain($this->prefixo.'REPARO')
        ->and($csv)->not->toContain($this->prefixo.'PROJ');

    // Impressão: só reparo selecionado continua "nenhuma venda válida"; só a bonificação, não.
    $this->post('/sells/bulk-print', ['ids' => [$this->reparo]])->assertNotFound();
    $impressao = $this->post('/sells/bulk-print', ['ids' => [$this->bonif]]);
    expect($impressao->status())->not->toBe(404);
});

it('UC-SBON-05 [T0] pagamento rápido fica fora da bonificação e o drawer não abre a de outra empresa', function () {
    $this->withHeaders(sbvHeaders())
        ->post("/sells/{$this->bonif}/quick-payment", ['amount' => 100, 'method' => 'cash'])
        ->assertNotFound();

    if ($this->outroBizId === null) {
        $this->markTestSkipped('Lane sem 2º business semeado — perna cross-tenant não exercitável.');
    }
    $deOutra = sbvVenda($this, $this->outroBizId, 'OUTRA', 'bonificacao');

    $this->withHeaders(sbvHeaders())->get("/sells/{$deOutra}/sheet-data")->assertNotFound();
    $ids = collect($this->withHeaders(sbvHeaders())
        ->get('/sells-list-json?'.http_build_query(['q' => $this->prefixo]))
        ->assertOk()->json('data'))->pluck('id')->map(fn ($i) => (int) $i)->all();
    expect($ids)->not->toContain($deOutra);
});
