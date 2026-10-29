<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Duplicar a venda guardando a origem (`GET /sells/duplicate/{id}` → `SellController::duplicateSell`)
 * e o vínculo nos dois sentidos (`/sells-list-json` e `/sells/{id}/sheet-data`).
 *
 * Casos: P-035 em `Sells/Create.casos.md` (UC-SDUP-01..03), UC-SDUP-07 em `Sells/Edit.casos.md` e P-031 em `Sells/Index.casos.md`
 * (UC-SDUP-04..06). Derivados do aceite dos pedidos, não do código.
 *
 * REGRA MESTRE (valor e estoque): esta mudança não muda conta nenhuma. A cópia continua com o
 * preço da origem (D-05 segue em aberto para o [W]) e nasce rascunho, que não mexe em estoque.
 * Valores à mão: origem = 3 un × 20,00 = 60,00 · cópia = 3 un × 20,00 = 60,00 · estoque do
 * produto no local: 10 antes e 10 depois de duplicar.
 *
 * Tenants: 98 (canônico, ADR 0358) × outro business semeado. Nunca biz=1 nem biz=4.
 * Não roda local (proibicoes.md): lane sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function sdupUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SDUP',
        'username' => 'sdup_' . uniqid(),
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

/** Venda final do business com o prefixo do teste, para filtrar a lista por `q`. */
function sdupVendaFinal(object $t, int $bizId, string $sufixo, ?int $duplicadaDe = null): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'created_by' => $t->user->id,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'invoice_no' => $t->prefixo . $sufixo,
        'transaction_date' => now()->format('Y-m-d') . ' 15:00:00',
        'total_before_tax' => 10,
        'final_total' => 10,
        'duplicated_from_id' => $duplicadaDe,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sdupHeaders(): array
{
    return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transactions', 'duplicated_from_id')) {
        $this->markTestSkipped('Schema UltimatePOS MySQL ausente — roda na lane sells-pest.yml / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $this->outroBizId = EstoqueFixture::secondBusinessId();
    expect($this->bizId)->toBe(98);
    if ($this->outroBizId === null) {
        $this->markTestSkipped('Lane sem 2º business semeado — contrato cross-tenant não exercitável.');
    }

    $this->prefixo = 'SDUP' . strtoupper(substr(uniqid(), -6)) . '-';
    $this->user = sdupUsuario($this->bizId, ['sell.create', 'direct_sell.view', 'access_all_locations']);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id]);

    // Origem: venda final, 1 linha de 3 un × 20,00, dentro de um pipeline FSM qualquer.
    $this->local = EstoqueFixture::locationId($this->bizId, 'SDUP');
    $this->produto = EstoqueFixture::singleProduct($this->bizId);
    EstoqueFixture::setStock($this->produto, 0, $this->local, 10);
    $venda = EstoqueFixture::saleWithLine($this->produto, 0, $this->local, 3, 20.0);
    $this->origemId = $venda['transaction_id'];
    DB::table('transactions')->where('id', $this->origemId)->update([
        'invoice_no' => $this->prefixo . 'ORIG',
        'is_direct_sale' => 1,
        'process_id' => 987001,
        'current_stage_id' => 987002,
    ]);

    $this->duplicar = function (int $id) {
        $antes = (int) DB::table('transactions')->max('id');
        $resp = $this->get('/sells/duplicate/' . $id);

        return [$resp, DB::table('transactions')->where('id', '>', $antes)->where('duplicated_from_id', $id)->first()];
    };
});

it('UC-SDUP-01 duplicar cria um rascunho novo que guarda a venda de origem', function () {
    [$resp, $copia] = ($this->duplicar)($this->origemId);

    $resp->assertRedirect();
    expect($copia)->not->toBeNull('Duplicar não gravou a origem na cópia (P-035).');
    expect((int) $copia->duplicated_from_id)->toBe($this->origemId);
    expect((int) $copia->business_id)->toBe($this->bizId);
    expect($copia->status)->toBe('draft');
    expect($copia->payment_status)->toBeNull();
    expect($copia->invoice_no)->not->toBe($this->prefixo . 'ORIG');
});

it('UC-SDUP-02 a cópia leva os mesmos itens e preços da origem e não mexe no estoque', function () {
    [, $copia] = ($this->duplicar)($this->origemId);
    expect($copia)->not->toBeNull();

    // Prova 1 — à mão: 3 × 20,00 = 60,00 nas duas.
    expect((float) $copia->final_total)->toBe(60.0);

    // Prova 2 — as linhas da cópia contra as da origem, campo a campo.
    $linhas = fn (int $id) => DB::table('transaction_sell_lines')->where('transaction_id', $id)
        ->orderBy('id')->get(['product_id', 'variation_id', 'quantity', 'unit_price_inc_tax'])
        ->map(fn ($l) => [(int) $l->product_id, (int) $l->variation_id, (float) $l->quantity, (float) $l->unit_price_inc_tax])
        ->all();
    expect($linhas((int) $copia->id))->toBe($linhas($this->origemId));
    expect($linhas((int) $copia->id))->toBe([[$this->produto->productId, $this->produto->variations[0]['variation_id'], 3.0, 20.0]]);

    expect(EstoqueFixture::currentStock($this->produto, 0, $this->local))->toBe(10.0);
});

it('UC-SDUP-03 a cópia nasce fora do pipeline FSM, sem herdar o estágio da origem', function () {
    [, $copia] = ($this->duplicar)($this->origemId);
    expect($copia)->not->toBeNull();

    expect($copia->process_id)->toBeNull();
    expect($copia->current_stage_id)->toBeNull();
    // A origem não foi tocada.
    expect((int) DB::table('transactions')->where('id', $this->origemId)->value('current_stage_id'))->toBe(987002);
});

it('UC-SDUP-04 a linha da venda duplicada mostra de qual venda ela veio', function () {
    $origem = sdupVendaFinal($this, $this->bizId, 'V150');
    $copia = sdupVendaFinal($this, $this->bizId, 'V200', $origem);

    $dados = collect($this->withHeaders(sdupHeaders())
        ->get('/sells-list-json?' . http_build_query(['q' => $this->prefixo]))
        ->assertOk()->json('data'))->keyBy('id');

    expect($dados->has($copia))->toBeTrue('A cópia não veio na lista — o contrato não foi exercitado.');
    expect($dados[$copia]['duplicated_from'])->toBe(['id' => $origem, 'invoice_no' => $this->prefixo . 'V150']);
    expect($dados[$origem]['duplicated_from'])->toBeNull();
});

it('UC-SDUP-05 o drawer mostra o vínculo nos dois sentidos', function () {
    $origem = sdupVendaFinal($this, $this->bizId, 'V150');
    $copia = sdupVendaFinal($this, $this->bizId, 'V200', $origem);

    $daCopia = $this->withHeaders(sdupHeaders())->get("/sells/{$copia}/sheet-data")->assertOk()->json();
    expect($daCopia['duplicated_from'])->toBe(['id' => $origem, 'invoice_no' => $this->prefixo . 'V150']);
    expect($daCopia['duplicated_into'])->toBe([]);

    $daOrigem = $this->withHeaders(sdupHeaders())->get("/sells/{$origem}/sheet-data")->assertOk()->json();
    expect($daOrigem['duplicated_from'])->toBeNull();
    expect($daOrigem['duplicated_into'])->toBe([['id' => $copia, 'invoice_no' => $this->prefixo . 'V200']]);
});

it('UC-SDUP-06 [T0] venda de outra empresa não duplica nem aparece no vínculo', function () {
    $alheia = (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->outroBizId, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'invoice_no' => $this->prefixo . 'ALHEIA',
        'transaction_date' => now(), 'total_before_tax' => 5, 'final_total' => 5,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Controle positivo: a origem da própria empresa duplica (o mecanismo funciona).
    [, $propria] = ($this->duplicar)($this->origemId);
    expect($propria)->not->toBeNull();

    // Vínculo cruzado gravado à força não vaza: nem a origem alheia aparece na cópia,
    // nem a cópia alheia aparece no drawer da origem.
    $minha = sdupVendaFinal($this, $this->bizId, 'MINHA', $alheia);
    DB::table('transactions')->insert([
        'business_id' => $this->outroBizId, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'invoice_no' => $this->prefixo . 'COPIA-ALHEIA',
        'transaction_date' => now(), 'total_before_tax' => 5, 'final_total' => 5,
        'duplicated_from_id' => $minha, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $drawer = $this->withHeaders(sdupHeaders())->get("/sells/{$minha}/sheet-data")->assertOk()->json();
    expect($drawer['duplicated_from'])->toBeNull();
    expect($drawer['duplicated_into'])->toBe([]);

    // Por último: no 404 o `duplicateSell` faz `DB::rollBack()`, que dentro do teste desfaz também a
    // transação do `DatabaseTransactions` (o usuário do `beforeEach` some). Nada roda depois disto.
    [$resp, $copia] = ($this->duplicar)($alheia);
    expect($resp->getStatusCode())->toBe(404);
    expect($copia)->toBeNull();
});

it('UC-SDUP-07 o topo do rascunho duplicado mostra de qual venda ele veio', function () {
    // Sessão como o `SetSessionData` monta em produção (mesmo bloco do SellsEditContratoTest).
    session([
        'business.transaction_edit_days' => 30,
        'business.date_format' => 'd/m/Y',
        'business.time_format' => 24,
        'business.enabled_modules' => (array) json_decode(
            (string) DB::table('business')->where('id', $this->bizId)->value('enabled_modules'), true
        ),
    ]);
    $moeda = DB::table('currencies')->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))->first();
    session(['currency' => [
        'id' => (int) ($moeda->id ?? 1), 'code' => (string) ($moeda->code ?? 'BRL'), 'symbol' => (string) ($moeda->symbol ?? 'R$'),
        'thousand_separator' => (string) ($moeda->thousand_separator ?? '.'), 'decimal_separator' => (string) ($moeda->decimal_separator ?? ','),
    ]]);
    Permission::findOrCreate('direct_sell.update', 'web');
    $this->user->givePermissionTo('direct_sell.update');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    [, $copia] = ($this->duplicar)($this->origemId);
    expect($copia)->not->toBeNull();

    $versao = file_exists(public_path('build-inertia/manifest.json')) ? md5_file(public_path('build-inertia/manifest.json')) : '1';
    $abrir = fn (int $id) => $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])->get("/sells/{$id}/edit");

    $daCopia = $abrir((int) $copia->id)->assertOk();
    expect($daCopia->json('component'))->toBe('Sells/Edit')
        ->and($daCopia->json('props.headline.duplicated_from'))->toBe(['id' => $this->origemId, 'invoice_no' => $this->prefixo . 'ORIG']);

    // A origem não nasceu de duplicação: o topo dela não mostra nada.
    expect($abrir($this->origemId)->assertOk()->json('props.headline.duplicated_from'))->toBeNull();
});
