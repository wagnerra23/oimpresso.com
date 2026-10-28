<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Portal público antigo do status do reparo — Tier 0 (ADR 0093).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HISTÓRIA (fato datado)
 * ─────────────────────────────────────────────────────────────────────────────
 * Até 2026-10-27 este arquivo travava o `POST /post-repair-status`: a rota era
 * pública, o `ScopeByBusiness` não age sem usuário logado, e sem `search_type`
 * válido a consulta saía sem filtro, devolvendo OS de todas as empresas
 * (conserto #8527, medido no main 13bc079894 em 2026-10-02).
 *
 * Em 2026-10-27 o portal antigo saiu no cutover MWART: a tela Blade e o POST que
 * ela chamava foram apagados, e `/repair-status` só leva ao ConsultaOs (decisão
 * [W] D-PORTAL, 2026-10-02). O isolamento da consulta pública agora é provado
 * no ConsultaOs (`tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php`).
 *
 * Este arquivo passa a travar a REMOÇÃO: se alguém religar o POST antigo, o
 * vetor de vazamento volta, e o teste cai.
 */
uses(Tests\TestCase::class);

it('UC-COS-11 Tier 0: o POST antigo do portal não existe mais', function () {
    expect(Route::has('post-repair-status'))->toBeFalse();

    $resposta = test()->post('/post-repair-status', [
        'search_type' => 'job_sheet_no',
        'search_number' => 'OS-A',
    ], [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ]);

    expect($resposta->status())->toBeIn([404, 405]);
    expect((string) $resposta->getContent())->not->toContain('repair_html');
});

it('UC-COS-11 GET /repair-status leva ao ConsultaOs, sem tela Blade de fallback', function () {
    $resposta = test()->get('/repair-status');

    if (Route::has('consulta-os.index')) {
        $resposta->assertRedirect(route('consulta-os.index'));
    } else {
        $resposta->assertNotFound();
    }
});

it('as views do portal antigo foram apagadas', function () {
    foreach (['customer_repair.index', 'customer_repair.repair_details', 'customer_repair.repair_activities', 'layouts.repair_status'] as $view) {
        expect(view()->exists("repair::{$view}"))->toBeFalse("A view repair::{$view} voltou.");
    }
});
