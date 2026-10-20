<?php
// @covers-us US-PCP-026

declare(strict_types=1);
// Cobre UC-FLX-01 (venda aprovada vira OS sem redigitar) e UC-FLX-07 (etapas vêm do produto) — ADR 0436, PR 3.
// UC-PCROT-01..04: Producao/Roteiros/Index.casos.md (US-PCP-026).

use App\Domain\Fsm\Modelos\AplicarModelo;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\CriarOsDaVenda;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: protótipo aprovado pelo [W] em 16/10/2026 (#9517) — processo "venda" de
 * fluxos-processos.jsx ("Cliente aprova → cria a OS por item") e a vista Roteiros. Tenant 98 × 99.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_ordens')) {
        $this->markTestSkipped('pcp_ordens ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->vendedor = $this->usuarioComPermissoes(['sell.view'], $this->B98);
});

/** Roteiro com bloco paralelo (2 trilhas que se juntam) e uma etapa opcional. */
function osRoteiro(int $biz): int
{
    $id = DB::table('pcp_roteiros')->insertGetId(['business_id' => $biz, 'familia' => 'Projeto', 'nome' => 'Fachada '.uniqid(), 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $e = fn ($bloco, $trilha, $ordem, $nome, $horas, $junta = null, $opc = null) => ['business_id' => $biz, 'roteiro_id' => $id, 'bloco' => $bloco,
        'trilha' => $trilha, 'ordem' => $ordem, 'nome' => $nome, 'horas' => $horas, 'junta' => $junta, 'opcional_por' => $opc, 'created_at' => now(), 'updated_at' => now()];
    DB::table('pcp_roteiro_etapas')->insert([
        $e(1, 0, 0, 'Arte', 1), $e(2, 1, 0, 'Estrutura', 6, 'Montagem'), $e(2, 2, 0, 'Adesivo', 1, 'Montagem'),
        $e(2, 2, 1, 'Laminação', 0.5, 'Montagem', 'com laminação'), $e(3, 0, 0, 'Montagem', 2),
    ]);

    return $id;
}

/** Unidade + produto + variação na empresa (mesmo formato do fixture do Manufacturing). @return array{0:int,1:int} */
function osProduto(int $biz, int $userId, string $nome): array
{
    $suf = random_int(100000, 999999);
    $unit = DB::table('units')->insertGetId(['business_id' => $biz, 'actual_name' => 'Unidade OS', 'short_name' => 'UO', 'allow_decimal' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    $prod = DB::table('products')->insertGetId(['business_id' => $biz, 'name' => $nome, 'type' => 'single', 'unit_id' => $unit, 'tax_type' => 'exclusive',
        'sku' => 'OSI-'.$suf, 'barcode_type' => 'C128', 'enable_stock' => 0, 'alert_quantity' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    $pv = DB::table('product_variations')->insertGetId(['product_id' => $prod, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $var = DB::table('variations')->insertGetId(['name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'OSI-'.$suf,
        'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0, 'created_at' => now(), 'updated_at' => now()]);

    return [$prod, $var];
}

/** Venda no processo de venda do modelo, na etapa Orçamento, com 2 linhas: uma com roteiro e outra sem. */
function osVenda($teste, string $modelo): Transaction
{
    $ids = app(AplicarModelo::class)->aplicar($teste->b98, $modelo);
    $teste->vendedor->assignRole('vendas.enviar#'.$teste->b98);
    $orc = SaleProcessStage::where('process_id', $ids['venda'])->where('is_initial', true)->firstOrFail();
    $tx = new Transaction();
    foreach (['business_id' => $teste->b98, 'current_stage_id' => $orc->id, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'contact_id' => 1, 'invoice_no' => 'OSI-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 100, 'final_total' => 100,
        'created_by' => $teste->vendedor->id] as $k => $v) {
        $tx->{$k} = $v;
    }
    $tx->save();
    [$p1, $v1] = osProduto($teste->b98, (int) $teste->vendedor->id, 'Fachada ACM');
    [$p2, $v2] = osProduto($teste->b98, (int) $teste->vendedor->id, 'Instalação');
    $teste->produtoComRoteiro = $p1;
    DB::table('transaction_sell_lines')->insert([
        ['transaction_id' => $tx->id, 'product_id' => $p1, 'variation_id' => $v1, 'quantity' => 2, 'unit_price' => 30, 'unit_price_inc_tax' => 33, 'created_at' => now(), 'updated_at' => now()],
        ['transaction_id' => $tx->id, 'product_id' => $p2, 'variation_id' => $v2, 'quantity' => 1, 'unit_price' => 40, 'unit_price_inc_tax' => 44, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('pcp_produto_roteiro')->insert(['business_id' => $teste->b98, 'product_id' => $p1, 'roteiro_id' => osRoteiro($teste->b98), 'created_at' => now(), 'updated_at' => now()]);

    return $tx;
}

function osOrdens(int $biz, int $txId)
{
    // SUPERADMIN: o teste confere a empresa explicitamente.
    return Ordem::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $biz)->where('transaction_id', $txId)->get();
}

it('UC-FLX-01 · UC-PCROT-02 · aprovar a venda cria uma OS só para o item com roteiro, em Aguardando liberação', function () {
    $tx = osVenda($this, 'cv-pequena');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);

    $ordens = osOrdens($this->b98, $tx->id);
    expect($tx->fresh()->current_stage_id)->not->toBeNull();
    expect($ordens)->toHaveCount(1);
    expect((int) $ordens[0]->product_id)->toBe($this->produtoComRoteiro);
    expect($ordens[0]->currentStage->name)->toBe('Aguardando liberação');
});

it('UC-FLX-07 · as programações vêm do roteiro: sem a opcional, e as duas trilhas começam juntas', function () {
    $tx = osVenda($this, 'cv-pequena');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);

    $prog = Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)
        ->where('os_tipo', Ordem::OS_TIPO)->where('os_id', osOrdens($this->b98, $tx->id)[0]->id)->get()->keyBy('nome');
    expect($prog->keys()->sort()->values()->all())->toBe(['Adesivo', 'Arte', 'Estrutura', 'Montagem']);
    expect($prog->every(fn ($p) => $p->status === 'pendente'))->toBeTrue();
    expect($prog['Estrutura']->inicio_previsto->equalTo($prog['Adesivo']->inicio_previsto))->toBeTrue();
    expect($prog['Estrutura']->junta)->toBe('Montagem');
    // A junta começa quando a trilha mais longa (6 h) termina.
    expect($prog['Montagem']->inicio_previsto->equalTo($prog['Estrutura']->fim_previsto))->toBeTrue();
});

it('idempotente: criar de novo não duplica OS nem programações', function () {
    $tx = osVenda($this, 'cv-pequena');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);
    $antes = Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('os_tipo', Ordem::OS_TIPO)->count();

    app(CriarOsDaVenda::class)->criar($this->b98, (int) $tx->id);

    expect(osOrdens($this->b98, $tx->id))->toHaveCount(1);
    expect(Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('os_tipo', Ordem::OS_TIPO)->count())->toBe($antes);
});

it('UC-PCROT-04 · Tier 0: a 99 não cria OS a partir da venda da 98', function () {
    $tx = osVenda($this, 'cv-pequena');
    // A 99 liga o mesmo produto a um roteiro dela: só o filtro da venda por empresa pode barrar.
    DB::table('pcp_produto_roteiro')->insert(['business_id' => $this->b99, 'product_id' => $this->produtoComRoteiro,
        'roteiro_id' => osRoteiro($this->b99), 'created_at' => now(), 'updated_at' => now()]);

    expect(app(CriarOsDaVenda::class)->criar($this->b99, (int) $tx->id))->toBe([]);
    expect(osOrdens($this->b99, $tx->id))->toHaveCount(0);
    expect(osOrdens($this->b98, $tx->id))->toHaveCount(0);
});

it('modelo sem produção (Balcão) não cria OS ao aprovar', function () {
    $tx = osVenda($this, 'balcao');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);

    expect(SaleProcessStage::find($tx->fresh()->current_stage_id)->key)->toBe('aprovado');
    expect(osOrdens($this->b98, $tx->id))->toHaveCount(0);
});

it('o histórico da venda não mostra transição de OS que tenha o mesmo número', function () {
    $tx = osVenda($this, 'cv-pequena');
    $etapaOs = app(ProcessoDaOrdem::class)->etapaInicial($this->b98);
    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create(['business_id' => $this->b98, 'transaction_id' => $tx->id,
        'action_id' => null, 'from_stage_id' => null, 'to_stage_id' => $etapaOs->id, 'executed_at' => now()]);

    $daVenda = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('transaction_id', $tx->id)->daVenda()->count();
    $todos = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('transaction_id', $tx->id)->count();
    expect($todos - $daVenda)->toBe(1);
});

/** Troca de usuário como um login novo (o SetSessionData só preenche a empresa com a sessão vazia). */
function osComo($teste, $user)
{
    $teste->flushSession();

    return $teste->actingAs($user);
}

function osProgramacoes(int $biz, int $ordemId)
{
    return Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $biz)
        ->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $ordemId)->orderBy('bloco')->orderBy('trilha')->orderBy('ordem')->get();
}

it('UC-PCROT-01 · o gestor liga o produto ao roteiro na tela, e a OS nasce com as etapas dele: ordem, posto e tempo previsto', function () {
    $tx = osVenda($this, 'cv-pequena');
    $gestor = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    // SUPERADMIN: fixture grava o posto na empresa do teste.
    $posto = \Modules\Producao\Entities\Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'ROL-'.uniqid(),
        'nome' => 'Roland', 'tipo_custo' => 'maquina', 'custo_hora' => '42.00']);
    $nome = 'Adesivo '.uniqid();
    osComo($this, $gestor)->post('/producao/roteiros', ['familia' => 'Adesivo', 'nome' => $nome, 'etapas' => [
        ['nome' => 'Impressão', 'posto_id' => $posto->id, 'horas' => 1.5], ['nome' => 'Laminação', 'horas' => 0.5], ['nome' => 'Recorte', 'horas' => 1],
    ]])->assertSessionHasNoErrors();
    $roteiro = (int) DB::table('pcp_roteiros')->where('business_id', $this->b98)->where('nome', $nome)->value('id');

    // O produto já estava no roteiro da Fachada (osVenda): ligar a este o tira de lá (1 roteiro por produto).
    $this->put("/producao/roteiros/$roteiro/produtos", ['product_ids' => [$this->produtoComRoteiro]])->assertSessionHasNoErrors();
    expect(DB::table('pcp_produto_roteiro')->where('business_id', $this->b98)->where('product_id', $this->produtoComRoteiro)->pluck('roteiro_id')->all())
        ->toBe([$roteiro]);

    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);
    $ordem = osOrdens($this->b98, $tx->id)[0];
    $prog = osProgramacoes($this->b98, (int) $ordem->id);

    expect((int) $ordem->roteiro_id)->toBe($roteiro);
    expect($prog->pluck('nome')->all())->toBe(['Impressão', 'Laminação', 'Recorte']);
    expect((int) $prog[0]->posto_id)->toBe((int) $posto->id);
    expect($prog[1]->posto_id)->toBeNull();
    expect((int) $prog[0]->inicio_previsto->diffInMinutes($prog[0]->fim_previsto))->toBe(90);
    expect((int) $prog[2]->inicio_previsto->diffInMinutes($prog[2]->fim_previsto))->toBe(60);
    expect($prog[1]->inicio_previsto->equalTo($prog[0]->fim_previsto))->toBeTrue();
});

it('UC-PCROT-03 · editar o roteiro depois não muda a OS já criada', function () {
    $tx = osVenda($this, 'cv-pequena');
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);
    $ordem = osOrdens($this->b98, $tx->id)[0];
    $antes = osProgramacoes($this->b98, (int) $ordem->id)->map(fn ($p) => [$p->nome, $p->posto_id, (string) $p->inicio_previsto, (string) $p->fim_previsto])->all();

    $gestor = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    osComo($this, $gestor)->put('/producao/roteiros/'.$ordem->roteiro_id, ['familia' => 'Projeto', 'nome' => 'Fachada nova', 'etapas' => [
        ['nome' => 'Só pintura', 'horas' => 9],
    ]])->assertSessionHasNoErrors();

    // Caso discriminante: a edição valeu no roteiro…
    expect(DB::table('pcp_roteiro_etapas')->where('roteiro_id', $ordem->roteiro_id)->pluck('nome')->all())->toBe(['Só pintura']);
    // …e a OS ficou com a cópia do dia da aprovação.
    $depois = osProgramacoes($this->b98, (int) $ordem->id)->map(fn ($p) => [$p->nome, $p->posto_id, (string) $p->inicio_previsto, (string) $p->fim_previsto])->all();
    expect($depois)->toBe($antes)->and(count($depois))->toBe(4);
});

it('UC-PCROT-04 · produto ou roteiro de outra empresa nunca entra na OS', function () {
    $tx = osVenda($this, 'cv-pequena');
    $gestor = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $meu = (int) DB::table('pcp_produto_roteiro')->where('business_id', $this->b98)->where('product_id', $this->produtoComRoteiro)->value('roteiro_id');
    $daOutra = osRoteiro($this->b99);
    [$produto99] = osProduto($this->b99, (int) $this->vendedor->id, 'Produto da 99');

    // A 98 não liga produto ao roteiro da 99, nem liga produto da 99 ao roteiro dela.
    osComo($this, $gestor)->put("/producao/roteiros/$daOutra/produtos", ['product_ids' => [$this->produtoComRoteiro]])->assertNotFound();
    $this->put("/producao/roteiros/$meu/produtos", ['product_ids' => [$produto99]])->assertSessionHasErrors('product_ids.0');
    expect(DB::table('pcp_produto_roteiro')->where('product_id', $produto99)->count())->toBe(0);

    // Um vínculo da 98 apontando o roteiro da 99 conta como produto sem roteiro: nenhuma OS.
    DB::table('pcp_produto_roteiro')->where('business_id', $this->b98)->where('product_id', $this->produtoComRoteiro)->update(['roteiro_id' => $daOutra]);
    app(ExecuteStageActionService::class)->execute($tx, 'avancar', $this->vendedor);
    expect(osOrdens($this->b98, $tx->id))->toHaveCount(0);
    expect(Programacao::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('os_tipo', Ordem::OS_TIPO)
        ->whereIn('roteiro_etapa_id', DB::table('pcp_roteiro_etapas')->where('roteiro_id', $daOutra)->pluck('id'))->count())->toBe(0);
});
