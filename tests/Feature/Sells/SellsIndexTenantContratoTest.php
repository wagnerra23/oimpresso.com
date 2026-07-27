<?php

// @covers-us US-SELL-047

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contrato Tier 0 do payload da LISTA de vendas (`SellController@inertiaList`).
 *
 * UC-SIDX-01 `[T0]` — o indicador de devolução (`has_return`) só considera devoluções
 *                     DO MESMO business.
 * UC-SIDX-02 `[V0][T0]` — o totalizador de dinheiro do rodapé não soma venda de outro business.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE (o achado que o motivou)
 * ─────────────────────────────────────────────────────────────────────────────
 * A derivação de `has_return` é uma subquery CRUA, e o irmão legado é um JOIN cru:
 *
 *   SellController@inertiaList:
 *     (SELECT COUNT(*) FROM transactions sr
 *       WHERE sr.return_parent_id = transactions.id AND sr.type = 'sell_return')
 *   TransactionUtil::getSellsCurrentFy:
 *     leftjoin('transactions as SR', fn ($j) => $j->on('SR.return_parent_id','=','transactions.id')
 *                                                 ->where('SR.type','sell_return'))
 *
 * NENHUM dos dois filtra `business_id`, e `App\Transaction` NÃO tem global scope
 * (medido 2026-07-27: `grep -n "addGlobalScope" app/Transaction.php` = 0). Logo nada
 * supre o escopo — ele teria de ser explícito (ADR 0093).
 *
 * LIMITE HONESTO DE SEVERIDADE: `return_parent_id` não é controlado pelo usuário no fluxo
 * normal, então NÃO há vazamento provado. O que está medido é a AUSÊNCIA do escopo
 * (defesa em profundidade). Este teste transforma isso em contrato: escopar — ou voltar a
 * desescopar — deixa de ser mudança silenciosa.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ANTI-VÁCUO (a razão do controle positivo)
 * ─────────────────────────────────────────────────────────────────────────────
 * Um teste que só afirma "has_return é falso" passaria VERDE por motivos errados: campo
 * ausente do payload, linha não encontrada, request 403, filtro que zera tudo. Por isso
 * cada contrato roda em par:
 *   (A) CONTROLE POSITIVO — devolução do MESMO business ⇒ o indicador ACENDE.
 *       Se (A) falhar, o defeito é do setup/mecanismo, e fica VISÍVEL — não silencioso.
 *   (B) CONTRATO — devolução de OUTRO business ⇒ o indicador NÃO acende.
 * Só o par discrimina. (proibicoes.md §5 2026-07-24 — "verde por não-execução")
 *
 * Multi-tenant: usa o business seedado da lane (biz=1) como A e o segundo business (biz=2)
 * como B — nunca biz=4 (cliente ROTA LIVRE), conforme ADR 0101.
 *
 * @see app/Http/Controllers/SellController.php::inertiaList
 * @see resources/js/Pages/Sells/Index.casos.md (UC-SIDX-01 · UC-SIDX-02)
 * @see memory/requisitos/Sells/SDD-tela-venda-v1.0.md §5.3 F2 · §6.3 CU-SELL-32/33 · §9 D-1
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
uses(DatabaseTransactions::class);

/**
 * Localiza, na linha do payload, a chave que carrega o indicador de devolução —
 * SEM acoplar o contrato ao nome literal (o contrato é "o indicador", não "a chave X").
 * Devolve null quando nenhuma das formas conhecidas existe.
 */
function sidxFlagDevolucao(array $linha): ?bool
{
    foreach (['has_return', 'return_exists', 'hasReturn'] as $chave) {
        if (array_key_exists($chave, $linha)) {
            return (bool) $linha[$chave];
        }
    }

    return null;
}

/** Extrai o somatório de dinheiro do payload, sem acoplar a uma única forma. */
function sidxSomaFinal(array $payload): ?float
{
    foreach (['sum_final_total', 'sumFinalTotal'] as $chave) {
        if (array_key_exists($chave, $payload)) {
            return (float) $payload[$chave];
        }
        if (isset($payload['totals'][$chave])) {
            return (float) $payload['totals'][$chave];
        }
    }

    return null;
}

/** Acha a linha da venda pelo id, seja qual for o envelope da lista. */
function sidxLinhaPorId(array $payload, int $id): ?array
{
    $candidatos = [];
    foreach (['data', 'sells', 'rows', 'items'] as $chave) {
        if (isset($payload[$chave]) && is_array($payload[$chave])) {
            $candidatos = $payload[$chave];
            break;
        }
    }
    if ($candidatos === [] && array_is_list($payload)) {
        $candidatos = $payload;
    }

    foreach ($candidatos as $linha) {
        if (is_array($linha) && (int) ($linha['id'] ?? 0) === $id) {
            return $linha;
        }
    }

    return null;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'contacts', 'business'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$tabela}).");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->businessA = $ctx['business'];
    $this->user = $ctx['user'];
    $this->vendaId = $ctx['transactionId'];

    // O segundo tenant vem do seed da lane (biz=1 + biz=2). Sem ele o contrato
    // cross-tenant não é exercitável — skip HONESTO, nunca verde falso.
    $this->businessB = \App\Business::where('id', '!=', $this->businessA->id)->first();
    if (! $this->businessB) {
        $this->markTestSkipped('Lane sem 2º business semeado — contrato cross-tenant não exercitável.');
    }

    \Spatie\Permission\Models\Permission::firstOrCreate(
        ['name' => 'direct_sell.view', 'guard_name' => 'web']
    );
    $this->user->givePermissionTo('direct_sell.view');

    // A lista só enxerga venda `final` (medido em inertiaList: type='sell' AND status='final').
    DB::table('transactions')->where('id', $this->vendaId)->update([
        'status' => 'final',
        'final_total' => 150.00,
        'total_before_tax' => 150.00,
    ]);

    $this->criarDevolucao = function (int $businessId) {
        $agora = now();

        return DB::table('transactions')->insertGetId([
            'business_id' => $businessId,
            'created_by' => $this->user->id,
            'type' => 'sell_return',
            'status' => 'final',
            'payment_status' => 'paid',
            'return_parent_id' => $this->vendaId,
            'invoice_no' => 'CT-RET-' . substr((string) microtime(true), -6),
            'transaction_date' => $agora,
            'total_before_tax' => 10.00,
            'final_total' => 10.00,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    };
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-SIDX-01 — o indicador de devolução é escopado por business
// ─────────────────────────────────────────────────────────────────────────────

it('UC-SIDX-01 (A · controle positivo) devolução do MESMO business ACENDE o indicador', function () {
    ($this->criarDevolucao)($this->businessA->id);

    $payload = $this->getJson('/sells-list-json')->assertOk()->json();
    $linha = sidxLinhaPorId($payload, $this->vendaId);

    // Pré-condição anti-vácuo: se a linha não vier, o resto não mede nada.
    expect($linha)->not->toBeNull(
        'A venda não apareceu na lista — setup inválido, o contrato não foi exercitado.'
    );

    $flag = sidxFlagDevolucao($linha);
    expect($flag)->not->toBeNull(
        'Nenhuma chave da linha carrega o indicador de devolução — o mecanismo do UC-S12 sumiu.'
    );
    expect($flag)->toBeTrue(
        'Devolução do próprio business deveria acender o indicador (mecanismo do UC-S12).'
    );
});

it('UC-SIDX-01 (B · contrato T0) devolução de OUTRO business NÃO acende o indicador', function () {
    ($this->criarDevolucao)($this->businessB->id);

    $payload = $this->getJson('/sells-list-json')->assertOk()->json();
    $linha = sidxLinhaPorId($payload, $this->vendaId);

    expect($linha)->not->toBeNull(
        'A venda não apareceu na lista — setup inválido, o contrato não foi exercitado.'
    );

    $flag = sidxFlagDevolucao($linha);
    expect($flag)->not->toBeNull(
        'Nenhuma chave da linha carrega o indicador de devolução.'
    );
    expect($flag)->toBeFalse(
        'Tier 0 (ADR 0093): o indicador de devolução considerou uma devolução de OUTRO business. '
        . 'A subquery de `return_exists` em inertiaList não filtra `sr.business_id`.'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-SIDX-02 — o totalizador de dinheiro é escopado por business (antes → depois)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-SIDX-02 [V0] o somatório do rodapé não se move por venda de OUTRO business', function () {
    $antes = sidxSomaFinal($this->getJson('/sells-list-json')->assertOk()->json());

    expect($antes)->not->toBeNull(
        'O payload não expõe somatório de dinheiro — o contrato [V0] não é exercitável.'
    );

    // Venda de valor alto e conhecido, em OUTRO tenant.
    $agora = now();
    DB::table('transactions')->insert([
        'business_id' => $this->businessB->id,
        'created_by' => $this->user->id,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'invoice_no' => 'CT-ALHEIA-' . substr((string) microtime(true), -6),
        'transaction_date' => $agora,
        'total_before_tax' => 99999.00,
        'final_total' => 99999.00,
        'created_at' => $agora,
        'updated_at' => $agora,
    ]);

    $depois = sidxSomaFinal($this->getJson('/sells-list-json')->assertOk()->json());

    // REGRA MESTRE: a prova é antes → depois, com número concreto.
    expect($depois)->toEqualWithDelta($antes, 0.001,
        "Tier 0/[V0]: o somatório do rodapé mudou de {$antes} para {$depois} por causa de uma "
        . 'venda de OUTRO business — o agregado não herdou o escopo `business_id`.'
    );
});
