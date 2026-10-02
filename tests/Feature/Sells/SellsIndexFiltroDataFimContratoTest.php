<?php

// @covers-us US-SELL-047

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contrato do filtro de período da LISTA de vendas (`SellController@inertiaList`).
 *
 * UC-SIDX-03 `[V0]` — `date_to` só com a data cobre o dia inteiro.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * O DEFEITO QUE ESTE TESTE TRAVA
 * ─────────────────────────────────────────────────────────────────────────────
 * O SellsDateFilter manda `date_to` como AAAA-MM-DD (presets Dia/Semana/Mês/Ano e o
 * <input type="date"> do Personalizado). O backend comparava cru:
 *     transactions.transaction_date <= '2001-03-10'
 * e o MySQL lê isso como `<= '2001-03-10 00:00:00'` — toda venda do último dia depois
 * da meia-noite saía da lista E do totalizador. No preset "Dia" (date_from = date_to =
 * hoje) a lista do dia ficava praticamente vazia.
 *
 * Antes → depois, com o setup abaixo (filtro 10/03/2001 → 10/03/2001):
 *     antes : venda das 14:30 FORA da lista · totals.count = 0 · sum_final_total = 0
 *     depois: venda das 14:30 DENTRO        · totals.count = 1 · sum_final_total = 150
 * A venda de 11/03/2001 00:00:00 fica fora nos dois casos (borda superior).
 *
 * Datas em 2001 de propósito: nenhum seed da lane tem venda nesse ano, então o
 * totalizador da janela mede só o que este teste criou.
 *
 * @see app/Http/Controllers/SellController.php::inertiaList
 * @see resources/js/Pages/Sells/_components/SellsDateFilter.tsx (computePresetRange)
 * @see resources/js/Pages/Sells/Index.casos.md (UC-SIDX-03)
 */
uses(DatabaseTransactions::class);

/** Acha a linha da venda pelo id no envelope da lista. */
function sidxFimLinha(array $payload, int $id): ?array
{
    foreach (($payload['data'] ?? []) as $linha) {
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
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->vendaDentro = $ctx['transactionId'];

    \Spatie\Permission\Models\Permission::firstOrCreate(
        ['name' => 'direct_sell.view', 'guard_name' => 'web']
    );
    $this->user->givePermissionTo('direct_sell.view');

    // Venda do meio da tarde do último dia do filtro — o caso que o defeito escondia.
    DB::table('transactions')->where('id', $this->vendaDentro)->update([
        'status' => 'final',
        'final_total' => 150.00,
        'total_before_tax' => 150.00,
        'transaction_date' => '2001-03-10 14:30:00',
    ]);

    // Venda à meia-noite do dia seguinte — tem de continuar FORA (borda superior).
    $this->vendaDiaSeguinte = DB::table('transactions')->insertGetId([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'invoice_no' => 'CT-FIM-' . substr((string) microtime(true), -6),
        'transaction_date' => '2001-03-11 00:00:00',
        'total_before_tax' => 70.00,
        'final_total' => 70.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('UC-SIDX-03 [V0] date_to só com a data inclui a venda da tarde do último dia', function () {
    $payload = $this->getJson('/sells-list-json?date_from=2001-03-10&date_to=2001-03-10')
        ->assertOk()->json();

    expect(sidxFimLinha($payload, $this->vendaDentro))->not->toBeNull(
        'A venda de 10/03/2001 14:30 sumiu com o filtro 10/03 → 10/03: `date_to` sem hora '
        . 'está sendo lido como 00:00:00.'
    );

    // REGRA MESTRE: o totalizador da janela é valor na tela — prova com número concreto.
    expect((int) ($payload['totals']['count'] ?? -1))->toBe(1);
    expect((float) ($payload['totals']['sum_final_total'] ?? -1))->toEqualWithDelta(150.00, 0.001);
});

it('UC-SIDX-03 [V0] a venda da meia-noite do dia seguinte continua fora', function () {
    $payload = $this->getJson('/sells-list-json?date_from=2001-03-10&date_to=2001-03-10')
        ->assertOk()->json();

    expect(sidxFimLinha($payload, $this->vendaDiaSeguinte))->toBeNull(
        'A venda de 11/03/2001 00:00:00 entrou no filtro que termina em 10/03.'
    );

    // Controle positivo: a mesma venda aparece quando o filtro é o dia dela.
    $doDia = $this->getJson('/sells-list-json?date_from=2001-03-11&date_to=2001-03-11')
        ->assertOk()->json();
    expect(sidxFimLinha($doDia, $this->vendaDiaSeguinte))->not->toBeNull(
        'Controle positivo falhou: a venda não aparece nem no próprio dia — setup inválido.'
    );
});

it('UC-SIDX-03 date_to que já traz hora é respeitado como veio', function () {
    $payload = $this->getJson('/sells-list-json?' . http_build_query([
        'date_from' => '2001-03-10',
        'date_to' => '2001-03-10 12:00:00',
    ]))->assertOk()->json();

    expect(sidxFimLinha($payload, $this->vendaDentro))->toBeNull(
        'Com date_to às 12:00 a venda das 14:30 deveria ficar fora — a hora enviada foi ignorada.'
    );
});
