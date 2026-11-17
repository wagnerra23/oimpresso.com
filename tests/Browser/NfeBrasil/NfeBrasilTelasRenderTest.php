<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E de render + axe das telas do NfeBrasil que não tinham teste de browser.
 *
 * ── O BURACO QUE ISTO FECHA (auditoria NfeBrasil 2026-11-05, pendência N1-07) ──
 * Das 6 Pages do módulo, nenhuma tinha axe; 4 não tinham teste de browser nenhum
 * (`git grep -l "NfeBrasil/" tests/Browser/` só achava o NfceStatusTest, que nunca rodava).
 * A NfceStatus tem arquivo próprio (`NfceStatusTest.php`); este cobre as outras 5.
 *
 * ── O QUE PROVA, POR TELA ─────────────────────────────────────────────────────
 *   - a tela montou para o admin do tenant do harness (o título da página aparece; não caiu em
 *     403, /login ou erro);
 *   - 0 violações axe-core CRITICAL (level 0, o piso do A11yAxeBrowserTest).
 *
 * ── POR QUE NÃO ESTÁ NO visreg-screens.json ───────────────────────────────────
 * Entrar no manifesto põe a tela também na catraca `tema-escuro-producao`, que reprova Page
 * MEDIDA fora da baseline dela, e baseline nova de tema escuro é decisão [W]. Mesmo motivo do
 * `tests/Browser/Fiscal/DasRenderTest.php`. A baseline de pixel saiu do projeto (ADR 0445).
 *
 * ── TENANT ────────────────────────────────────────────────────────────────────
 * O admin do business fictício que o VisregTenantSeeder cria no `oimpresso_test` do CI (role
 * `Admin#N`, que o Gate::before libera). Nunca biz=4 (ADR 0358). Não semeia nada e não
 * captura imagem.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (memory/proibicoes.md §Ambiente). Step "E2E de render · NfeBrasil
 * (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use App\Business;
use App\User;

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

function abrirTelaNfeBrasil(string $rota): object
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    return visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota));
}

dataset('telas_nfebrasil', [
    'Manifestacao/Index' => ['/nfe-brasil/manifestacao', 'Notas recebidas'],
    'Tributacao/Index' => ['/nfe-brasil/tributacao', 'Tributação'],
    'Tributacao/ConfigDefault' => ['/nfe-brasil/tributacao/config-default', 'Configuração padrão'],
    'Tributacao/ImportCsv' => ['/nfe-brasil/tributacao/import', 'Import CSV de regras tributárias'],
    'Tributacao/RegraForm' => ['/nfe-brasil/tributacao/regras/create', 'Nova regra tributária'],
]);

it('RENDER · a tela monta para o admin do tenant', function (string $rota, string $titulo) {
    $page = abrirTelaNfeBrasil($rota)->resize(1280, 800);

    $page->assertSee($titulo);
    expect((string) $page->script('window.location.pathname'))->toBe($rota);
})->with('telas_nfebrasil');

it('A11Y · 0 violações axe CRITICAL', function (string $rota, string $titulo) {
    $page = abrirTelaNfeBrasil($rota)->resize(1280, 800);
    $page->assertSee($titulo);
    // Props adiadas (<Deferred>) chegam depois da montagem: audita o DOM já carregado.
    $page->wait(1);

    $page->assertNoAccessibilityIssues(level: 0);
})->with('telas_nfebrasil');
