<?php
// @covers-us US-PCP-028

declare(strict_types=1);
// Cobre UC-PCPRZ-01..04 (Postos/Index.casos.md · US-PCP-028 · UC-FLX-06 — prazo de entrega pela fila).

use App\Transaction;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\PrazoPelaFila;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: UC-FLX-06 do catálogo de fluxos ("Calcula a data de entrega pela capacidade livre de cada
 * etapa", etapa Aprovado da venda) e a ADR 0436 (o roteiro anda nas programações do PCP). As contas
 * esperadas estão feitas à mão em cada caso. Tenant 98 × 99; NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    Carbon::setTestNow('2026-10-20 09:00:00');
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->vendedor = $this->usuarioComPermissoes(['sell.view'], $this->B98);
    $u = (int) $this->vendedor->id;

    // Postos novos: a fila só tem o que o teste põe neles.
    $posto = fn (string $nome, float $horasDia) => (int) DB::table('pcp_postos')->insertGetId(['business_id' => $this->b98,
        'codigo' => 'PRZ-'.uniqid(), 'nome' => $nome, 'tipo_custo' => 'maquina', 'custo_hora' => '0', 'horas_por_dia' => $horasDia,
        'created_at' => now(), 'updated_at' => now()]);
    $this->impressora = $posto('Impressora', 8);
    $this->corte = $posto('Corte', 0);        // sem horas por dia → padrão 8 h
    $this->acabamento = $posto('Acabamento', 10);

    $this->prog = fn (int $biz, int $postoId, int $horas, string $status = 'pendente', string $tipo = 'comvis_os', int $os = 0) => DB::table('pcp_programacoes')->insert([
        'business_id' => $biz, 'os_tipo' => $tipo, 'os_id' => $os ?: random_int(100000, 999999), 'posto_id' => $postoId,
        'inicio_previsto' => '2026-10-21 08:00:00', 'fim_previsto' => Carbon::parse('2026-10-21 08:00:00')->addHours($horas),
        'status' => $status, 'created_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
    // Fila na impressora: 2 × 8 h abertas = 16 h. Concluída e a da outra empresa (mesmo posto) não contam.
    ($this->prog)($this->b98, $this->impressora, 8);
    ($this->prog)($this->b98, $this->impressora, 8);
    ($this->prog)($this->b98, $this->impressora, 8, 'concluida');
    ($this->prog)($this->b99, $this->impressora, 40);
    // Fila no acabamento: 4 h iniciada = 0,4 dia (10 h/dia).
    ($this->prog)($this->b98, $this->acabamento, 4, 'iniciada');

    // Roteiro: Impressão 4 h → [Corte 8 h ‖ Acabamento 2 h (+ Laminação opcional)] → Conferência 4 h sem posto.
    $this->roteiro = DB::table('pcp_roteiros')->insertGetId(['business_id' => $this->b98, 'familia' => 'Banner', 'nome' => 'Banner '.uniqid(),
        'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $e = fn ($bloco, $trilha, $ordem, $nome, $posto, $horas, $opc = null) => ['business_id' => $this->b98, 'roteiro_id' => $this->roteiro,
        'bloco' => $bloco, 'trilha' => $trilha, 'ordem' => $ordem, 'nome' => $nome, 'posto_id' => $posto, 'horas' => $horas,
        'opcional_por' => $opc, 'created_at' => now(), 'updated_at' => now()];
    DB::table('pcp_roteiro_etapas')->insert([
        $e(1, 0, 0, 'Impressão', $this->impressora, 4), $e(2, 1, 0, 'Corte', $this->corte, 8),
        $e(2, 2, 0, 'Acabamento', $this->acabamento, 2), $e(2, 2, 1, 'Laminação', $this->acabamento, 5, 'com laminação'),
        $e(3, 0, 0, 'Conferência', null, 4),
    ]);
    [$this->produto, $this->variacao] = przProduto($this->b98, $u, 'Banner lona');
    DB::table('pcp_produto_roteiro')->insert(['business_id' => $this->b98, 'product_id' => $this->produto, 'roteiro_id' => $this->roteiro,
        'created_at' => now(), 'updated_at' => now()]);
});

afterEach(fn () => Carbon::setTestNow());

/** @return array{0:int,1:int} */
function przProduto(int $biz, int $userId, string $nome): array
{
    $suf = random_int(100000, 999999);
    $unit = DB::table('units')->insertGetId(['business_id' => $biz, 'actual_name' => 'Unidade PRZ', 'short_name' => 'UP', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    $prod = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit, 'tax_type' => 'exclusive',
        'sku' => 'PRZ-'.$suf, 'barcode_type' => 'C128', 'enable_stock' => 0, 'alert_quantity' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    $pv = DB::table('product_variations')->insertGetId(['product_id' => $prod, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $var = DB::table('variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'PRZ-'.$suf,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);

    return [$prod, $var];
}

/** Orçamento (venda em rascunho) com `n` linhas do produto com roteiro e, opcional, uma sem roteiro. */
function przVenda($teste, int $biz, int $comRoteiro, bool $semRoteiro = false): int
{
    $tx = new Transaction();
    foreach (['business_id' => $biz, 'type' => 'sell', 'status' => 'draft', 'payment_status' => 'due', 'contact_id' => 1,
        'invoice_no' => 'PRZ-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 100, 'final_total' => 100,
        'created_by' => $teste->vendedor->id] as $k => $v) {
        $tx->{$k} = $v;
    }
    $tx->save();
    $linha = fn ($prod, $var) => ['transaction_id' => $tx->id, 'product_id' => $prod, 'variation_id' => $var, 'quantity' => 1,
        'unit_price' => 50, 'unit_price_inc_tax' => 50, 'created_at' => now(), 'updated_at' => now()];
    for ($i = 0; $i < $comRoteiro; $i++) {
        // A partir da 2ª linha, outro produto com o mesmo roteiro: a chave única de
        // transaction_sell_lines (venda + produto + variação) não aceita o mesmo produto duas vezes.
        [$p, $v] = $i === 0 ? [$teste->produto, $teste->variacao] : przProduto($biz, (int) $teste->vendedor->id, 'Banner lona '.$i);
        if ($i > 0) {
            DB::table('pcp_produto_roteiro')->insert(['business_id' => $biz, 'product_id' => $p, 'roteiro_id' => $teste->roteiro,
                'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('transaction_sell_lines')->insert($linha($p, $v));
    }
    if ($semRoteiro) {
        [$p, $v] = przProduto($biz, (int) $teste->vendedor->id, 'Instalação');
        DB::table('transaction_sell_lines')->insert($linha($p, $v));
    }

    return (int) $tx->id;
}

function przModulo($teste, bool $ligado = true): void
{
    // partialMock é protegido no TestCase: chama no escopo do próprio teste.
    (fn () => $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $ligado && $perm === 'producao_module')))->call($teste);
}

it('UC-PCPRZ-01 · uma linha: a data soma a fila de cada posto e as etapas do roteiro, em sequência por bloco', function () {
    $tx = przVenda($this, $this->b98, 1);

    $p = app(PrazoPelaFila::class)->daVenda($this->b98, $tx);

    // Fila: impressora 16 h (só as 2 abertas da 98), acabamento 4 h; corte vazio.
    expect($p['fila_horas'])->toEqual([$this->impressora => 16.0, $this->acabamento => 4.0]);
    $et = collect($p['itens'][0]['etapas'])->keyBy('nome');
    // Laminação é opcional e fica de fora.
    expect($et->keys()->all())->toBe(['Impressão', 'Corte', 'Acabamento', 'Conferência']);
    // Impressão: livre em 16/8 = 2,0 dias; +4/8 = 2,5.
    expect([$et['Impressão']['inicio_dias'], $et['Impressão']['fim_dias']])->toEqual([2.0, 2.5]);
    // Bloco 2 começa em 2,5: Corte (padrão 8 h/dia) 2,5 + 8/8 = 3,5; Acabamento livre em 0,4 → começa 2,5, +2/10 = 2,7.
    expect([$et['Corte']['inicio_dias'], $et['Corte']['fim_dias']])->toEqual([2.5, 3.5]);
    expect([$et['Acabamento']['inicio_dias'], $et['Acabamento']['fim_dias']])->toEqual([2.5, 2.7]);
    // O bloco termina com a trilha mais longa (3,5); Conferência sem posto: 3,5 + 4/8 = 4,0.
    expect([$et['Conferência']['inicio_dias'], $et['Conferência']['fim_dias']])->toEqual([3.5, 4.0]);
    expect($p['dias'])->toEqual(4.0)->and($p['data_prevista'])->toBe('2026-10-24');
    expect(implode(' | ', $p['premissas']))->toContain('Posto Corte sem horas por dia: considerado 8 h')
        ->toContain('Etapa sem posto');
});

it('UC-PCPRZ-02 · duas linhas disputam os mesmos postos; linha sem roteiro não entra no prazo', function () {
    $tx = przVenda($this, $this->b98, 2, true);

    $p = app(PrazoPelaFila::class)->daVenda($this->b98, $tx);

    expect($p['itens'])->toHaveCount(2)->and($p['sem_roteiro'])->toHaveCount(1);
    $et = collect($p['itens'][1]['etapas'])->keyBy('nome');
    // 2ª linha: Impressão espera a 1ª (2,5) → 3,0; Corte espera a 1ª (3,5) → 4,5;
    // Acabamento livre em 2,7, bloco começa 3,0 → 3,2; Conferência 4,5 → 5,0.
    expect([$et['Impressão']['inicio_dias'], $et['Impressão']['fim_dias']])->toEqual([2.5, 3.0]);
    expect([$et['Corte']['inicio_dias'], $et['Corte']['fim_dias']])->toEqual([3.5, 4.5]);
    expect([$et['Acabamento']['inicio_dias'], $et['Acabamento']['fim_dias']])->toEqual([3.0, 3.2]);
    expect($p['itens'][1]['fim_dias'])->toEqual(5.0);
    expect($p['dias'])->toEqual(5.0)->and($p['data_prevista'])->toBe('2026-10-25');
});

it('UC-PCPRZ-03 · venda já aprovada não entra na fila de si mesma, e o cálculo não grava nada', function () {
    $tx = przVenda($this, $this->b98, 1);
    $linha = (int) DB::table('transaction_sell_lines')->where('transaction_id', $tx)->value('id');
    $os = (int) DB::table('pcp_ordens')->insertGetId(['business_id' => $this->b98, 'transaction_id' => $tx, 'transaction_sell_line_id' => $linha,
        'product_id' => $this->produto, 'quantidade' => 1, 'roteiro_id' => $this->roteiro, 'created_at' => now(), 'updated_at' => now()]);
    // A própria OS tem 8 h programadas na impressora: não pode virar fila (seria 24 h → 3,0 dias).
    ($this->prog)($this->b98, $this->impressora, 8, 'pendente', 'pcp_ordem', $os);
    $antes = [DB::table('pcp_programacoes')->count(), DB::table('transactions')->where('id', $tx)->value('updated_at')];

    $p = app(PrazoPelaFila::class)->daVenda($this->b98, $tx);

    expect($p['fila_horas'][$this->impressora])->toEqual(16.0)->and($p['dias'])->toEqual(4.0);
    expect([DB::table('pcp_programacoes')->count(), DB::table('transactions')->where('id', $tx)->value('updated_at')])->toBe($antes);
});

it('UC-PCPRZ-04 · quem vende ou quem produz consulta; sem permissão 403; venda de outra empresa 404', function () {
    przModulo($this);
    $tx = przVenda($this, $this->b98, 1);
    $operador = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $outro = $this->usuarioComPermissoes(['product.view'], $this->B98);

    $this->actingAs($this->vendedor)->getJson("/producao/vendas/{$tx}/prazo")->assertOk()
        ->assertJsonPath('data_prevista', '2026-10-24')->assertJsonPath('transaction_id', $tx);
    $this->actingAs($operador)->getJson("/producao/vendas/{$tx}/prazo")->assertOk()->assertJsonPath('data_prevista', '2026-10-24');
    $this->actingAs($outro)->getJson("/producao/vendas/{$tx}/prazo")->assertForbidden();

    // Venda da 99 (com o produto e o roteiro da 98): só o filtro da venda por empresa barra.
    $alheia = przVenda($this, $this->b99, 1);
    $this->actingAs($this->vendedor)->getJson("/producao/vendas/{$alheia}/prazo")->assertNotFound();
    expect(app(PrazoPelaFila::class)->daVenda($this->b98, $alheia))->toBeNull();

    // Sem o módulo no pacote, nem quem vende consulta.
    przModulo($this, false);
    $this->actingAs($this->vendedor)->getJson("/producao/vendas/{$tx}/prazo")->assertForbidden();
});
