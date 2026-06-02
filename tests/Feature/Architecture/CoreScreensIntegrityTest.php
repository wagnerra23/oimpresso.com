<?php

declare(strict_types=1);

/**
 * Gate B (camada estrutural, roda sem browser) — integridade das telas-núcleo.
 *
 * Origem: handoff Cowork 2026-06-02 (PROMPT_PARA_CODE_REFORCO-APPSHELL-TESTES §B).
 * O handoff pede smoke browser test por tela-núcleo. Browser test exige chromium
 * (tests/Browser/, opt-in, fora do suite default) — esta camada estrutural roda
 * SEMPRE no CI e garante o esqueleto: a tela existe, monta no AppShellV2, e tem
 * charter (contrato de não-regressão · tests/Charter).
 *
 * Nota L-26 (repo = UltimatePOS híbrido): "CRM" do protótipo ainda é Blade legado
 * (sem Inertia page) — por isso NÃO está aqui. As 4 telas abaixo são Inertia reais.
 *
 * @see tests/Browser/CoreScreens/SmokeTest.php  (camada browser, CI com chromium)
 */

const CORE_SCREENS = [
    // page (sob resources/js/Pages) => rota de runtime (referência pro browser test)
    'Financeiro/Unificado/Index'    => '/financeiro/unificado',
    'Compras/Index'                 => '/compras',
    'Cliente/Index'                 => '/cliente',
    'OficinaAuto/ServiceOrders/Index' => '/oficina-auto/ordens-servico',
];

function coreScreenRoot(): string
{
    return dirname(__DIR__, 3);
}

it('toda tela-núcleo tem a Page Inertia, monta no AppShellV2 e tem charter', function () {
    $faltas = [];

    foreach (array_keys(CORE_SCREENS) as $page) {
        $tsx     = coreScreenRoot()."/resources/js/Pages/{$page}.tsx";
        $charter = coreScreenRoot()."/resources/js/Pages/{$page}.charter.md";

        if (! is_file($tsx)) {
            $faltas[] = "{$page}: Page .tsx ausente";
            continue;
        }

        $src = (string) file_get_contents($tsx);
        if (! str_contains($src, 'AppShellV2')) {
            $faltas[] = "{$page}: não monta no AppShellV2";
        }
        if (! is_file($charter)) {
            $faltas[] = "{$page}: charter.md ausente (contrato de não-regressão)";
        }
    }

    expect($faltas)->toBe([], "Telas-núcleo com integridade quebrada:\n  - ".implode("\n  - ", $faltas));
});
