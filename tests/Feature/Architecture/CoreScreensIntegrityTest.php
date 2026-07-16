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
 * (sem Inertia page) — por isso NÃO está aqui. As telas abaixo são Inertia reais.
 *
 * Ampliado 2026-06-02 (worklist TRAVA-SEGUNDA Martinho): além das 4 originais,
 * cobre o núcleo-6 de retenção (Cliente · Produto/Preço · Venda · Fiscal NF-e/NFS-e ·
 * Financeiro · Oficina). Esta camada estrutural é o net "falha alto" #6 do worklist
 * que roda SEMPRE (sem DB, sem chromium) — se uma tela-núcleo perder .tsx, AppShellV2
 * ou charter, o CI quebra antes do balcão da Kamila.
 *
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php  (camada browser, CI com chromium)
 */

const CORE_SCREENS = [
    // page (sob resources/js/Pages) => rota de runtime (referência pro browser test)
    // — núcleo-6 retenção (worklist TRAVA-SEGUNDA) —
    'Cliente/Index'                   => '/cliente',                     // CU-1 Cliente
    'Produto/Index'                   => '/produto',                     // CU-2 Produto/Preço
    'Sells/Index'                     => '/sells',                       // CU-3 Venda (lista)
    'Sells/Create'                    => '/sells/create',                // CU-3 Venda (o coração)
    'Fiscal/Cockpit'                  => '/fiscal',                      // CU-4 Fiscal (hub)
    'Fiscal/Nfe'                      => '/fiscal/nfe',                   // CU-4 NF-e (produto)
    'Fiscal/Nfse'                     => '/fiscal/nfse',                  // CU-4 NFS-e (serviço)
    'Financeiro/Unificado/Index'      => '/financeiro/unificado',        // CU-5 Financeiro
    'OficinaAuto/ServiceOrders/Board' => '/oficina-auto/ordens-servico', // CU-6 Oficina (o wow) — tela unificada (Index aposentado 2026-06-11)
    // — cobertura herdada (handoff Cowork 2026-06-02 §B) —
    'Compras/Index'                   => '/compras',
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
