<?php
// @covers-us US-REL-001

declare(strict_types=1);

use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Relatório montável — US-REL-001, fatia servidor (executar, salvar, abrir). Casos UC-RMT-*.
 *
 * Conta à mão (vendas da 98 entre 01 e 31/10/2031 — janela que o seed não usa):
 *   finais: 100,00 + 250,50 + 49,50 = 400,00 · rascunho de 999,00 NÃO conta · venda da 99 de 777,00 NÃO conta
 *   o segundo caminho é o TransactionUtil::getSellTotals do relatório pronto: tem de dar os mesmos 400,00.
 * Estoque: 7 un × custo 10,00 = 70,00 em estoque; sem `view_purchase_price` custo e valor somem.
 *
 * Não muda valor nem estoque. Tenants: 98 (ADR 0358) × 99. Nunca biz=4. Lane: acessos-pest.yml.
 */
uses(DatabaseTransactions::class);

function rmtVenda(int $bizId, float $total, string $status = 'final', string $data = '2031-10-15 10:00:00'): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => EstoqueFixture::locationId($bizId), 'type' => 'sell', 'status' => $status,
        'payment_status' => 'paid', 'invoice_no' => 'RMT-' . uniqid(), 'transaction_date' => $data,
        'total_before_tax' => $total, 'tax_amount' => 0, 'final_total' => $total,
        'created_by' => EstoqueFixture::userId($bizId), 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rmtComo($teste, int $bizId, array $permissoes): void
{
    $u = $teste->usuarioComPermissoes(array_merge($permissoes, ['access_all_locations']), \App\Business::find($bizId));
    $teste->actingAs($u);
    session(['user.business_id' => $bizId, 'user.id' => $u->id, 'business.id' => $bizId]);
}

const RMT_VENDAS = ['fonte' => 'vendas', 'campos' => ['numero', 'total'], 'de' => '2031-10-01', 'ate' => '2031-10-31'];

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('relatorios_salvos')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) $this->seededSupportClientTenant()->id;
    config(['relatorios.montavel.enabled' => true]);

    // Janela em outubro de 2031: o seed não tem venda lá, então o recorte é só deste teste.
    foreach ([100.0, 250.5, 49.5] as $v) {
        rmtVenda($this->biz, $v);
    }
    rmtVenda($this->biz, 999.0, 'draft');
    rmtVenda($this->outro, 777.0);
    rmtComo($this, $this->biz, ['purchase_n_sell_report.view', 'stock_report.view', 'view_purchase_price']);
});

it('UC-RMT-01 · com a flag desligada as rotas não existem', function () {
    config(['relatorios.montavel.enabled' => false]);
    $this->postJson('/relatorios/montavel/executar', ['definicao' => RMT_VENDAS])->assertNotFound();
    $this->getJson('/relatorios/montavel/salvos')->assertNotFound();
});

it('UC-RMT-02 · o total de vendas bate com a conta à mão e com o relatório pronto', function () {
    $r = $this->postJson('/relatorios/montavel/executar', ['definicao' => RMT_VENDAS])->assertOk()->json();

    expect($r['linhas'])->toHaveCount(3);
    expect((float) $r['totais']['total'])->toBe(400.0);
    $pronto = app(TransactionUtil::class)->getSellTotals($this->biz, '2031-10-01', '2031-10-31');
    expect(round((float) $pronto['total_sell_inc_tax'], 2))->toBe(400.0);

    $agrupado = $this->postJson('/relatorios/montavel/executar', ['definicao' => RMT_VENDAS + ['grupo' => 'mes']])->assertOk()->json();
    expect($agrupado['linhas'])->toHaveCount(1);
    expect((int) $agrupado['linhas'][0]['linhas'])->toBe(3);
    expect((float) $agrupado['linhas'][0]['total'])->toBe(400.0);
});

it('UC-RMT-03 · a outra empresa não vê as vendas nem abre o relatório salvo da 98', function () {
    $id = $this->postJson('/relatorios/montavel/salvos', ['nome' => 'Vendas de outubro', 'definicao' => RMT_VENDAS])->assertCreated()->json('id');

    rmtComo($this, $this->outro, ['purchase_n_sell_report.view']);
    $r = $this->postJson('/relatorios/montavel/executar', ['definicao' => RMT_VENDAS])->assertOk()->json();
    expect((float) $r['totais']['total'])->toBe(777.0);
    $this->getJson("/relatorios/montavel/salvos/{$id}")->assertNotFound();
    $this->putJson("/relatorios/montavel/salvos/{$id}", ['nome' => 'invadido', 'definicao' => RMT_VENDAS])->assertNotFound();
    expect(collect($this->getJson('/relatorios/montavel/salvos')->json())->pluck('id'))->not->toContain($id);
    expect(DB::table('relatorios_salvos')->where('id', $id)->value('nome'))->toBe('Vendas de outubro');
});

it('UC-RMT-04 · custo e valor em estoque só para quem vê preço de compra', function () {
    $p = EstoqueFixture::singleProduct($this->biz);
    DB::table('variations')->where('id', $p->variations[0]['variation_id'])->update(['dpp_inc_tax' => 10]);
    EstoqueFixture::setStock($p, 0, EstoqueFixture::locationId($this->biz), 7);
    $def = ['fonte' => 'estoque', 'campos' => ['sku', 'qtd', 'custo', 'valor_estoque'], 'filtros' => ['local' => EstoqueFixture::locationId($this->biz)]];

    $com = $this->postJson('/relatorios/montavel/executar', ['definicao' => $def])->assertOk()->json();
    expect(collect($com['colunas'])->pluck(0)->all())->toBe(['sku', 'qtd', 'custo', 'valor_estoque']);
    $sku = DB::table('variations')->where('id', $p->variations[0]['variation_id'])->value('sub_sku');
    $linha = collect($com['linhas'])->firstWhere('sku', $sku);
    expect((float) $linha['valor_estoque'])->toBe(70.0);

    rmtComo($this, $this->biz, ['stock_report.view']);
    $sem = $this->postJson('/relatorios/montavel/executar', ['definicao' => $def])->assertOk()->json();
    expect(collect($sem['colunas'])->pluck(0)->all())->toBe(['sku', 'qtd']);
    expect($sem['totais'])->not->toHaveKey('valor_estoque');
});

it('UC-RMT-05 · chave fora da lista é recusada, nada vira SQL', function () {
    $this->postJson('/relatorios/montavel/executar', ['definicao' => ['fonte' => 'vendas', 'campos' => ['t.final_total) or 1=1 --']]])
        ->assertStatus(422)->assertJsonFragment(['message' => 'Campo inválido: t.final_total) or 1=1 --']);
    $this->postJson('/relatorios/montavel/executar', ['definicao' => ['fonte' => 'users', 'campos' => ['id']]])->assertStatus(422);
});

it('UC-RMT-06 · sem a permissão da fonte o usuário não roda aquela fonte', function () {
    rmtComo($this, $this->biz, ['stock_report.view']);
    // Mesmo usuário roda estoque (prova que o 403 abaixo é da fonte, não do login).
    $this->postJson('/relatorios/montavel/executar', ['definicao' => ['fonte' => 'estoque', 'campos' => ['qtd']]])->assertOk();
    $this->postJson('/relatorios/montavel/executar', ['definicao' => RMT_VENDAS])->assertForbidden();
});

it('UC-RMT-07 · salvar e reabrir devolve a mesma definição e o mesmo resultado', function () {
    $id = $this->postJson('/relatorios/montavel/salvos', ['nome' => 'Por mês', 'definicao' => RMT_VENDAS + ['grupo' => 'mes', 'grafico' => 'barras']])->assertCreated()->json('id');
    $r = $this->getJson("/relatorios/montavel/salvos/{$id}")->assertOk()->json();

    expect($r['nome'])->toBe('Por mês');
    expect($r['definicao']['grupo'])->toBe('mes');
    expect($r['definicao']['grafico'])->toBe('barras');
    expect((float) $r['totais']['total'])->toBe(400.0);
});
