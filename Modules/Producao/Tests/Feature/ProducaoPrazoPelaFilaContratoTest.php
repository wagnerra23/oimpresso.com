<?php
// @covers-us US-PCP-028

declare(strict_types=1);
// Cobre UC-PCPRZ-01..09 (Postos/Index.casos.md · US-PCP-028 · UC-FLX-06 — prazo de entrega pela fila,
// em dias úteis pelo período de funcionamento cadastrado no RH, e a data prometida gravada na
// aprovação — decisão [W] 2026-10-20).

use App\Contracts\Rh\CalendarioDaEmpresa;
use App\Transaction;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Jobs\CriarOsDaVendaJob;
use Modules\Producao\Services\CalendarioDeProducao;
use Modules\Producao\Services\CriarOsDaVenda;
use Modules\Producao\Services\PrazoPelaFila;
use Modules\Producao\Services\PrometerPrazoDaVenda;

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

    // Calendário conhecido: as datas esperadas são contadas à mão sobre 08h–18h, sábado e domingo de
    // folga e nenhum feriado. Limpa o que o seed tiver no RH e no turno das duas empresas (rollback no fim).
    foreach (['essentials_holidays', 'essentials_shifts', 'pcp_turnos'] as $tabela) {
        if (Schema::hasTable($tabela)) {
            DB::table($tabela)->whereIn('business_id', [$this->b98, $this->b99])->delete();
        }
    }

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
        DB::table('transaction_sell_lines')->insert($linha($teste->produto, $teste->variacao));
    }
    if ($semRoteiro) {
        [$p, $v] = przProduto($biz, (int) $teste->vendedor->id, 'Instalação');
        DB::table('transaction_sell_lines')->insert($linha($p, $v));
    }

    return (int) $tx->id;
}

function przModulo($teste, bool $ligado = true): void
{
    $teste->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $ligado && $perm === 'producao_module'));
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
    // 4,0 dias × 10 h do turno (08h–18h) = 40 h úteis a partir de ter 20/10 09:00: ter 9 h (→31), qua 10 (→21),
    // qui 10 (→11), sex 10 (→1), sáb e dom pulam, seg 26/10 08:00 + 1 h = 09:00.
    expect($p['dias'])->toEqual(4.0)->and($p['data_prevista'])->toBe('2026-10-26')->and($p['entrega_prevista'])->toBe('2026-10-26 09:00');
    expect(implode(' | ', $p['premissas']))->toContain('Posto Corte sem horas por dia: considerado 8 h')
        ->toContain('Etapa sem posto')->toContain('Sem turno da produção nem expediente no RH: considerado 08:00–18:00');
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
    // 50 h úteis: ter 9 (→41), qua (→31), qui (→21), sex (→11), seg 26 (→1), ter 27/10 08:00 + 1 h = 09:00.
    expect($p['dias'])->toEqual(5.0)->and($p['data_prevista'])->toBe('2026-10-27')->and($p['entrega_prevista'])->toBe('2026-10-27 09:00');
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
        ->assertJsonPath('data_prevista', '2026-10-26')->assertJsonPath('transaction_id', $tx);
    $this->actingAs($operador)->getJson("/producao/vendas/{$tx}/prazo")->assertOk()->assertJsonPath('data_prevista', '2026-10-26');
    $this->actingAs($outro)->getJson("/producao/vendas/{$tx}/prazo")->assertForbidden();

    // Venda da 99 (com o produto e o roteiro da 98): só o filtro da venda por empresa barra.
    $alheia = przVenda($this, $this->b99, 1);
    $this->actingAs($this->vendedor)->getJson("/producao/vendas/{$alheia}/prazo")->assertNotFound();
    expect(app(PrazoPelaFila::class)->daVenda($this->b98, $alheia))->toBeNull();

    // Sem o módulo no pacote, nem quem vende consulta.
    przModulo($this, false);
    $this->actingAs($this->vendedor)->getJson("/producao/vendas/{$tx}/prazo")->assertForbidden();
});

/** Feriado no RH (Essentials) — `$loja` nulo = da empresa toda. */
function przFeriado(int $biz, string $de, ?string $ate = null, ?int $loja = null): void
{
    DB::table('essentials_holidays')->insert(['name' => 'Feriado PRZ', 'start_date' => $de, 'end_date' => $ate ?? $de, 'business_id' => $biz,
        'location_id' => $loja, 'created_at' => now(), 'updated_at' => now()]);
}

function przEm(int $biz, ?int $loja, string $inicio, float $horas): string
{
    $cal = app(CalendarioDeProducao::class);

    return $cal->somarHoras($cal->regras($biz, $loja, Carbon::parse($inicio)), Carbon::parse($inicio), $horas)->format('Y-m-d H:i');
}

it('UC-PCPRZ-05 · sem nada cadastrado: 08h–18h, folga no sábado e no domingo, e as horas contam só no turno', function () {
    // Sex 30/10 16:00 + 6 h: sex 16→18 (2 h, faltam 4), sáb 31 e dom 01/11 pulam, seg 02/11 08:00 + 4 h = 12:00.
    // Nada no código sabe que 02/11 é Finados: feriado é o que a empresa cadastra no RH (UC-PCPRZ-06).
    expect(przEm($this->b98, null, '2026-10-30 16:00', 6))->toBe('2026-11-02 12:00');
    // Antes da abertura começa às 08:00; depois do fechamento, no dia útil seguinte; fechar às 18:00 fica no dia.
    expect(przEm($this->b98, null, '2026-10-26 06:00', 3))->toBe('2026-10-26 11:00');
    expect(przEm($this->b98, null, '2026-10-20 19:30', 1))->toBe('2026-10-21 09:00');
    expect(przEm($this->b98, null, '2026-10-20 09:00', 9))->toBe('2026-10-20 18:00');
    expect(implode(' | ', app(CalendarioDeProducao::class)->regras($this->b98, null, now())['premissas']))
        ->toContain('Sem expediente no RH: folga no sábado e no domingo');
});

it('UC-PCPRZ-06 · feriados do RH: o da empresa e o da loja da venda param; o de outra loja e o de outra empresa não', function () {
    if (! app()->bound(CalendarioDaEmpresa::class)) {
        $this->markTestSkipped('Essentials desligado: o contrato do RH não está ligado.');
    }
    $loja = (int) DB::table('business_locations')->where('business_id', $this->b98)->value('id');
    if ($loja === 0) {
        $this->markTestSkipped('Tenant 98 sem loja no seed.');
    }
    przFeriado($this->b98, '2026-11-02');                         // empresa toda
    przFeriado($this->b98, '2026-11-03', null, $loja);            // só a loja da venda
    przFeriado($this->b98, '2026-11-04', null, $loja + 1000000);  // outra loja
    przFeriado($this->b99, '2026-11-04', '2026-11-06');           // outra empresa, 3 dias

    // Na loja: sex 16→18 (2 h), sáb, dom, seg 02 (empresa) e ter 03 (loja) pulam; qua 04/11 08:00 + 4 h = 12:00.
    expect(przEm($this->b98, $loja, '2026-10-30 16:00', 6))->toBe('2026-11-04 12:00');
    // Sem loja só vale o da empresa: ter 03/11 08:00 + 4 h = 12:00.
    expect(przEm($this->b98, null, '2026-10-30 16:00', 6))->toBe('2026-11-03 12:00');
    // Na 99 os feriados da 98 não valem: seg 02/11 08:00 + 4 h = 12:00; os dela vêm dia a dia.
    expect(przEm($this->b99, null, '2026-10-30 16:00', 6))->toBe('2026-11-02 12:00');
    expect(app(CalendarioDaEmpresa::class)->diasNaoUteis($this->b99, null, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')))
        ->toBe(['2026-11-04', '2026-11-05', '2026-11-06']);

    // A venda leva a loja dela para o prazo: 4,0 dias (40 h) desde ter 20/10 09:00 dariam seg 26/10 09:00
    // (UC-PCPRZ-01); com feriado da loja na seg 26/10, ter 27/10 09:00.
    przFeriado($this->b98, '2026-10-26', null, $loja);
    $tx = przVenda($this, $this->b98, 1);
    DB::table('transactions')->where('id', $tx)->update(['location_id' => $loja]);
    expect(app(PrazoPelaFila::class)->daVenda($this->b98, $tx)['entrega_prevista'])->toBe('2026-10-27 09:00');
});

it('UC-PCPRZ-07 · hora útil: turno da produção, senão expediente do RH; folgas do expediente', function () {
    if (! app()->bound(CalendarioDaEmpresa::class)) {
        $this->markTestSkipped('Essentials desligado: o contrato do RH não está ligado.');
    }
    $turno = fn (string $nome, string $tipo, ?string $ini, ?string $fim, array $folgas) => DB::table('essentials_shifts')->insert(['name' => $nome,
        'type' => $tipo, 'business_id' => $this->b98, 'start_time' => $ini, 'end_time' => $fim, 'holidays' => json_encode($folgas),
        'created_at' => now(), 'updated_at' => now()]);
    $turno('Flexível', 'flexible_shift', null, null, ['saturday']);   // sem horário: não conta
    $turno('Comercial', 'fixed_shift', '07:00:00', '12:00:00', ['sunday']);

    // Expediente 07h–12h (5 h), folga só no domingo: sex 16:00 já fechou; sáb 31/10 07→12 (5 h, falta 1);
    // dom folga; seg 02/11 07:00 + 1 h = 08:00.
    $cal = app(CalendarioDeProducao::class);
    expect(przEm($this->b98, null, '2026-10-30 16:00', 6))->toBe('2026-11-02 08:00');
    expect($cal->horasDoTurno($cal->regras($this->b98, null, now())))->toEqual(5.0);

    // O turno da produção manda na hora (08h–18h); as folgas seguem as do expediente: sex 16→18 (2 h, faltam 4),
    // sáb 31/10 08:00 + 4 h = 12:00.
    DB::table('pcp_turnos')->insert(['business_id' => $this->b98, 'inicio' => '08:00:00', 'fim' => '18:00:00', 'created_at' => now(), 'updated_at' => now()]);
    expect(przEm($this->b98, null, '2026-10-30 16:00', 6))->toBe('2026-10-31 12:00');
    // A outra empresa não vê o expediente da 98: sábado e domingo de folga → seg 02/11 12:00.
    expect(przEm($this->b99, null, '2026-10-30 16:00', 6))->toBe('2026-11-02 12:00');
});

it('UC-PCPRZ-08 · na aprovação a estimativa vai para a venda sem data e para a OS; data digitada fica', function () {
    // Sem data: a aprovação (job das OS) grava 26/10 na venda e copia na OS criada.
    $tx = przVenda($this, $this->b98, 1);
    (new CriarOsDaVendaJob($this->b98, $tx))->handle(app(CriarOsDaVenda::class), app(PrometerPrazoDaVenda::class));
    expect(substr((string) DB::table('transactions')->where('id', $tx)->value('due_date'), 0, 10))->toBe('2026-10-26');
    $prazos = DB::table('pcp_ordens')->where('business_id', $this->b98)->where('transaction_id', $tx)->pluck('prazo_prometido');
    expect($prazos)->toHaveCount(1)->and(substr((string) $prazos[0], 0, 10))->toBe('2026-10-26');

    // Com data digitada pelo vendedor: não sobrescreve; a OS recebe a data dele.
    $comData = przVenda($this, $this->b98, 1);
    DB::table('transactions')->where('id', $comData)->update(['due_date' => '2026-11-30']);
    $linha = (int) DB::table('transaction_sell_lines')->where('transaction_id', $comData)->value('id');
    DB::table('pcp_ordens')->insert(['business_id' => $this->b98, 'transaction_id' => $comData, 'transaction_sell_line_id' => $linha,
        'product_id' => $this->produto, 'quantidade' => 1, 'roteiro_id' => $this->roteiro, 'created_at' => now(), 'updated_at' => now()]);
    expect(app(PrometerPrazoDaVenda::class)->gravar($this->b98, $comData))->toBe('2026-11-30');
    expect(substr((string) DB::table('transactions')->where('id', $comData)->value('due_date'), 0, 10))->toBe('2026-11-30');
    expect(substr((string) DB::table('pcp_ordens')->where('transaction_id', $comData)->value('prazo_prometido'), 0, 10))->toBe('2026-11-30');

    // Venda de outra empresa pedida pela 98: nada muda.
    $alheia = przVenda($this, $this->b99, 1);
    expect(app(PrometerPrazoDaVenda::class)->gravar($this->b98, $alheia))->toBeNull();
    expect(DB::table('transactions')->where('id', $alheia)->value('due_date'))->toBeNull();
});

it('UC-PCPRZ-09 · gravar a data prometida não muda o vencimento que o Financeiro calcula', function () {
    // Regra mestre de valor: `transactions.due_date` (coluna) é a data prometida; o vencimento do
    // título vem de `$tx->due_date`, que é o accessor `Transaction::getDueDateAttribute`
    // (data da venda + condição de pagamento). Mede os dois cálculos de vencimento do Financeiro
    // antes e depois da gravação, numa venda a 30 dias e numa sem condição de pagamento.
    $titulo = app(\Modules\Financeiro\Services\TituloAutoService::class);
    $job = (new ReflectionClass(\Modules\Financeiro\Jobs\CriarTituloDeVendaJob::class))->newInstanceWithoutConstructor();
    $vencimentos = function (int $id) use ($titulo, $job): array {
        $tx = Transaction::query()->where('business_id', $this->b98)->findOrFail($id);
        $priv = fn (object $obj) => (fn (Transaction $t) => $this->calcularVencimento($t))->call($obj, $tx);

        return [Carbon::parse($tx->due_date)->toDateString(), $priv($titulo), $priv($job)];
    };

    $aPrazo = przVenda($this, $this->b98, 1);
    DB::table('transactions')->where('id', $aPrazo)->update(['status' => 'final', 'pay_term_number' => 30, 'pay_term_type' => 'days',
        'transaction_date' => '2026-10-20 09:00:00']);
    $semCondicao = przVenda($this, $this->b98, 1);
    DB::table('transactions')->where('id', $semCondicao)->update(['status' => 'final', 'transaction_date' => '2026-10-20 09:00:00']);

    $antes = [$aPrazo => $vencimentos($aPrazo), $semCondicao => $vencimentos($semCondicao)];
    expect($antes[$aPrazo])->toBe(['2026-11-19', '2026-11-19', '2026-11-19'])
        ->and($antes[$semCondicao])->toBe(['2026-10-20', '2026-10-20', '2026-10-20']);

    foreach ([$aPrazo, $semCondicao] as $id) {
        // A gravação aconteceu: a coluna recebeu a data prometida (26/10, a mesma do UC-PCPRZ-08).
        expect(app(PrometerPrazoDaVenda::class)->gravar($this->b98, $id))->toBe('2026-10-26');
        expect(substr((string) DB::table('transactions')->where('id', $id)->value('due_date'), 0, 10))->toBe('2026-10-26');
        // E o vencimento do Financeiro não mudou em nenhum dos três caminhos.
        expect($vencimentos($id))->toBe($antes[$id]);
    }
});
