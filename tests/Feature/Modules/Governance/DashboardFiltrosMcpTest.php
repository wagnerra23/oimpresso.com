<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Governance\Http\Controllers\DashboardController;

/**
 * UC-GDSH-06 — o filtro de período da seção MCP é whitelist server-side e degrada pra `30d`.
 *
 * Contrato (não derivado do .tsx):
 *   - Dashboard.charter.md §Goals: "filtro de período por query string `mcp_preset` /
 *     `mcp_de` / `mcp_ate` (whitelist server-side)".
 *   - RUNBOOK-dashboard.md §3 passo 3: "Whitelist server-side (hoje|ontem|7d|30d|mes_anterior|
 *     custom); valor fora da lista, ou `custom` sem as duas pontas em `YYYY-MM-DD`, degrada pra
 *     `30d` — nunca 500."
 *
 * Sem banco, sem tenant: só a resolução da query string (`resolverFiltrosMcp`). Por isso
 * cabe na lane sqlite e não toca `business_id` — as `mcp_*` são cross-tenant por design.
 * O "nunca 500" é provado pela consequência: entrada lixo devolve o default, não lança.
 */
function resolverFiltrosMcpGdsh(array $query): array
{
    $controller = new DashboardController;
    $metodo = new ReflectionMethod($controller, 'resolverFiltrosMcp');
    $metodo->setAccessible(true);

    return $metodo->invoke($controller, Request::create('/governance/dashboard', 'GET', $query));
}

test('UC-GDSH-06 preset da whitelist é respeitado', function () {
    foreach (['hoje', 'ontem', '7d', '30d', 'mes_anterior'] as $preset) {
        expect(resolverFiltrosMcpGdsh(['mcp_preset' => $preset])['preset'])->toBe($preset);
    }
});

test('UC-GDSH-06 preset fora da whitelist degrada pra 30d sem lançar', function () {
    // Controle: '7d' NÃO é o default — se o caso acima passar e este também, o assert
    // abaixo discrimina (um resolvedor que devolvesse sempre '30d' cairia no caso acima).
    expect(resolverFiltrosMcpGdsh(['mcp_preset' => 'lixo'])['preset'])->toBe('30d');
    expect(resolverFiltrosMcpGdsh([])['preset'])->toBe('30d');
    expect(resolverFiltrosMcpGdsh(['mcp_preset' => ['7d']])['preset'])->toBe('30d');
});

test('UC-GDSH-06 custom com as duas pontas YYYY-MM-DD é aceito', function () {
    $f = resolverFiltrosMcpGdsh(['mcp_preset' => 'custom', 'mcp_de' => '2026-07-01', 'mcp_ate' => '2026-07-31']);

    expect($f)->toBe(['preset' => 'custom', 'de' => '2026-07-01', 'ate' => '2026-07-31']);
});

test('UC-GDSH-06 custom sem as duas pontas válidas degrada pra 30d e zera as datas', function () {
    $semAte = resolverFiltrosMcpGdsh(['mcp_preset' => 'custom', 'mcp_de' => '2026-07-01']);
    $dataLixo = resolverFiltrosMcpGdsh(['mcp_preset' => 'custom', 'mcp_de' => '01/07/2026', 'mcp_ate' => '2026-07-31']);

    expect($semAte)->toBe(['preset' => '30d', 'de' => null, 'ate' => null]);
    expect($dataLixo)->toBe(['preset' => '30d', 'de' => null, 'ate' => null]);
});
