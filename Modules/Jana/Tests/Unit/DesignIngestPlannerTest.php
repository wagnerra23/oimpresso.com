<?php

declare(strict_types=1);

use Modules\Jana\Services\Memoria\DesignIngestPlanner;

uses(Tests\TestCase::class);

/**
 * PR-2a — núcleo PURO da ingestão de design-zip (plano vectorized-badger).
 *
 * route() (map tela→destino + extras), parseDiff() (git --name-status), e os render
 * dos 2 entregáveis (PLANO-MUDANCAS + memória de sessão). Tudo determinístico, sem
 * FS/git/zip/LLM (now injetado).
 */

function ingestMap(): array
{
    return [
        'version' => '1',
        'screens' => [
            'vendas' => [
                'module' => 'Sells',
                'routes' => [
                    ['glob' => '*-page.jsx', 'to' => 'prototipo-ui/prototipos/vendas/vendas-page.jsx'],
                    ['glob' => '*.css', 'to' => 'prototipo-ui/prototipos/vendas/'], // destino-dir → preserva nome
                ],
            ],
        ],
    ];
}

test('route() roteia pelo glob e separa extras', function () {
    $r = DesignIngestPlanner::route(ingestMap(), ['vendas-page.jsx', 'app.css', 'lixo.txt'], 'vendas');

    expect($r['routed'])->toHaveCount(2);
    expect($r['routed'][0])->toBe(['from' => 'vendas-page.jsx', 'to' => 'prototipo-ui/prototipos/vendas/vendas-page.jsx']);
    expect($r['routed'][1])->toBe(['from' => 'app.css', 'to' => 'prototipo-ui/prototipos/vendas/app.css']);
    expect($r['extras'])->toBe(['lixo.txt']);
});

test('route() de tela sem entrada no map → tudo extra', function () {
    $r = DesignIngestPlanner::route(ingestMap(), ['x.jsx'], 'inexistente');
    expect($r['routed'])->toBe([]);
    expect($r['extras'])->toBe(['x.jsx']);
});

test('parseDiff() classifica add/mod/del', function () {
    $out = "A\tprototipo-ui/prototipos/vendas/novo.jsx\nM\tprototipo-ui/prototipos/vendas/vendas-page.jsx\nD\tprototipo-ui/prototipos/vendas/velho.css\n";
    $d = DesignIngestPlanner::parseDiff($out);
    expect($d['added'])->toBe(['prototipo-ui/prototipos/vendas/novo.jsx']);
    expect($d['modified'])->toBe(['prototipo-ui/prototipos/vendas/vendas-page.jsx']);
    expect($d['removed'])->toBe(['prototipo-ui/prototipos/vendas/velho.css']);
});

test('parseDiff() vazio → listas vazias', function () {
    $d = DesignIngestPlanner::parseDiff('');
    expect($d)->toBe(['added' => [], 'modified' => [], 'removed' => []]);
});

test('renderPlano() destaca PROPOSTA + tabela + extras + roteamento', function () {
    $routing = DesignIngestPlanner::route(ingestMap(), ['vendas-page.jsx', 'lixo.txt'], 'vendas');
    $diff = DesignIngestPlanner::parseDiff("M\tprototipo-ui/prototipos/vendas/vendas-page.jsx\n");
    $plano = DesignIngestPlanner::renderPlano('vendas', $routing, $diff);

    expect($plano)
        ->toContain('# PLANO-MUDANCAS — vendas')
        ->toContain('STATUS: PROPOSTA — nada aplicado')
        ->toContain('| `prototipo-ui/prototipos/vendas/vendas-page.jsx` | mod |')
        ->toContain('⚠️ `lixo.txt` — **fora do cowork-map**')
        ->toContain('`vendas-page.jsx` → `prototipo-ui/prototipos/vendas/vendas-page.jsx`');
});

test('renderSession() tem frontmatter de sessão + contagens', function () {
    $routing = DesignIngestPlanner::route(ingestMap(), ['vendas-page.jsx', 'app.css', 'lixo.txt'], 'vendas');
    $diff = DesignIngestPlanner::parseDiff("A\tx\nM\ty\n");
    $s = DesignIngestPlanner::renderSession('vendas', $routing, $diff, '2026-06-19');

    expect($s)
        ->toContain('date: "2026-06-19"')
        ->toContain('authors: [C]')
        ->toContain('Roteados: **2**')
        ->toContain('Extras (fora do map): **1**')
        ->toContain('**1** add · **1** mod · **0** del');
});

test('é DETERMINÍSTICO — route/parseDiff/render idênticos em re-run', function () {
    $files = ['vendas-page.jsx', 'app.css', 'lixo.txt'];
    expect(DesignIngestPlanner::route(ingestMap(), $files, 'vendas'))
        ->toBe(DesignIngestPlanner::route(ingestMap(), $files, 'vendas'));
    $diff = DesignIngestPlanner::parseDiff("A\tx\n");
    expect(DesignIngestPlanner::renderSession('vendas', DesignIngestPlanner::route(ingestMap(), $files, 'vendas'), $diff, '2026-06-19'))
        ->toBe(DesignIngestPlanner::renderSession('vendas', DesignIngestPlanner::route(ingestMap(), $files, 'vendas'), $diff, '2026-06-19'));
});
