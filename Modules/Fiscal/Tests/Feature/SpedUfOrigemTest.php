<?php

declare(strict_types=1);

use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

require_once __DIR__.'/../Support/sped-sem-banco.php';

/**
 * US-FISCAL-028 — a UF de origem do bloco C é a UF real da empresa (aprovado por [W] em 2026-10-27).
 *
 * Antes: `business.state ?? 'SP'`, coluna inexistente → toda empresa saía como SP.
 * Depois: a UF da primeira location (a mesma do 0000 e da NF-e).
 *
 * Antes → depois, empresa de SC no Simples (fallback CSOSN 102, ICMS 0), nota de R$ 1.000,00:
 *   cliente SC              6102 → 5102
 *   cliente SP              5102 → 6102
 *   sem UF do cliente       5102 → 5102  (destino cai na UF da empresa)
 *   ICMS                    0,00 → 0,00
 * A coluna "antes" está provada pelo controle (empresa em SP), que reproduz a regra antiga.
 */
function notaSped(int $id, ?string $ufCliente): NfeEmissao
{
    return (new NfeEmissao)->forceFill([
        'id' => $id, 'business_id' => 98, 'transaction_id' => 9000 + $id, 'modelo' => '55', 'serie' => '1',
        'numero' => $id, 'valor_total' => 1000.00, 'emitido_em' => '2026-01-10 10:00:00', 'status' => 'autorizada',
        'metadata' => $ufCliente === null ? [] : ['dest_uf' => $ufCliente],
    ]);
}

/** @return list<string> o CFOP de cada C170, na ordem das notas */
function cfopsDoC170(string $txt): array
{
    $cfops = [];
    foreach (camposSped($txt) as $c) {
        if ($c[0] === 'C170') {
            $cfops[] = $c[10]; // [0]REG [1]NUM_ITEM [2]COD_ITEM [3]DESCR_COMPL [4]QTD [5]UNID [6]VL_ITEM [7]VL_DESC [8]IND_MOV [9]CST_ICMS [10]CFOP
        }
    }

    return $cfops;
}

it('UC-FSPED-19 · empresa de SC: cliente SC é 5102, cliente SP é 6102, sem UF do cliente é 5102', function () {
    $notas = [notaSped(1, 'SC'), notaSped(2, 'SP'), notaSped(3, null)];
    $txt = spedSemBanco($notas, fontes: ['uf' => 'SC'])->gerar(98, 2026, 1);

    expect(cfopsDoC170($txt))->toBe(['5102', '6102', '5102']);

    // ICMS segue zero (Simples): o valor do arquivo não muda, só o CFOP.
    foreach (camposSped($txt) as $c) {
        if ($c[0] === 'C190') {
            expect($c[6])->toBe('0,00'); // [6] VL_ICMS do C190
        }
    }
});

it('UC-FSPED-19 · controle: empresa em SP reproduz a regra antiga (é o "antes" da tabela)', function () {
    $notas = [notaSped(1, 'SC'), notaSped(2, 'SP'), notaSped(3, null)];

    expect(cfopsDoC170(spedSemBanco($notas, fontes: ['uf' => 'SP'])->gerar(98, 2026, 1)))
        ->toBe(['6102', '5102', '5102']);
});
