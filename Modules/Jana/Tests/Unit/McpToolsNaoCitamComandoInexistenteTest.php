<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class);

/**
 * GUARD LC-15 (proibicoes §5 2026-07-30) — mecanismo não anuncia saída que não honra.
 *
 * Origem, medida em 2026-08-10: a `AutomationsListTool` mandava o usuário rodar
 * `php artisan jana:automations:sync`. Esse comando NUNCA esteve no `commands([...])`
 * do `JanaServiceProvider` (`git log -S` vazio) e o `app/Console/Kernel.php` só faz
 * `$this->load()` de `app/Console/Commands`, nunca de `Modules/`. O artisan não o
 * conhecia em host nenhum — e a instrução falsa era servida por MCP a quem usa o
 * produto (a tool está registrada em `OimpressoMcpServer`).
 *
 * O guard é CEGO a qual comando é citado: varre o fonte de TODAS as tools MCP,
 * extrai todo `php artisan <cmd>` e exige que cada um exista no registry do artisan.
 * Cobre a classe inteira, não só o caso que o originou.
 *
 * POR QUE Tests/Unit e não junto do AutomationRegistrySyncTest: aquele arquivo tem
 * `markTestSkipped` fora do sqlite, e nenhuma lane sqlite o invoca (a `logica-pura`
 * só lista `Tests/Unit`). Um guard lá seria verde por NÃO-EXECUÇÃO — LC-13. Este não
 * precisa de DB, então roda de verdade na lane sqlite.
 */
test('GUARD LC-15: nenhuma tool MCP cita `php artisan` de comando inexistente', function () {
    $padrao = '/php artisan ([a-z][a-z0-9:_-]*)/i';

    // ── Controle positivo 1: o EXTRATOR funciona (não depende do corpus real).
    preg_match_all($padrao, 'rode `php artisan schedule:list` se precisar', $sonda);
    expect($sonda[1])->toBe(['schedule:list']);

    $registrados = array_keys(Artisan::all());

    // ── Controle positivo 2: o registry do artisan está populado. Sem isto, um
    //    Artisan::all() vazio faria TODA citação virar violação (ruído), ou pior,
    //    um corpus vazio faria o guard passar sem medir nada.
    expect($registrados)->toContain('schedule:list');

    $arquivos = glob(base_path('Modules/Jana/Mcp/Tools') . '/*.php') ?: [];

    // ── Controle positivo 3: o corpus existe. Glob vazio = guard vacuamente verde.
    expect(count($arquivos))->toBeGreaterThan(10);

    $violacoes = [];

    foreach ($arquivos as $arquivo) {
        $src = (string) file_get_contents($arquivo);

        if (preg_match_all($padrao, $src, $m) === 0) {
            continue;
        }

        foreach (array_unique($m[1]) as $cmd) {
            if (! in_array($cmd, $registrados, true)) {
                $violacoes[] = basename($arquivo) . " cita `php artisan {$cmd}` — inexistente no artisan";
            }
        }
    }

    expect($violacoes)->toBe([]);
});
