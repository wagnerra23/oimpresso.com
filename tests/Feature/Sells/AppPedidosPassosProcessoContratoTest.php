<?php

declare(strict_types=1);

use Database\Seeders\FsmProcessoComunicacaoVisualSeeder;
use Database\Seeders\FsmProcessoVendaComProducaoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\Contract\AutosaveContractRunner;

/**
 * Passos do pedido no app vindos do processo FSM (decisão [W] 2026-10-16).
 *
 * Contrato: `passos[{chave,rotulo}]` em ordem, `passo_atual`, `etapa.terminal`,
 * `etapa.cancelada`, filtro `cancelados` (e `concluidos` sem as canceladas), colunas da
 * Produção das etapas marcadas no processo — e `etapa.grupo` com os valores ANTIGOS, porque
 * há versões do app em campo que leem só ele. Os passos esperados vêm da decisão de [W]
 * (venda) e da proposta de agrupamento da OS Comunicação Visual descrita no PR, não do código.
 */
uses(DatabaseTransactions::class);

const PASSOS_VENDA = ['orcamento', 'aprovado', 'producao', 'faturamento', 'entrega', 'concluido'];
const PASSOS_CV = ['orcamento', 'aprovado', 'arte', 'producao', 'instalacao', 'entregue'];

/** Põe a venda na etapa `$etapa` do processo `$processo` do business (UPDATE cru, sem Observer). */
function appPassosMover(int $biz, int $venda, string $processo, string $etapa): void
{
    $stage = DB::table('sale_process_stages as s')
        ->join('sale_processes as p', 'p.id', '=', 's.process_id')
        ->where('p.business_id', $biz)->where('p.key', $processo)->where('s.key', $etapa)
        ->value('s.id');
    expect($stage)->not->toBeNull();
    DB::table('transactions')->where('id', $venda)->update(['current_stage_id' => $stage]);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasColumn('sale_process_stages', 'passo')) {
        $this->markTestSkipped('Migration de passos ausente.');
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = (int) $ctx['business']->id;
    $this->user = $ctx['user'];
    $this->venda = (int) $ctx['transactionId'];
    $this->outroBiz = (int) (\App\Business::where('id', '!=', $this->biz)->value('id') ?? 0);
    if ($this->outroBiz === 0) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    foreach (['direct_sell.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }
    DB::table('transactions')->where('id', $this->venda)->update([
        'status' => 'final', 'sub_type' => null,
        // Prazo bem antigo: a lista ordena por prazo, então a venda fica na 1ª página.
        'delivery_date' => '2000-01-01 00:00:00',
    ]);

    foreach ([$this->biz, $this->outroBiz] as $b) {
        (new FsmProcessoVendaComProducaoSeeder())->runForBusiness($b);
        (new FsmProcessoComunicacaoVisualSeeder())->runForBusiness($b);
    }

    Passport::actingAs($this->user, [], 'api');
});

function appPassosItem($test, string $filtro): ?array
{
    return collect($test->getJson('/api/app/pedidos?filtro=' . $filtro)->assertOk()->json('itens'))
        ->firstWhere('id', $test->venda);
}

it('venda com produção: passos do processo em ordem, passo atual e grupo antigo preservado', function () {
    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'ready_for_invoice');

    $item = appPassosItem($this, 'ativos');
    expect($item)->not->toBeNull();
    expect(collect($item['passos'])->pluck('chave')->all())->toBe(PASSOS_VENDA);
    expect($item['passos'][3]['rotulo'])->toBe('Faturamento');
    expect($item['passo_atual'])->toBe('faturamento');
    expect($item['etapa']['grupo'])->toBe('entrega');
    expect($item['etapa']['terminal'])->toBeFalse();
    expect($item['etapa']['cancelada'])->toBeFalse();

    $det = $this->getJson('/api/app/pedidos/' . $this->venda)->assertOk();
    expect($det->json('passo_atual'))->toBe('faturamento');
    expect(collect($det->json('passos'))->pluck('chave')->all())->toBe(PASSOS_VENDA);
    expect(collect($det->json('etapas'))->firstWhere('grupo', 'entrega')['estado'])->toBe('atual');
});

it('em espera fica no passo Produção; grupo antigo segue "producao"', function () {
    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'on_hold');

    $item = appPassosItem($this, 'ativos');
    expect($item['passo_atual'])->toBe('producao');
    expect($item['etapa']['grupo'])->toBe('producao');
});

it('cancelada sai de "concluidos", entra em "cancelados", fora dos passos e com grupo antigo "cancelado"', function () {
    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'cancelled');

    expect(appPassosItem($this, 'concluidos'))->toBeNull();
    $item = appPassosItem($this, 'cancelados');
    expect($item)->not->toBeNull();
    expect($item['passo_atual'])->toBeNull();
    expect($item['etapa']['cancelada'])->toBeTrue();
    expect($item['etapa']['terminal'])->toBeTrue();
    expect($item['etapa']['grupo'])->toBe('cancelado');

    $r = $this->getJson('/api/app/pedidos')->assertOk();
    expect($r->json('contadores'))->toHaveKeys(['ativos', 'atrasados', 'concluidos', 'cancelados', 'todos']);
});

it('concluída entra em "concluidos" e não em "cancelados"', function () {
    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'completed');

    expect(appPassosItem($this, 'cancelados'))->toBeNull();
    $item = appPassosItem($this, 'concluidos');
    expect($item)->not->toBeNull();
    expect($item['passo_atual'])->toBe('concluido');
    expect($item['etapa']['grupo'])->toBe('concluido');
});

it('filtro desconhecido continua caindo em ativos', function () {
    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'in_production');
    expect(appPassosItem($this, 'qualquer'))->not->toBeNull();
});

it('OS Comunicação Visual: passos próprios, passo atual e grupo antigo derivado do passo', function () {
    appPassosMover($this->biz, $this->venda, 'os_comunicacao_visual', 'em_impressao');

    $item = appPassosItem($this, 'ativos');
    expect($item)->not->toBeNull();
    expect(collect($item['passos'])->pluck('chave')->all())->toBe(PASSOS_CV);
    expect($item['passo_atual'])->toBe('producao');
    expect($item['etapa']['grupo'])->toBe('producao');

    appPassosMover($this->biz, $this->venda, 'os_comunicacao_visual', 'cancelado');
    $cancelado = appPassosItem($this, 'cancelados');
    expect($cancelado['etapa']['cancelada'])->toBeTrue();
    expect($cancelado['etapa']['grupo'])->toBe('cancelado');
});

it('Produção: colunas vêm das etapas marcadas do processo em uso; a venda aparece na coluna da sua etapa', function () {
    appPassosMover($this->biz, $this->venda, 'os_comunicacao_visual', 'em_impressao');

    $colunas = collect($this->getJson('/api/app/producao')->assertOk()->json('colunas'));
    $ids = $colunas->pluck('id')->all();
    $cv = ['arte_aprovada', 'aguardando_maquina', 'em_impressao', 'impressao_concluida',
        'aguardando_acabamento', 'acabamento_concluido'];
    expect(array_values(array_intersect($ids, $cv)))->toBe($cv);
    expect($colunas->first())->toHaveKeys(['id', 'rotulo', 'total', 'itens']);
    expect(collect($colunas->firstWhere('id', 'em_impressao')['itens'])->pluck('id'))->toContain($this->venda);
});

it('cancelada de OUTRO business não aparece em "cancelados" (Tier 0)', function () {
    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $this->outroBiz, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'invoice_no' => 'APP-PASSO-ALHEIA-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 9, 'final_total' => 9,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    appPassosMover($this->outroBiz, $alheia, 'venda_com_producao', 'cancelled');
    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'cancelled');

    $ids = collect($this->getJson('/api/app/pedidos?filtro=cancelados')->assertOk()->json('itens'))->pluck('id');
    expect($ids)->toContain($this->venda);
    expect($ids)->not->toContain($alheia);
});

/** Ação do detalhe do pedido pela chave, ou null. */
function appPassosAcao($test, string $chave): ?array
{
    return collect($test->getJson('/api/app/pedidos/' . $test->venda)->assertOk()->json('acoes'))
        ->firstWhere('chave', $chave);
}

/** Título a receber de origem venda, gravado cru (sem Observer). */
function appPassosTitulo(int $biz, int $venda, int $userId, float $total, float $aberto): void
{
    DB::table('fin_titulos')->insert([
        'business_id' => $biz, 'numero' => 'APP-PAG-' . substr(uniqid(), -8), 'tipo' => 'receber',
        'status' => $aberto <= 0 ? 'quitado' : ($aberto < $total ? 'parcial' : 'aberto'),
        'valor_total' => $total, 'valor_aberto' => $aberto, 'emissao' => now()->toDateString(),
        'vencimento' => now()->toDateString(), 'competencia_mes' => now()->format('Y-m'),
        'origem' => 'venda', 'origem_id' => $venda, 'parcela_numero' => 1, 'parcela_total' => 1,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('detalhe: pede_motivo é true em pausar_producao e false em iniciar_producao', function () {
    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'in_production');
    $pausar = appPassosAcao($this, 'pausar_producao');
    expect($pausar)->not->toBeNull();
    expect($pausar['pede_motivo'])->toBeTrue();

    appPassosMover($this->biz, $this->venda, 'venda_com_producao', 'quote_approved');
    $iniciar = appPassosAcao($this, 'iniciar_producao');
    expect($iniciar)->not->toBeNull();
    expect($iniciar['pede_motivo'])->toBeFalse();
});

it('detalhe: pagamento sem_titulo sem fin_titulos; título de OUTRO business não vaza (Tier 0)', function () {
    if (! Schema::hasTable('fin_titulos')) {
        $this->markTestSkipped('fin_titulos ausente nesta lane.');
    }
    // Tira do caminho título que o Observer possa ter criado para a venda (soft delete; rollback no fim).
    DB::table('fin_titulos')->where('origem', 'venda')->where('origem_id', $this->venda)
        ->update(['deleted_at' => now()]);

    $pag = $this->getJson('/api/app/pedidos/' . $this->venda)->assertOk()->json('pagamento');
    expect($pag['situacao'])->toBe('sem_titulo');
    expect($pag['valor_aberto'])->toEqual(0.0);

    appPassosTitulo($this->outroBiz, $this->venda, (int) $this->user->id, 100, 100);
    $pag = $this->getJson('/api/app/pedidos/' . $this->venda)->assertOk()->json('pagamento');
    expect($pag['situacao'])->toBe('sem_titulo');

    // Controle positivo: o título do PRÓPRIO business aparece — o assert acima discrimina.
    appPassosTitulo($this->biz, $this->venda, (int) $this->user->id, 100, 40);
    $pag = $this->getJson('/api/app/pedidos/' . $this->venda)->assertOk()->json('pagamento');
    expect($pag['situacao'])->toBe('parcial');
    expect($pag['valor_aberto'])->toEqual(40.0);
});
