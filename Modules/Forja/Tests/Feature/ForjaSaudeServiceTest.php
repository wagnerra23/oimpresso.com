<?php

declare(strict_types=1);

use Modules\Forja\Services\ForjaSaudeService;

uses(Tests\TestCase::class);

/**
 * ForjaSaudeService — view `saude` do cockpit Forja (PARIDADE §11 Onda 7).
 *
 * GUARD da regra que separa esta tela de "4 cards bonitos": **o sparkline só existe
 * onde a série É a história da própria métrica**. Chamadas/Movimentações/Devs têm série
 * diária real (`mcp_audit_log.ts`, `mcp_task_events.occurred_at`); "Checks verdes" não
 * tem histórico persistido em tabela nenhuma, então sai com `serie: null` e o componente
 * não desenha `<svg>`. Desenhar ali uma linha derivada de OUTRA grandeza seria rotular
 * como histórico algo que não é — a classe de erro do §5 2026-07-16.
 *
 * O contrato vem do `Cockpit.casos.md` (UC-FORJA-15) e do charter, não do `.tsx`: se
 * alguém "completar" o 4º card com uma série inventada, este teste fica vermelho.
 *
 * Roda em QUALQUER driver: `build(null)` não semeia nada e todo builder consultado
 * guarda com `Schema::hasTable`, então sem schema a projeção degrada pra zeros — o que
 * é exatamente o cenário que interessa aqui (a FORMA do contrato, não os números).
 */
it('UC-FORJA-15 · Saúde entrega as 4 métricas e só a que não tem histórico vem sem série', function () {
    $saude = app(ForjaSaudeService::class)->build(null);

    expect($saude['metricas'])->toHaveCount(4);

    $semSerie = array_values(array_filter(
        $saude['metricas'],
        static fn (array $m): bool => $m['serie'] === null
    ));

    expect($semSerie)->toHaveCount(
        1,
        'CONTRATO (UC-FORJA-15): exatamente UMA métrica pode vir sem série — a que não tem '.
        'histórico persistido. Duas sem série = perdemos sparkline real; zero sem série = '.
        'alguém inventou histórico pra "Checks verdes", que nenhuma tabela guarda.'
    );

    expect($semSerie[0]['label'])->toBe('Checks verdes');
});

it('UC-FORJA-15 · toda série entregue tem um ponto por dia da janela, normalizada em 0..1', function () {
    $saude = app(ForjaSaudeService::class)->build(null);
    $janela = $saude['janelaDias'];

    expect($janela)->toBeGreaterThan(1); // < 2 pontos o sparkline nem desenha

    foreach ($saude['metricas'] as $m) {
        if ($m['serie'] === null) {
            continue;
        }

        expect($m['serie'])->toHaveCount(
            $janela,
            "CONTRATO (UC-FORJA-15): a série de '{$m['label']}' tem que ter um ponto por dia da ".
            'janela — série mais curta/longa que a janela desenha um sparkline que mente sobre o período.'
        );

        foreach ($m['serie'] as $ponto) {
            expect($ponto)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        }
    }
});

it('UC-FORJA-15 · o WIP da Saúde usa as MESMAS fases do quadro, na mesma ordem', function () {
    $saude = app(ForjaSaudeService::class)->build(null);

    // Não é tautologia: cruza a projeção da Saúde contra o board (ForjaQuadroService), que é
    // outra fonte. Se alguém acrescentar fase no quadro e esquecer da Saúde, o WIP passa a
    // esconder trabalho — e este teste avisa antes da tela mentir.
    $doQuadro = array_column(app(Modules\Forja\Services\ForjaQuadroService::class)->build(null)['fases'], 'key');

    expect(array_column($saude['wip'], 'id'))->toBe($doQuadro);
});
