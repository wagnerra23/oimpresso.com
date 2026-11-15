<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — render + acessibilidade das 2 telas do Vestuário: Etiquetas e Remarcação.
 *
 * O servidor das duas já é coberto em Modules/Vestuario/Tests/Feature. O que nenhuma suíte
 * Feature alcança é o render real: até aqui ninguém abria as telas num browser nem rodava axe
 * nelas (auditoria do módulo 2026-11-09, eixo 1, P2 item 12 · lote 5).
 *
 * Sem comparação de pixel (ADR 0445): zero `screenshot()`, fora de `tests/Browser/visreg-screens.json`.
 *
 * Tenant 98 (ADR 0358): business e admin vêm do VisregEmptyTenantSeeder. Toda rota de /vestuario
 * exige o pacote `vestuario_module` (VestuarioLiberado, UC-VPAC-01): a assinatura nasce aqui. A
 * Remarcação também exige o setting `remarcacao.enabled` da empresa (UC-VREM-05); ele é ligado só
 * no tenant 98 do banco de teste, nos casos que precisam, e a linha volta ao que era no afterEach.
 * Em produção a remarcação segue desligada.
 *
 * Lane: step "E2E de comportamento · Vestuario" do visual-regression.yml. Pest Browser é CI-only
 * (o container do CT 100 não tem chromium).
 */

use Illuminate\Support\Facades\DB;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

const VEB_BIZ = 98;
const VEB_ADMIN = 98;
const VEB_ASSINATURA = 900096;

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', VEB_ADMIN)->where('business_id', VEB_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }

    DB::table('subscriptions')->where('id', VEB_ASSINATURA)->delete();
    DB::table('subscriptions')->insert([
        'id' => VEB_ASSINATURA,
        'business_id' => VEB_BIZ,
        'package_id' => 900001, // pacote do VisregTenantSeeder; o que vale aqui é o package_details
        'start_date' => '2026-01-01',
        'end_date' => '2099-12-31',
        'package_price' => 0,
        'package_details' => json_encode(['name' => 'Fixture Vestuário', 'vestuario_module' => '1']),
        'created_id' => VEB_ADMIN,
        'status' => 'approved',
    ]);

    // Guarda a linha de settings do tenant 98 como estava, para devolver no afterEach.
    $this->settingsAntes = DB::table('vestuario_settings')->where('business_id', VEB_BIZ)->first();
});

afterEach(function () {
    DB::table('subscriptions')->where('id', VEB_ASSINATURA)->delete();

    DB::table('vestuario_settings')->where('business_id', VEB_BIZ)->delete();
    if ($this->settingsAntes !== null) {
        DB::table('vestuario_settings')->insert((array) $this->settingsAntes);
    }
    app(VestuarioSettingsResolver::class)->forBusiness(VEB_BIZ)->refresh();
});

function vebRemarcacao(bool $ligada): void
{
    app(VestuarioSettingsResolver::class)->forBusiness(VEB_BIZ)->set('remarcacao.enabled', $ligada);
}

/** Abre a tela autenticado e espera o React montar a seção esperada. */
function vebAbrir(string $rota, string $secao)
{
    $page = visit('/_visreg-login/'.VEB_ADMIN.'?to='.urlencode($rota))->resize(1280, 800);

    $sinal = "!!document.querySelector('[data-contract=\"{$secao}\"]')";
    for ($i = 0; $i < 40; $i++) {
        if ($page->script($sinal) === true) {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Seção {$secao} não apareceu em {$rota}.");
}

// ── Etiquetas ────────────────────────────────────────────────────────────────

it('UC-VET-02 · etiquetas abre com a config padrão da empresa e o lote vazio, sem erro e no axe', function () {
    vebAbrir('/vestuario/etiquetas', 'lote')
        ->assertSee('Etiquetas TAG vestuário')
        ->assertVisible('[data-contract="config"]')
        ->assertSee('400×240 dots')
        ->assertSee('QR desligado')
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

it('UC-VET-02 · etiquetas não tem rolagem horizontal da página a 1280px', function () {
    $largura = (string) vebAbrir('/vestuario/etiquetas', 'lote')
        ->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth ? "cabe" : "vaza"');

    expect($largura)->toBe('cabe');
});

// ── Remarcação ───────────────────────────────────────────────────────────────

it('UC-VREM-06 · ligada, a remarcação abre com filtro e histórico, sem erro e no axe', function () {
    vebRemarcacao(true);

    $page = vebAbrir('/vestuario/remarcacao', 'historico')
        ->assertSee('Remarcação por coleção')
        ->assertVisible('[data-contract="filtro"]');

    // O histórico é prop deferida: espera sair do "Carregando…" antes do axe.
    for ($i = 0; $i < 40 && $page->script("document.querySelector('[data-contract=\"historico\"]').innerText.includes('Carregando')") === true; $i++) {
        $page->wait(0.25);
    }

    $page->assertSee('Nenhuma remarcação ainda.')
        ->assertNoConsoleLogs()
        ->assertNoAccessibilityIssues(level: 1);
});

it('UC-VREM-05 · desligada, a rota da remarcação não abre a tela', function () {
    vebRemarcacao(false);

    $page = visit('/_visreg-login/'.VEB_ADMIN.'?to='.urlencode('/vestuario/remarcacao'))
        ->resize(1280, 800)
        ->assertDontSee('Remarcação por coleção');

    expect($page->script("!!document.querySelector('[data-contract=\"filtro\"]')"))->toBeFalse();
});
