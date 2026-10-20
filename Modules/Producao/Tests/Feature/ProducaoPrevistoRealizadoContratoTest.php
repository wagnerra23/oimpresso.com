<?php
// @covers-us US-COMVIS-005

declare(strict_types=1);
// Cobre UC-PCPR-01..05 (Producao/Apontamento/Index.casos.md) — UC-FLX-15 do catálogo de fluxos.

use App\Domain\Fsm\Models\SaleProcessStage;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\PrevistoRealizadoDaOrdem;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-COMVIS-005 · UC-FLX-15 — previsto × realizado por OS e por venda. Só leitura de VALOR (regra
 * mestre): cada número é feito à mão abaixo E recalculado no teste a partir das linhas cruas do banco
 * (`recalcular()`, sem o serviço); "nada foi gravado" é medido.
 *
 * Arranjo, tenant 98, uma venda com três linhas e uma OS por linha:
 *   L1  4 × 25,00 = 100,00 · custo guardado 12,50 → orçado 50,00 · OS concluída, congelado 62,00
 *       diferença +12,00 = +24,00% · margem orçada 50/100 = 50,00% · margem real 38/100 = 38,00%
 *   L2  2 × 30,00 = 60,00 · custo guardado 10,00 → orçado 20,00 · OS em execução, 7,50 de material
 *       diferença −12,50 = −62,50% · margem orçada 40/60 = 66,67% · margem real 52,5/60 = 87,50%
 *   L3  1 × 1000,00 · custo 900,00 · OS cancelada → fora do total
 *   venda: receita 160,00 · orçado 70,00 · realizado 69,50 · diferença −0,50 = −0,71%
 *          margem orçada 90/160 = 56,25% · margem real 90,5/160 = 56,5625 → 56,56% · em andamento
 *
 * ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_consumos') || ! Schema::hasColumn('transaction_sell_lines', 'custo_previsto')) {
        $this->markTestSkipped('pcp_consumos ou transaction_sell_lines.custo_previsto ausente.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $processo = app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->etapa = fn (string $chave) => (int) SaleProcessStage::where('process_id', $processo->id)->where('key', $chave)->value('id');
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->gestor = $this->usuarioComPermissoes(['producao.access', 'producao.desempenho.ver'], $this->B98);

    $unit = (int) (DB::table('units')->where('business_id', $this->b98)->value('id') ?? DB::table('units')->insertGetId([
        'business_id' => $this->b98, 'actual_name' => 'Unidade', 'short_name' => 'un', 'allow_decimal' => 1, 'created_by' => $this->op->id,
        'created_at' => now(), 'updated_at' => now()]));
    $sku = 'PPR-'.uniqid();
    $this->pid = DB::table('products')->insertGetId(['business_id' => $this->b98, 'name' => 'Banner '.$sku, 'type' => 'single', 'unit_id' => $unit,
        'sku' => $sku, 'enable_stock' => 0, 'alert_quantity' => 0, 'tax_type' => 'exclusive', 'barcode_type' => 'C128',
        'created_by' => $this->op->id, 'created_at' => now(), 'updated_at' => now()]);
    $grupo = DB::table('product_variations')->insertGetId(['product_id' => $this->pid, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->vid = DB::table('variations')->insertGetId(['product_id' => $this->pid, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 25, 'sell_price_inc_tax' => 25,
        'created_at' => now(), 'updated_at' => now()]);

    $this->venda = fn (int $biz) => DB::table('transactions')->insertGetId(['business_id' => $biz, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 0, 'essentials_duration' => 0, 'created_by' => $this->op->id,
        'created_at' => now(), 'updated_at' => now()]);
    $this->linha = fn (int $venda, string $qtd, string $preco, ?string $custo) => DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda, 'product_id' => $this->pid, 'variation_id' => $this->vid, 'quantity' => $qtd, 'unit_price' => $preco,
        'unit_price_inc_tax' => $preco, 'unit_price_before_discount' => $preco, 'item_tax' => 0, 'custo_previsto' => $custo,
        'created_at' => now(), 'updated_at' => now()]);
    // SUPERADMIN: fixture da empresa 98 (a OS nasce já na situação do caso; o motor só barra UPDATE).
    $this->os = fn (int $biz, int $venda, int $linha, string $situacao, ?string $custoReal = null) => Ordem::withoutGlobalScopes()->create([
        'business_id' => $biz, 'transaction_id' => $venda, 'transaction_sell_line_id' => $linha, 'product_id' => $this->pid,
        'quantidade' => '1.0000', 'current_stage_id' => $biz === $this->b98 ? ($this->etapa)($situacao) : null, 'custo_real' => $custoReal]);

    $this->v = ($this->venda)($this->b98);
    $this->o1 = ($this->os)($this->b98, $this->v, ($this->linha)($this->v, '4', '25.00', '12.5000'), 'concluida', '62.0000');
    $this->o2 = ($this->os)($this->b98, $this->v, ($this->linha)($this->v, '2', '30.00', '10.0000'), 'em_execucao');
    $this->o3 = ($this->os)($this->b98, $this->v, ($this->linha)($this->v, '1', '1000.00', '900.0000'), 'cancelada');

    $posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'PPR-'.uniqid(), 'nome' => 'Impressão',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $prog = Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->o2->id,
        'posto_id' => $posto->id, 'bloco' => 1, 'trilha' => 0, 'nome' => 'Impressão', 'user_id' => $this->op->id,
        'inicio_previsto' => now()->subHour(), 'fim_previsto' => now(), 'status' => 'iniciada', 'created_by' => $this->op->id]);
    DB::table('pcp_consumos')->insert(['business_id' => $this->b98, 'chave' => 'ppr-'.uniqid(), 'ordem_id' => $this->o2->id,
        'programacao_id' => $prog->id, 'product_id' => $this->pid, 'variation_id' => $this->vid, 'quantidade' => '1.5000', 'custo' => '7.5000',
        'transaction_id' => $this->v, 'transaction_sell_line_id' => $this->o2->transaction_sell_line_id, 'user_id' => $this->op->id,
        'momento' => now(), 'created_at' => now(), 'updated_at' => now()]);

    // O que está gravado: custo congelado das OS e custo guardado das linhas.
    $this->gravado = fn () => [
        DB::table('pcp_ordens')->where('business_id', $this->b98)->where('transaction_id', $this->v)->orderBy('id')->pluck('custo_real')->all(),
        DB::table('transaction_sell_lines')->where('transaction_id', $this->v)->orderBy('id')->pluck('custo_previsto')->all(),
        DB::table('pcp_consumos')->where('ordem_id', $this->o2->id)->pluck('custo')->all(),
    ];
});

/** Caminho 2: a mesma conta a partir das linhas cruas, sem o serviço. Valores em décimos de milésimo. */
function pprDm(string $v): int
{
    [$i, $f] = array_pad(explode('.', $v, 2), 2, '');

    return (int) $i * 10000 + (int) substr(str_pad($f, 4, '0'), 0, 4);
}

function pprPct(int $num, int $den): string
{
    $c = (int) round(abs($num) * 10000 / $den, 0, PHP_ROUND_HALF_UP);

    return ($num < 0 ? '-' : '').sprintf('%d.%02d', intdiv($c, 100), $c % 100);
}

/** @return array{receita: int, orcado: int, realizado: int} */
function pprRecalcular(int $ordemId, string $realizado): array
{
    $l = DB::table('pcp_ordens as o')->join('transaction_sell_lines as l', 'l.id', '=', 'o.transaction_sell_line_id')
        ->where('o.id', $ordemId)->first(['l.quantity', 'l.unit_price', 'l.custo_previsto']);
    $q = pprDm((string) $l->quantity);

    return ['receita' => intdiv($q * pprDm((string) $l->unit_price), 10000), 'orcado' => intdiv($q * pprDm((string) $l->custo_previsto), 10000),
        'realizado' => pprDm($realizado)];
}

it('UC-PCPR-01 · OS concluída: orçado × congelado, diferença e margem, provados por dois caminhos', function () {
    $antes = ($this->gravado)();

    $r = app(PrevistoRealizadoDaOrdem::class)->daOrdem($this->b98, $this->o1->id);

    expect($r)->toMatchArray(['status' => 'realizado', 'situacao' => 'concluida', 'receita' => '100.0000', 'orcado' => '50.0000',
        'realizado' => '62.0000', 'diferenca' => '12.0000', 'diferenca_pct' => '24.00', 'margem_orcada_pct' => '50.00', 'margem_real_pct' => '38.00']);
    $c = pprRecalcular($this->o1->id, (string) $this->o1->fresh()->custo_real);
    expect($r['diferenca_pct'])->toBe(pprPct($c['realizado'] - $c['orcado'], $c['orcado']))
        ->and($r['margem_real_pct'])->toBe(pprPct($c['receita'] - $c['realizado'], $c['receita']));
    expect(($this->gravado)())->toBe($antes);
});

it('UC-PCPR-02 · OS aberta mostra o parcial de hoje como "em andamento"; cancelada não tem realizado', function () {
    $antes = ($this->gravado)();

    $r = app(PrevistoRealizadoDaOrdem::class)->daOrdem($this->b98, $this->o2->id);
    expect($r)->toMatchArray(['status' => 'em_andamento', 'situacao' => 'em_execucao', 'orcado' => '20.0000', 'realizado' => '7.5000',
        'diferenca' => '-12.5000', 'diferenca_pct' => '-62.50', 'margem_orcada_pct' => '66.67', 'margem_real_pct' => '87.50']);
    $c = pprRecalcular($this->o2->id, '7.5000');
    expect($r['margem_orcada_pct'])->toBe(pprPct($c['receita'] - $c['orcado'], $c['receita']));
    expect($this->o2->fresh()->custo_real)->toBeNull();                       // o parcial não congela

    $x = app(PrevistoRealizadoDaOrdem::class)->daOrdem($this->b98, $this->o3->id);
    expect($x)->toMatchArray(['status' => 'cancelada', 'realizado' => null, 'diferenca' => null, 'margem_real_pct' => null]);
    expect(($this->gravado)())->toBe($antes);
});

it('UC-PCPR-03 · a venda soma as OS não canceladas e fica "em andamento" enquanto alguma não concluiu', function () {
    $antes = ($this->gravado)();

    $r = app(PrevistoRealizadoDaOrdem::class)->daVenda($this->b98, $this->v);

    expect($r['ordens'])->toHaveCount(3);
    expect($r['total'])->toMatchArray(['status' => 'em_andamento', 'receita' => '160.0000', 'orcado' => '70.0000', 'realizado' => '69.5000',
        'diferenca' => '-0.5000', 'diferenca_pct' => '-0.71', 'margem_orcada_pct' => '56.25', 'margem_real_pct' => '56.56', 'sem_orcado' => false]);
    $c1 = pprRecalcular($this->o1->id, '62.0000');
    $c2 = pprRecalcular($this->o2->id, '7.5000');
    $receita = $c1['receita'] + $c2['receita'];
    $orcado = $c1['orcado'] + $c2['orcado'];
    $real = $c1['realizado'] + $c2['realizado'];
    expect($r['total']['diferenca_pct'])->toBe(pprPct($real - $orcado, $orcado))
        ->and($r['total']['margem_real_pct'])->toBe(pprPct($receita - $real, $receita));
    expect(($this->gravado)())->toBe($antes);
});

it('UC-PCPR-04 · linha sem custo guardado: sem orçado, sem diferença, e a venda avisa', function () {
    $v = ($this->venda)($this->b98);
    $o = ($this->os)($this->b98, $v, ($this->linha)($v, '1', '80.00', null), 'concluida', '30.0000');

    $r = app(PrevistoRealizadoDaOrdem::class)->daOrdem($this->b98, $o->id);
    expect($r)->toMatchArray(['orcado' => null, 'diferenca' => null, 'diferenca_pct' => null, 'margem_orcada_pct' => null,
        'realizado' => '30.0000', 'margem_real_pct' => '62.50']);
    expect(app(PrevistoRealizadoDaOrdem::class)->daVenda($this->b98, $v)['total'])->toMatchArray(['orcado' => null, 'sem_orcado' => true]);
});

it('UC-PCPR-05 · custo é do gestor: sem permissão 403; OS ou venda da empresa 99 → 404', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));

    $this->actingAs($this->gestor)->getJson("/producao/ordens/{$this->o1->id}/previsto-realizado")->assertOk()
        ->assertJson(['orcado' => '50.0000', 'realizado' => '62.0000', 'margem_real_pct' => '38.00']);
    $this->actingAs($this->gestor)->getJson("/producao/vendas/{$this->v}/previsto-realizado")->assertOk()
        ->assertJsonPath('total.realizado', '69.5000');
    $this->actingAs($this->op)->getJson("/producao/ordens/{$this->o1->id}/previsto-realizado")->assertForbidden();
    $this->actingAs($this->op)->getJson("/producao/vendas/{$this->v}/previsto-realizado")->assertForbidden();

    $b99 = (int) $this->seededSupportClientTenant()->id;
    $v99 = ($this->venda)($b99);
    // SUPERADMIN: OS da empresa 99, para provar o 404.
    $o99 = ($this->os)($b99, $v99, ($this->linha)($v99, '1', '10.00', '5.0000'), 'concluida', '5.0000');
    $this->actingAs($this->gestor)->getJson("/producao/ordens/{$o99->id}/previsto-realizado")->assertNotFound();
    $this->actingAs($this->gestor)->getJson("/producao/vendas/{$v99}/previsto-realizado")->assertNotFound();
});
