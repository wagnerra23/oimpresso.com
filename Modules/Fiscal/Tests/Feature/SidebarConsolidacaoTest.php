<?php

declare(strict_types=1);

use Modules\Fiscal\Http\Controllers\DataController as FiscalDataController;
use Modules\NfeBrasil\Http\Controllers\DataController as NfeBrasilDataController;

uses(Tests\TestCase::class);

/**
 * Onda ESTABILIZAR 2026-05-25 — Wagner apontou "fiscal manifestação certificado
 * tem telas competindo". Consolidação:
 *
 *   - Modules/Fiscal/DataController.modifyAdminMenu — publica 3 entries
 *     (Notas · Manifestação · Certificado) todas em /fiscal/*
 *
 *   - Modules/NfeBrasil/DataController.modifyAdminMenu — NÃO publica entries
 *     fiscais (eram 3: Notas/Manifestação/Certificado). Vira motor headless.
 *
 * Atualizado 2026-05-26 (Wagner): entry "Fiscal" (cockpit dashboard order 93)
 * REMOVIDA — duplicava visualmente com "Notas Fiscais" logo abaixo. Rota
 * /fiscal continua ativa (FiscalCockpitController) — acesso URL direta.
 *
 * Tests via análise source (NÃO via boot completo do Menu facade — requer
 * AdminSidebarMenu middleware + ModuleUtil + auth user, complexo demais pra
 * unit test puro). Asserts declarativos no código que sobrevivem a refactor
 * desde que o intent permaneça.
 */

it('Fiscal/DataController NÃO publica entry url(/fiscal) raiz (removida 2026-05-26 — duplicava com Notas)', function () {
    $src = file_get_contents(
        (new ReflectionClass(FiscalDataController::class))->getFileName(),
    );

    // Pattern busca chamada efetiva $menu->url(url('/fiscal'), 'Fiscal', ...).
    // Comentário/docblock contendo url('/fiscal') é OK — o regex exige preceder
    // por `$menu->url(\s*` e seguir por `,` (não barra).
    expect($src)->not->toMatch('/\$menu->url\(\s*url\([\'"]\/fiscal[\'"]\)\s*,/');
});

it('Fiscal/DataController publica entry url(/fiscal/nfe) — Notas Fiscais', function () {
    $src = file_get_contents(
        (new ReflectionClass(FiscalDataController::class))->getFileName(),
    );

    expect($src)->toContain("url('/fiscal/nfe')")
        ->and($src)->toContain("'Notas Fiscais'");
});

it('Fiscal/DataController publica entry url(/fiscal/dfe) — Manifestação consolidada', function () {
    $src = file_get_contents(
        (new ReflectionClass(FiscalDataController::class))->getFileName(),
    );

    expect($src)->toContain("url('/fiscal/dfe')")
        ->and($src)->toContain("'Manifestação'");
});

it('Fiscal/DataController publica entry url(/fiscal/config) — Certificado consolidado', function () {
    $src = file_get_contents(
        (new ReflectionClass(FiscalDataController::class))->getFileName(),
    );

    expect($src)->toContain("url('/fiscal/config')")
        ->and($src)->toContain("'Certificado'");
});

it('NfeBrasil/DataController NÃO publica mais entry url(/fiscal/nfe) — movido pra Fiscal', function () {
    $src = file_get_contents(
        (new ReflectionClass(NfeBrasilDataController::class))->getFileName(),
    );

    // A string url('/fiscal/nfe') só pode existir em comentário explicativo, não
    // em chamada de Menu::modify. Detectamos chamada efetiva via padrão Menu->url
    expect($src)->not->toMatch('/\$menu->url\(\s*url\([\'"]\/fiscal\/nfe[\'"]\)/');
});

it('NfeBrasil/DataController NÃO publica entry url(/nfe-brasil/manifestacao) no sidebar', function () {
    $src = file_get_contents(
        (new ReflectionClass(NfeBrasilDataController::class))->getFileName(),
    );

    expect($src)->not->toMatch('/\$menu->url\(\s*url\([\'"]\/nfe-brasil\/manifestacao[\'"]\)/');
});

it('NfeBrasil/DataController NÃO publica entry url(/nfe-brasil/configuracao/certificado) no sidebar', function () {
    $src = file_get_contents(
        (new ReflectionClass(NfeBrasilDataController::class))->getFileName(),
    );

    expect($src)->not->toMatch('/\$menu->url\(\s*url\([\'"]\/nfe-brasil\/configuracao\/certificado[\'"]\)/');
});

it('NfeBrasil/DataController.modifyAdminMenu existe (motor headless) mas SEM Menu::modify', function () {
    expect(method_exists(NfeBrasilDataController::class, 'modifyAdminMenu'))->toBeTrue();

    $src = file_get_contents(
        (new ReflectionClass(NfeBrasilDataController::class))->getFileName(),
    );

    // O método pode ainda existir pro contrato AdminSidebarMenu, mas não chama
    // Menu::modify(). Esse é o estado canon pós-consolidação 2026-05-25.
    expect($src)->not->toContain('Menu::modify(');
});

it('Fiscal/DataController.modifyAdminMenu chama Menu::modify (publica entries)', function () {
    $src = file_get_contents(
        (new ReflectionClass(FiscalDataController::class))->getFileName(),
    );

    expect($src)->toContain('Menu::modify(');
});

it('Fiscal/DataController publica fiscal.sped.export entry permission (mesmo que feature flag bloqueie download)', function () {
    $controller = new FiscalDataController;
    $perms = collect($controller->user_permissions())->pluck('value');

    expect($perms)->toContain('fiscal.sped.export')
        ->and($perms)->toContain('fiscal.access')
        ->and($perms)->toContain('fiscal.nfe.view')
        ->and($perms)->toContain('fiscal.nfe.acoes')
        ->and($perms)->toContain('fiscal.nfse.view')
        ->and($perms)->toContain('fiscal.dfe.manage')
        ->and($perms)->toContain('fiscal.config.edit');
});
