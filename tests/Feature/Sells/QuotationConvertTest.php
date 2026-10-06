<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;
use Tests\Support\EstoqueProduto;

/**
 * Converter cotação em venda — thread Q3 de venda-menu (D-ORC-1, 2026-10-06).
 *
 * Reusa SellPosController@convertToInvoice pela MESMA rota do Blade
 * (`GET /sells/convert-to-draft/{id}`). A Page Sells/Quotations só ganha o botão,
 * atrás da MESMA condição do item "Converter em fatura" do Blade: permissão de venda
 * E `config('constants.enable_convert_draft_to_invoice')` (false no repo).
 *
 * UC-QUO-04 (03-orcamentos.md; entra no Quotations.casos.md depois da Q1).
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 *
 * REGRA MESTRE (valor + estoque) — conta à mão, independente do código:
 *
 *   tenant 98 · produto P · local L · saldo inicial 10
 *   cotação C: 1 linha, 3 un × 1.234,50 · sem desconto · sem imposto
 *     final_total = 3 × 1.234,50 = 3.703,50   (não muda na conversão)
 *   converter C:  status draft → final · sub_status quotation → null · estoque 10 → 7
 *   converter C de novo: 409 · estoque segue 7
 *   cotação D (não convertida): segue draft/quotation · estoque não mexe
 *
 * Caminho B da dupla prova: depois da conversão, o total e a baixa são recalculados a
 * partir das linhas gravadas no banco (Σ qtd × preço · Σ qtd) e têm de bater com a conta.
 *
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function qconvUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'QCONV Cotacao',
        'username' => 'qconv_' . uniqid(),
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

/** Sessão que o SetSessionData montaria (mapPurchaseSell lê business.enable_product_expiry). */
function qconvSessao(User $user): array
{
    return [
        'user' => ['id' => $user->id, 'business_id' => (int) $user->business_id],
        'business' => [
            'id' => (int) $user->business_id,
            'date_format' => 'd/m/Y',
            'time_format' => 24,
            'currency_precision' => 2,
            'quantity_precision' => 2,
            'accounting_method' => 'fifo',
            'enable_product_expiry' => 0,
        ],
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];
}

/** Cotação (draft + quotation, venda direta: não exige caixa aberto) com 1 linha. */
function qconvCotacao(EstoqueProduto $p, int $loc, int $contato, float $qtd, float $preco): int
{
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $p->businessId,
        'location_id' => $loc,
        'contact_id' => $contato,
        'type' => 'sell',
        'status' => 'draft',
        'sub_status' => 'quotation',
        'is_quotation' => 1,
        'is_direct_sale' => 1,
        'payment_status' => 'due',
        'invoice_no' => 'QCONV-' . uniqid(),
        'transaction_date' => now(),
        'total_before_tax' => $qtd * $preco,
        'final_total' => $qtd * $preco,
        'created_by' => EstoqueFixture::userId($p->businessId),
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $id,
        'product_id' => $p->productId,
        'variation_id' => $p->variations[0]['variation_id'],
        'quantity' => $qtd,
        'quantity_returned' => 0,
        'unit_price' => $preco,
        'unit_price_inc_tax' => $preco,
        'item_tax' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function qconvContato(int $bizId): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => 'QCONV Cliente', 'contact_status' => 'active',
        'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'transaction_sell_lines', 'contacts', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas DISTINTAS (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);

    // Sem lote de compra no fixture: o mapeamento compra×venda não pode recusar a baixa.
    $pos = json_decode((string) DB::table('business')->where('id', $this->bizId)->value('pos_settings'), true) ?: [];
    $pos['allow_overselling'] = 1;
    DB::table('business')->where('id', $this->bizId)->update(['pos_settings' => json_encode($pos)]);

    $this->loc = EstoqueFixture::locationId($this->bizId);
    $this->produto = EstoqueFixture::singleProduct($this->bizId);
    EstoqueFixture::setStock($this->produto, 0, $this->loc, 10.0);
    $contato = qconvContato($this->bizId);

    $this->cotacao = qconvCotacao($this->produto, $this->loc, $contato, 3.0, 1234.50);
    $this->outraCotacao = qconvCotacao($this->produto, $this->loc, $contato, 2.0, 99.90);

    $this->usuario = qconvUsuario($this->bizId, ['sell.create', 'quotation.view_all', 'access_all_locations']);
    $this->actingAs($this->usuario)->withSession(qconvSessao($this->usuario));
});

it('UC-QUO-04 converte a cotação em venda com os mesmos itens e preço e baixa o estoque uma vez', function () {
    $this->get("/sells/convert-to-draft/{$this->cotacao}")->assertStatus(302);

    $venda = DB::table('transactions')->where('id', $this->cotacao)->first();
    expect($venda->status)->toBe('final');
    expect($venda->sub_status)->toBeNull();
    expect((int) $venda->is_quotation)->toBe(0);
    expect($venda->invoice_no)->not->toStartWith('QCONV-');

    // Caminho A — a conta à mão do cabeçalho.
    expect(round((float) $venda->final_total, 2))->toBe(3703.50);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->loc))->toBe(7.0);

    // Caminho B — recalculado das linhas gravadas: mesmas linhas, mesmo total, baixa = Σ qtd.
    $linhas = DB::table('transaction_sell_lines')->where('transaction_id', $this->cotacao)->get();
    expect($linhas)->toHaveCount(1);
    $totalLinhas = round($linhas->sum(fn ($l) => (float) $l->quantity * (float) $l->unit_price_inc_tax), 2);
    expect($totalLinhas)->toBe(round((float) $venda->final_total, 2));
    expect(10.0 - (float) $linhas->sum('quantity'))->toBe(EstoqueFixture::currentStock($this->produto, 0, $this->loc));

    // Controle positivo: a cotação que ninguém converteu segue cotação.
    $outra = DB::table('transactions')->where('id', $this->outraCotacao)->first();
    expect($outra->status)->toBe('draft');
    expect($outra->sub_status)->toBe('quotation');
});

it('UC-QUO-04 converter a mesma cotação duas vezes responde 409 e não baixa estoque de novo', function () {
    $this->get("/sells/convert-to-draft/{$this->cotacao}")->assertStatus(302);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->loc))->toBe(7.0);

    $this->get("/sells/convert-to-draft/{$this->cotacao}")->assertStatus(409);
    expect(EstoqueFixture::currentStock($this->produto, 0, $this->loc))->toBe(7.0);
});

it('UC-QUO-04 [T0] não converte cotação de outra empresa', function () {
    $produto99 = EstoqueFixture::singleProduct($this->outroBizId);
    $loc99 = EstoqueFixture::locationId($this->outroBizId);
    EstoqueFixture::setStock($produto99, 0, $loc99, 10.0);
    $cotacao99 = qconvCotacao($produto99, $loc99, qconvContato($this->outroBizId), 3.0, 1234.50);

    $this->get("/sells/convert-to-draft/{$cotacao99}")->assertStatus(404);

    expect(DB::table('transactions')->where('id', $cotacao99)->value('status'))->toBe('draft');
    expect(EstoqueFixture::currentStock($produto99, 0, $loc99))->toBe(10.0);
});

it('UC-QUO-04 o botão Converter segue a flag do Blade e a lista lê o endpoint que devolve JSON', function () {
    $versao = file_exists(public_path('build-inertia/manifest.json')) ? md5_file(public_path('build-inertia/manifest.json')) : '1';
    $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $versao, 'X-Requested-With' => 'XMLHttpRequest'];

    config(['constants.enable_convert_draft_to_invoice' => false]);
    $page = json_decode($this->withHeaders($inertia)->get('/sells/quotations')->assertStatus(200)->getContent(), true);
    expect($page['component'])->toBe('Sells/Quotations');
    expect($page['props']['permissions']['convert'])->toBeFalse();

    config(['constants.enable_convert_draft_to_invoice' => true]);
    $page = json_decode($this->withHeaders($inertia)->get('/sells/quotations')->getContent(), true);
    expect($page['props']['permissions']['convert'])->toBeTrue();
    expect($page['props']['urls']['convert'])->toBe('/sells/convert-to-draft/{id}');

    // A lista React lê urls.datatable: tem de ser JSON com as cotações do 98.
    $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get($page['props']['urls']['datatable'])
        ->assertStatus(200)
        ->json();
    // Sem access_all_locations o draft-dt filtra por local permitido e a lista sai vazia
    // (permitted_locations) — por isso o usuário do fixture tem a permissão.
    $ids = array_map(fn ($r) => (int) $r['id'], $json['data']);
    expect($ids)->toContain($this->cotacao);
    expect($ids)->toContain($this->outraCotacao);
});
