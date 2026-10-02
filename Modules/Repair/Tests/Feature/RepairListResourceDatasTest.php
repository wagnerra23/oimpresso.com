<?php

declare(strict_types=1);

use App\Transaction;
use Modules\Repair\Http\Resources\RepairListResource;

/**
 * Contrato das datas da fila `/repair/repair` no payload que a tela lê.
 *
 * `transactions.transaction_date` e `transactions.repair_due_date` não têm cast em
 * App\Transaction: o select do RepairController as entrega como STRING. Até 2026-10-02
 * o Resource fazia `optional($string)?->toIso8601String()`, e o Optional só chama
 * método em objeto — devolvia null sem erro, e as colunas de data saíam vazias.
 *
 * O teste monta a linha com `setRawAttributes`, que é exatamente a forma em que o
 * Eloquent a hidrata vinda do banco (string crua), sem precisar de banco: as datas
 * são formatação pura, e o caminho HTTP já é coberto pelo RepairIndexContratoTest.
 *
 * @see resources/js/Pages/Repair/Index.casos.md
 */
uses(Tests\TestCase::class);

function rlrLinha(array $atributos): array
{
    $tx = new Transaction();
    $tx->setRawAttributes(array_merge(['id' => 1, 'invoice_no' => 'RLR-1'], $atributos));

    return (new RepairListResource($tx))->toArray(request());
}

it('UC-RIDX-06 · a fila entrega data da venda e data de entrega vindas do banco como texto', function () {
    $linha = rlrLinha([
        'transaction_date' => '2026-09-20 09:15:00',
        'repair_due_date' => '2026-09-28 17:30:00',
    ]);

    // antes do conserto as três chaves saíam null; o prefixo prova que é a MESMA data
    expect($linha['transaction_date'])->toStartWith('2026-09-20T09:15:00');
    expect($linha['repair_due_date'])->toStartWith('2026-09-28T17:30:00');
    expect($linha['repair_due_human'])->toBeString()->not->toBeEmpty();
});

it('UC-RIDX-06 · reparo sem data de entrega continua com entrega nula, sem erro', function () {
    $linha = rlrLinha([
        'transaction_date' => '2026-09-20 09:15:00',
        'repair_due_date' => null,
    ]);

    // controle: a data da venda vem, e só a de entrega fica nula
    expect($linha['transaction_date'])->toStartWith('2026-09-20T09:15:00');
    expect($linha['repair_due_date'])->toBeNull();
    expect($linha['repair_due_human'])->toBeNull();
});
