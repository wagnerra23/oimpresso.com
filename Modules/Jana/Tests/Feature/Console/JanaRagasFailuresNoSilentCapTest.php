<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\JanaRagasRealEvalCommand;

/**
 * "No silent caps" no report do jana:ragas-real-eval.
 *
 * ── O DEFEITO MEDIDO (2026-07-27) ────────────────────────────────────────────
 * O report montava `'failures' => array_slice($failures, 0, 10)`. No run de
 * domingo 2026-07-26 o campo vizinho dizia `n_failed: 20` — ou seja, METADE do
 * diagnóstico era descartada antes de chegar ao log, ao transporte pra órfã, ou a
 * qualquer humano. E nada declarava a perda: quem lesse o JSON veria 10 itens três
 * linhas abaixo de um campo dizendo 20.
 *
 * Regra do projeto (CLAUDE.md §"no silent caps"): se limitar cobertura, DECLARE o
 * que caiu — senão truncamento silencioso lê como "cobri tudo".
 *
 * Desenho: o JSON (consumo de máquina) carrega TUDO; quem trunca é a tabela humana,
 * e ela anuncia quantas omitiu. Este teste trava a segunda metade — a que é
 * comportamento, não forma. Testar a primeira por grep do `array_slice` no fonte
 * seria presence-gate (LC-11, banido em proibicoes.md §5 2026-07-27).
 */
it('MORDE: quando há mais falhas que o teto da tabela, o corte é DECLARADO', function () {
    $aviso = JanaRagasRealEvalCommand::avisoDeCorte(total: 20, mostrar: 5);

    expect($aviso)->not->toBeNull()
        ->and($aviso)->toContain('+15')      // as que ficaram de fora
        ->and($aviso)->toContain('20')       // o total, pra não esconder o tamanho
        ->and($aviso)->toContain('failures'); // onde achar todas
});

it('controle: sem corte (total <= teto) → não inventa aviso', function () {
    expect(JanaRagasRealEvalCommand::avisoDeCorte(total: 5, mostrar: 5))->toBeNull()
        ->and(JanaRagasRealEvalCommand::avisoDeCorte(total: 3, mostrar: 5))->toBeNull();
});

it('controle: zero falhas → sem aviso (run limpo não fala de corte)', function () {
    expect(JanaRagasRealEvalCommand::avisoDeCorte(total: 0, mostrar: 5))->toBeNull();
});

it('borda: exatamente 1 acima do teto declara 1', function () {
    expect(JanaRagasRealEvalCommand::avisoDeCorte(total: 6, mostrar: 5))->toContain('+1');
});
