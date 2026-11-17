<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — E2E da tela pública `resources/js/Pages/ConsultaOs/Index.tsx` (/consulta-os).
 *
 * ── O BURACO QUE ISTO FECHA (medido em 2026-11-17) ────────────────────────────
 * `git grep -l "consulta-os" tests/Browser/` devolvia ZERO, e o `screen-coverage-map` mostrava
 * E2E 0 para ConsultaOs/Index. O contrato (`ConsultaOsIndexContratoTest`) prova a API e o
 * payload, nunca o DOM: busca, "não encontrada" e falha do servidor só existiam no `.tsx`.
 * Auditoria do ConsultaOs (2026-11-05), lote 3.
 *
 * ── O QUE PROVA ───────────────────────────────────────────────────────────────
 *   - UC-COS-01: a página abre sem login, com o formulário e sem o shell do ERP (sidebar).
 *   - UC-COS-02: buscar o nº de uma OS pinta o cartão com o nº e o status daquela OS.
 *   - UC-COS-03: um nº que não existe pinta "OS não encontrada", com o nº digitado.
 *   - UC-COS-38: resposta 500 pinta um alerta e mantém o
 *     formulário; não confunde com "não encontrada". O texto do alerta não é assertado — a
 *     mensagem por status é o item 3.1 do mesmo lote.
 *   - axe-core CRITICAL (level 0) nos quatro estados.
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Tenant fictício 98 (VisregEmptyTenantSeeder). O teste cria um contato, um status e uma folha
 * de OS marcados com `E2E-COS`, e apaga os três antes e depois. Nenhum valor monetário.
 * A rota é pública e, desde 2026-11-17 (decisão [W] "empresa na URL"), a tela abre pelo link da
 * empresa 98 (`/consulta-os/{codigo}`, código de EmpresaPublica::codigoDe — a linha em
 * consulta_os_empresas fica, é o código fixo da 98). O nº escolhido não existe em nenhum seeder.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI apenas (memory/proibicoes.md §Ambiente): step "E2E de render · ConsultaOs/Index (ADVISORY)"
 * do `visual-regression.yml`. Não foi executado antes do PR.
 */

use Illuminate\Support\Facades\DB;
use Modules\ConsultaOs\Services\EmpresaPublica;

const COS_BIZ = 98;
const COS_ADMIN = 98;
const COS_CONTATO = 'E2E-COS-98';
const COS_STATUS = 'Aguardando peça E2E-COS';
const COS_OS = 'E2ECOS/0001';
const COS_OS_AUSENTE = 'E2ECOS/9999';

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', COS_ADMIN)->where('business_id', COS_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }

    cosLimpar();

    $contato = DB::table('contacts')->insertGetId([
        'business_id' => COS_BIZ,
        'type' => 'customer',
        'is_customer' => 1,
        'name' => 'Cliente E2E Consulta',
        'contact_id' => COS_CONTATO,
        'created_by' => COS_ADMIN,
        'is_default' => 0,
    ]);
    $status = DB::table('repair_statuses')->insertGetId([
        'name' => COS_STATUS,
        'color' => '#2563eb',
        'business_id' => COS_BIZ,
    ]);
    DB::table('repair_job_sheets')->insert([
        'business_id' => COS_BIZ,
        'contact_id' => $contato,
        'job_sheet_no' => COS_OS,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-E2E-COS',
        'status_id' => $status,
        'created_by' => COS_ADMIN,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

afterEach(function () {
    cosLimpar();
});

/** Apaga a folha, o status e o contato que este arquivo cria (só os marcados). */
function cosLimpar(): void
{
    DB::table('repair_job_sheets')->where('business_id', COS_BIZ)->where('job_sheet_no', COS_OS)->delete();
    DB::table('repair_statuses')->where('business_id', COS_BIZ)->where('name', COS_STATUS)->delete();
    DB::table('contacts')->where('business_id', COS_BIZ)->where('contact_id', COS_CONTATO)->delete();
}

/** Abre o link da empresa 98 sem login e espera o formulário montar. */
function cosAbrir()
{
    $page = visit('/consulta-os/'.EmpresaPublica::codigoDe(COS_BIZ))->resize(1280, 800);

    for ($i = 0; $i < 40; $i++) {
        if ($page->script("!!document.querySelector('#os-numero')") === true) {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException('O formulário #os-numero não apareceu no link da empresa.');
}

/** Espera um texto aparecer no corpo (a busca é um fetch assíncrono). */
function cosEsperarTexto($page, string $texto): bool
{
    $js = 'document.body.innerText.includes(' . json_encode($texto) . ')';
    for ($i = 0; $i < 40; $i++) {
        if ($page->script($js) === true) {
            return true;
        }
        $page->wait(0.25);
    }

    return false;
}

it('UC-COS-01 · /consulta-os abre sem login, com o formulário e sem o shell do ERP', function () {
    $page = cosAbrir();

    $page->assertSee('Consulta de Ordem de Serviço')
        ->assertVisible('#os-numero')
        ->assertVisible('#os-serie');
    expect((string) $page->script("document.querySelector('aside, nav[data-sidebar], [data-sidebar]') ? 'shell' : 'sem-shell'"))
        ->toBe('sem-shell');
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-COS-02 · buscar o nº de uma OS pinta o cartão com o nº e o status dela', function () {
    $page = cosAbrir()
        ->fill('#os-numero', COS_OS)
        ->press('Pesquisar');

    expect(cosEsperarTexto($page, COS_STATUS))->toBeTrue();
    $page->assertSee(COS_OS)
        ->assertSee('Voltar à consulta')
        ->assertDontSee('OS não encontrada');
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-COS-03 · um nº que não existe pinta "OS não encontrada" com o nº digitado', function () {
    $page = cosAbrir()
        ->fill('#os-numero', COS_OS_AUSENTE)
        ->press('Pesquisar');

    expect(cosEsperarTexto($page, 'OS não encontrada'))->toBeTrue();
    $page->assertSee(COS_OS_AUSENTE)
        ->assertDontSee(COS_STATUS);
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-COS-38 · resposta 500 da busca pinta um alerta, mantém o formulário e não vira "não encontrada"', function () {
    $page = cosAbrir();
    // A SPA não recarrega entre a troca e o clique: o fetch da busca passa a responder 500.
    $page->script("window.fetch = () => Promise.resolve(new Response('', { status: 500 })); true");
    $page->fill('#os-numero', COS_OS)->press('Pesquisar');

    $alerta = "!!document.querySelector('[role=\"alert\"]')";
    $visto = false;
    for ($i = 0; $i < 40 && ! $visto; $i++) {
        $visto = $page->script($alerta) === true;
        if (! $visto) {
            $page->wait(0.25);
        }
    }

    expect($visto)->toBeTrue();
    $page->assertVisible('#os-numero')
        ->assertDontSee('OS não encontrada')
        ->assertDontSee(COS_STATUS);
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-COS-61 · /consulta-os sem empresa mostra o aviso e não mostra o formulário', function () {
    $page = visit('/consulta-os')->resize(1280, 800);

    $this->assertTrue(cosEsperarTexto($page, 'Abra o link de acompanhamento'));
    $page->assertVisible('[data-testid="consulta-os-sem-empresa"]');
    expect($page->script("!!document.querySelector('#os-numero')"))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});
