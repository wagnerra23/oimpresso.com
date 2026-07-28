<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Contrato Tier 0 do total de vendas do gráfico da home
 * (`TransactionUtil::getSellsCurrentFy` → `HomeController@index:163`).
 *
 * ÂNCORA — ADR 0093 (multi-tenant Tier 0: toda query de negócio escopa `business_id`)
 * + REGRA MESTRE valor/estoque de `memory/proibicoes.md`.
 *
 * O DEFEITO QUE ESTE TESTE TRAVA
 * ─────────────────────────────────────────────────────────────────────────────────────────
 * O método soma `SUM(transactions.final_total - COALESCE(SR.final_total, 0))`, onde `SR` é
 * um LEFT JOIN de devoluções por `return_parent_id`. O join filtrava só `SR.type`, NUNCA
 * `SR.business_id` — e `App\Transaction` não tem global scope (medido: 0 `addGlobalScope`).
 * Logo, uma devolução de OUTRO business SUBTRAI dinheiro do gráfico de vendas deste
 * business. Não é indicador de exibição como o `return_exists` do `inertiaList` (#4877):
 * aqui o cross-tenant entra direto no VALOR mostrado ao dono da empresa.
 *
 * IMPACTO MEDIDO EM PRODUÇÃO ANTES DE CORRIGIR (2026-07-28, dois caminhos independentes —
 * REGRA MESTRE §1):
 *   (a) contagem: 324 devoluções com `return_parent_id`, das quais **0 cross-business**;
 *   (b) recompute: `SUM(final_total - SR.final_total)` com e sem o filtro, biz=1 e biz=4
 *       → **delta 0,00** nos dois.
 * Ou seja: defeito LATENTE (defesa em profundidade ausente), sem dinheiro errado hoje. O
 * fix é aditivo — só RESTRINGE o conjunto do join.
 *
 * ANTI-VÁCUO (proibicoes §5 2026-07-24) — o par é obrigatório:
 *   (A) CONTROLE POSITIVO — devolução do MESMO business SUBTRAI (o mecanismo existe);
 *   (B) CONTRATO         — devolução de OUTRO business NÃO subtrai.
 * Só (B) passaria verde por motivo errado (query vazia, período fora, location não
 * permitida). O (A) é o que separa "contrato cumprido" de "parei de medir".
 *
 * ⛔ biz=1 canônico + 2º business seedado como alheio; NUNCA biz=4 (ROTA LIVRE) — ADR 0101.
 *
 * @see app/Utils/TransactionUtil.php::getSellsCurrentFy
 * @see app/Http/Controllers/HomeController.php:163
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
uses(DatabaseTransactions::class);

/** Soma o `total_sells` que o gráfico da home exibiria no período. */
function fyTotal(int $bizId, string $ini, string $fim): float
{
    $linhas = app(\App\Utils\TransactionUtil::class)->getSellsCurrentFy($bizId, $ini, $fim);

    return (float) collect($linhas)->sum(fn ($l) => (float) $l->total_sells);
}

/** Devolução (`sell_return`) apontando pra venda `$vendaId`, no business indicado. */
function fyDevolucao(int $bizId, int $vendaId, int $locationId, int $userId, float $valor): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => $locationId,
        'type' => 'sell_return',
        'status' => 'final',
        'payment_status' => 'paid',
        'return_parent_id' => $vendaId,
        'transaction_date' => now(),
        'total_before_tax' => $valor,
        'final_total' => $valor,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL (sells-pest).');
    }
    if (! Schema::hasColumn('transactions', 'return_parent_id')) {
        $this->markTestSkipped('Coluna return_parent_id ausente — schema base incompleto.');
    }

    $this->bizA = EstoqueFixture::businessId();          // biz=1 (ADR 0101)
    $this->bizB = EstoqueFixture::secondBusinessId();     // 2º tenant

    $this->user = User::where('business_id', $this->bizA)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizA, 'user.id' => $this->user->id]);

    $this->locationId = EstoqueFixture::locationId($this->bizA);
    $produto = EstoqueFixture::singleProduct($this->bizA);

    // Venda FINAL de valor conhecido, semeada por INSERT (estado inicial independente
    // do mecanismo sob teste — anti-tautologia).
    $venda = EstoqueFixture::saleWithLine($produto, 0, $this->locationId, 1.0, 500.0);
    $this->vendaId = $venda['transaction_id'];
    DB::table('transactions')->where('id', $this->vendaId)->update([
        'final_total' => 500.0,
        'transaction_date' => now(),
    ]);

    $this->ini = now()->subDays(2)->format('Y-m-d');
    $this->fim = now()->addDay()->format('Y-m-d');
});

it('(A · controle positivo) devolução do MESMO business SUBTRAI do total do gráfico', function () {
    $antes = fyTotal($this->bizA, $this->ini, $this->fim);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a venda entrou no período/location — senão nada há pra subtrair.
    expect($antes)->toBeGreaterThanOrEqual(500.0,
        'A venda semeada não entrou no total — período, location ou status inválidos no setup.');

    fyDevolucao($this->bizA, $this->vendaId, $this->locationId, (int) $this->user->id, 120.0);

    $depois = fyTotal($this->bizA, $this->ini, $this->fim);

    expect(round($antes - $depois, 2))->toBe(120.0,
        'Devolução do próprio business deveria abater exatamente seu valor do total.');
});

it('(B · contrato T0) devolução de OUTRO business NÃO mexe no total do gráfico', function () {
    if ($this->bizB === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $antes = fyTotal($this->bizA, $this->ini, $this->fim);
    expect($antes)->toBeGreaterThanOrEqual(500.0,
        'A venda semeada não entrou no total — setup inválido, o contrato não foi exercitado.');

    // Devolução ALHEIA apontando pra MINHA venda: é o vetor exato (o join casa por
    // return_parent_id e, sem o filtro, não olha de quem é a devolução).
    fyDevolucao($this->bizB, $this->vendaId, $this->locationId, (int) $this->user->id, 333.0);

    $depois = fyTotal($this->bizA, $this->ini, $this->fim);

    expect(round($antes - $depois, 2))->toBe(0.0,
        'Tier 0 (ADR 0093): uma devolução de OUTRO business alterou o total de vendas exibido '
        . 'na home deste business — o LEFT JOIN de `SR` em getSellsCurrentFy não filtra `SR.business_id`.');
});
