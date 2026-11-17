<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E de render da tela NfeBrasil/Transactions/NfceStatus.
 *
 * ── HISTÓRICO (2026-11-10, auditoria NfeBrasil lote 4.3) ──────────────────────
 * A versão anterior deste arquivo nunca executou: o guard `class_exists(\Pest\Browser\Bootstrap)`
 * apontava para uma classe que o pest-plugin-browser v4 não tem, então todo caso pulava; nenhum
 * workflow o invocava; e o `visit()` era feito sem login (cairia no /login). As âncoras de texto
 * também tinham envelhecido (o subtítulo e o card "NFC-e da venda" mudaram). Esta versão usa o
 * auth-bridge `/_visreg-login` do harness, como os outros Browser tests que rodam no CI, e
 * assere o contrato de HOJE do NfceStatus.tsx.
 *
 * ── O QUE PROVA ───────────────────────────────────────────────────────────────
 *   - UC-NFST-01 no browser: venda sem nota mostra "ainda não emitida" com o selo
 *     "Aguardando emissão", e não erro.
 *   - Sem nota, as ações terminais (Baixar DANFE, Reemitir nota) não aparecem.
 *   - "Verificar agora" consulta de novo sem sair da tela.
 *   - axe-core CRITICAL (level 0, o piso do A11yAxeBrowserTest).
 *
 * ── TENANT ────────────────────────────────────────────────────────────────────
 * O admin do business fictício que o VisregTenantSeeder cria no `oimpresso_test` do CI. Cada caso
 * grava uma venda `final` desse business, sem nota, e apaga no fim: venda que NÃO existe responde
 * 404 no endpoint de emissões e a tela mostra o estado de erro (medido no 1º run, 2026-11-10), que
 * não é o UC-NFST-01. Nunca biz=4 (ADR 0358). Não captura imagem (ADR 0445).
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

const NFCST_INVOICE = 'NFST-BROWSER-SMOKE';

afterEach(function () {
    \Illuminate\Support\Facades\DB::table('transactions')->where('invoice_no', NFCST_INVOICE)->delete();
});

/** Abre a tela de uma venda sem nota recém-gravada. Devolve [página, id da venda]. */
function abrirNfceStatus(): array
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        test()->markTestSkipped('Sem business seedado (VisregTenantSeeder não rodou).');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        test()->markTestSkipped('Sem user no business seedado.');
    }

    $db = \Illuminate\Support\Facades\DB::table('transactions');
    $db->where('invoice_no', NFCST_INVOICE)->delete();
    $tx = (int) \Illuminate\Support\Facades\DB::table('transactions')->insertGetId([
        'business_id' => $business->id,
        'location_id' => \Illuminate\Support\Facades\DB::table('business_locations')->where('business_id', $business->id)->orderBy('id')->value('id'),
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => \Illuminate\Support\Facades\DB::table('contacts')->where('business_id', $business->id)->orderBy('id')->value('id'),
        'invoice_no' => NFCST_INVOICE,
        'transaction_date' => now()->toDateTimeString(),
        'total_before_tax' => 10,
        'final_total' => 10,
        'created_by' => $admin->id,
        'essentials_duration' => 0,
    ]);

    $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode('/nfe-brasil/transactions/' . $tx . '/status'));

    return [$page, $tx];
}

it('UC-NFST-01 · venda sem nota mostra "ainda não emitida", não erro', function () {
    [$page, $tx] = abrirNfceStatus();
    $page = $page->resize(1280, 800);

    $page->assertSee('Status fiscal — Venda #' . $tx)
        ->assertSee('Nota fiscal da venda #' . $tx)
        ->assertSee('A nota fiscal ainda não foi emitida para esta venda.')
        ->assertSee('Aguardando emissão')
        ->assertSee('Verificar agora')
        ->assertSee('Voltar para vendas')
        ->assertDontSee('Não foi possível consultar a nota agora.')
        ->assertDontSee('Baixar DANFE')
        ->assertDontSee('Reemitir nota');
});

it('"Verificar agora" consulta de novo sem sair da tela', function () {
    [$page, $tx] = abrirNfceStatus();
    $page = $page->resize(1280, 800);
    $page->assertSee('Verificar agora');

    $page->click('Verificar agora');

    $page->assertSee('Status fiscal — Venda #' . $tx)
        ->assertSee('A nota fiscal ainda não foi emitida para esta venda.');
    expect((string) $page->script('window.location.pathname'))
        ->toBe('/nfe-brasil/transactions/' . $tx . '/status');
});

it('A11Y · NfceStatus sem nota com 0 violações axe CRITICAL', function () {
    [$page, $tx] = abrirNfceStatus();
    $page = $page->resize(1280, 800);
    $page->assertSee('Aguardando emissão');

    $page->assertNoAccessibilityIssues(level: 0);
});
