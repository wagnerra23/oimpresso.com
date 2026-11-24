<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — as 6 telas da Cobrança Recorrente no navegador, cada uma com axe.
 *
 * Telas (resources/js/Pages/RecurringBilling/):
 *   'RecurringBilling/Index'               /recurring-billing
 *   'RecurringBilling/Faturas/Index'       /recurring-billing/faturas
 *   'RecurringBilling/Configuracoes/Index' /recurring-billing/configuracoes
 *   'RecurringBilling/Planos/Index'        /recurring-billing/planos
 *   'RecurringBilling/Planos/Create'       /recurring-billing/planos/novo
 *   'RecurringBilling/Planos/Edit'         /recurring-billing/planos/{id}/editar
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * Auditoria RecurringBilling 2026-11-19, eixo 1 §6 (d13): `screen-coverage-map --screen` dava
 * `e2e (Browser) ✗` nas 6 telas e 0 telas com axe. Os UC já tinham prova por payload Inertia ou
 * por controller (lane recurringbilling-pest e modules-pest), mas nada mostrava o navegador
 * pintando a lista depois do `<Deferred>`.
 *
 * ── O QUE PROVA (viewport 1280) ───────────────────────────────────────────────
 *   - Index · UC-RBSUB-07: a assinatura pausada do 98 aparece na lista com o status "pausada";
 *     a do 99 não chega ao DOM.
 *   - Faturas · UC-RBFAT-03 [T0]: a fatura do 98 aparece; a do 99 não chega ao DOM.
 *   - Configurações · UC-RBCFG-04 [T0]: as URLs de webhook terminam no business da sessão (98).
 *   - Planos/Index · UC-RBPLN-03 [T0]: o plano do 98 aparece; o do 99 não chega ao DOM.
 *   - Planos/Create · UC-RBPNC-01 [T0]: preencher o nome e salvar grava o plano no business 98,
 *     com o slug derivado do nome.
 *   - Planos/Edit · UC-RBPNE-02 [T0]: o plano do 98 abre preenchido; o mesmo endereço com o id
 *     do 99 responde 404.
 *   - Em cada tela: 0 violações axe-core CRITICAL (`level: 0`, piso do A11yAxeBrowserTest).
 *
 * ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
 *   - Forma comparada ao protótipo (é medição design-diff, ADR 0408).
 *   - Pixel: nenhum `screenshot()` (ADR 0445).
 *
 * ── COMO ENTRA ────────────────────────────────────────────────────────────────
 * Usuário `VisregEmptyTenantSeeder::USER_EMPTY` (id 98, business 98, papel `Admin#98` —
 * tenant fictício, ADR 0358; nunca biz=4 nem biz=1). Login por `/_visreg-login` indo para
 * `/home` (que tem `SetSessionData`), e a mesma aba navega para a tela. O tour de primeira
 * visita das telas é marcado como visto no localStorage antes, para não cobrir a tela.
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Cada caso grava contato, plano, assinatura e fatura com marcador único (98 e, quando precisa,
 * 99) e apaga pelos ids no `->after()`. Nenhum dado pessoal real; valores fictícios.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI apenas (memory/proibicoes.md §Ambiente): step "E2E de comportamento + axe ·
 * RecurringBilling (6 telas)" do `visual-regression.yml`.
 */

use Database\Seeders\VisregEmptyTenantSeeder;
use Illuminate\Support\Facades\DB;

const RBBRW_ADVERSARIO = 99;

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', VisregEmptyTenantSeeder::USER_EMPTY)->where('business_id', VisregEmptyTenantSeeder::BIZ_EMPTY)->exists()) {
        throw new RuntimeException('Sem o usuario 98 do business 98: o VisregEmptyTenantSeeder nao rodou.');
    }
    if (! DB::table('business')->where('id', RBBRW_ADVERSARIO)->exists()) {
        throw new RuntimeException('Sem o business 99: o VisregTenantBLeakSeeder nao rodou.');
    }

    $this->rbMarca = 'RBBRW' . strtoupper(substr(uniqid(), -6));
    $this->rbIds = ['rb_invoices' => [], 'rb_subscriptions' => [], 'rb_plans' => [], 'contacts' => []];
});

function rbBrwPlano($test, int $biz, string $sufixo): int
{
    $id = (int) DB::table('rb_plans')->insertGetId([
        'business_id' => $biz, 'name' => $test->rbMarca . '-' . $sufixo, 'slug' => strtolower($test->rbMarca . '-' . $sufixo),
        'valor' => '50.00', 'ciclo' => 'monthly', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $test->rbIds['rb_plans'][] = $id;

    return $id;
}

/** Contato + plano + assinatura no business dado. Devolve o id da assinatura. */
function rbBrwAssinatura($test, int $biz, string $sufixo, string $status = 'active'): int
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $test->rbMarca . '-' . $sufixo,
        'mobile' => '', 'contact_id' => 'RBBRW-' . uniqid(), 'created_by' => $owner, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $test->rbIds['contacts'][] = $contato;
    $plano = rbBrwPlano($test, $biz, 'P' . $sufixo);

    $sub = (int) DB::table('rb_subscriptions')->insertGetId([
        'business_id' => $biz, 'plan_id' => $plano, 'contact_id' => $contato, 'status' => $status,
        'start_date' => '2031-03-01', 'next_due_date' => '2031-04-05', 'billing_anchor_date' => '2031-04-05',
        'paused_at' => $status === 'paused' ? now() : null,
        'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $test->rbIds['rb_subscriptions'][] = $sub;

    return $sub;
}

function rbBrwFatura($test, int $biz, int $sub, string $numero): int
{
    $id = (int) DB::table('rb_invoices')->insertGetId([
        'business_id' => $biz, 'subscription_id' => $sub,
        'contact_id' => DB::table('rb_subscriptions')->where('id', $sub)->value('contact_id'),
        'numero_documento' => $numero, 'valor' => '50.00', 'status' => 'open', 'vencimento' => '2031-04-05',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $test->rbIds['rb_invoices'][] = $id;

    return $id;
}

function rbBrwLimpar($test): void
{
    foreach (['rb_invoices', 'rb_subscriptions', 'rb_plans', 'contacts'] as $tabela) {
        $ids = $test->rbIds[$tabela] ?? [];
        if ($ids !== []) {
            DB::table($tabela)->whereIn('id', $ids)->delete();
        }
    }
    // Plano criado pela própria tela (Planos/Create).
    DB::table('rb_plans')->where('name', 'like', $test->rbMarca . '%')->delete();
}

/**
 * Loga como o usuário 98 em /home (popula a sessão), marca o tour como visto, navega para a
 * rota e espera os marcadores aparecerem. Falha dizendo o último estado lido.
 */
function rbBrwAbrir(string $rota, array $marcadores)
{
    $page = visit('/_visreg-login/' . VisregEmptyTenantSeeder::USER_EMPTY . '?to=' . urlencode('/home'))->resize(1280, 800);
    $page->script("localStorage.setItem('oimpresso.rec.tour-done', '1')");
    $page->navigate($rota);

    $marcas = json_encode(array_map('mb_strtolower', $marcadores));
    $js = "(() => { const t = (document.body ? document.body.innerText : '').replace(/\\s+/g, ' ').toLowerCase();"
        . " if (/unauthorized action|n\\u00e3o liberada|sem permiss/.test(t)) return 'ERRO-403';"
        . " const falta = {$marcas}.filter((m) => !t.includes(m));"
        . " return falta.length ? 'FALTA ' + falta.join(',') : 'PRONTO'; })()";

    $ultimo = null;
    for ($i = 0; $i < 40; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === 'PRONTO') {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("A tela {$rota} nao estabilizou — ultimo estado: '{$ultimo}'.");
}

function rbBrwHtml($page): string
{
    return (string) $page->script('document.documentElement.outerHTML');
}

it("UC-RBSUB-07 · 'RecurringBilling/Index' mostra a assinatura pausada do 98 com o status e esconde a do 99, sem violação axe CRITICAL", function () {
    rbBrwAssinatura($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'PAUSADA', 'paused');
    rbBrwAssinatura($this, RBBRW_ADVERSARIO, 'ALHEIA', 'active');
    $propria = $this->rbMarca . '-PAUSADA';

    $page = rbBrwAbrir('/recurring-billing', ['cobrança recorrente', $propria]);

    $n = json_encode($propria);
    $linha = (string) $page->script("(() => { const els = [...document.querySelectorAll('[data-contract=\"lista\"] *')]"
        . ".filter((e) => e.innerText && e.innerText.includes({$n}) && e.innerText.length < 600);"
        . " return els.length ? els[0].innerText.toLowerCase() : ''; })()");
    expect($linha)->toContain('pausada');
    expect(str_contains(rbBrwHtml($page), $this->rbMarca . '-ALHEIA'))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => rbBrwLimpar($this));

it("[T0] UC-RBFAT-03 · 'RecurringBilling/Faturas/Index' mostra a fatura do 98 e não a do 99, sem violação axe CRITICAL", function () {
    $sub98 = rbBrwAssinatura($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'F98');
    $sub99 = rbBrwAssinatura($this, RBBRW_ADVERSARIO, 'F99');
    rbBrwFatura($this, VisregEmptyTenantSeeder::BIZ_EMPTY, $sub98, $this->rbMarca . '-FAT98');
    rbBrwFatura($this, RBBRW_ADVERSARIO, $sub99, $this->rbMarca . '-FAT99');

    $page = rbBrwAbrir('/recurring-billing/faturas', [$this->rbMarca . '-FAT98']);

    expect(str_contains(rbBrwHtml($page), $this->rbMarca . '-FAT99'))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => rbBrwLimpar($this));

it("[T0] UC-RBCFG-04 · 'RecurringBilling/Configuracoes/Index' mostra as URLs de webhook do business da sessão, sem violação axe CRITICAL", function () {
    $page = rbBrwAbrir('/recurring-billing/configuracoes', ['/webhooks/inter/pix/' . VisregEmptyTenantSeeder::BIZ_EMPTY]);

    // Business no fim de cada URL de webhook exibida (Asaas e Inter): todos têm de ser o 98.
    $businesses = json_decode((string) $page->script("JSON.stringify([...document.body.innerText.matchAll(/\\/webhooks?\\/[a-z\\/-]*?\\/(\\d+)/gi)].map((m) => m[1]))"), true);
    expect($businesses)->not->toBeEmpty();
    expect(array_values(array_unique($businesses)))->toBe([(string) VisregEmptyTenantSeeder::BIZ_EMPTY]);
    $page->assertNoAccessibilityIssues(level: 0);
});

it("[T0] UC-RBPLN-03 · 'RecurringBilling/Planos/Index' mostra o plano do 98 e não o do 99, sem violação axe CRITICAL", function () {
    rbBrwPlano($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'PL98');
    rbBrwPlano($this, RBBRW_ADVERSARIO, 'PL99');

    $page = rbBrwAbrir('/recurring-billing/planos', [$this->rbMarca . '-PL98']);

    expect(str_contains(rbBrwHtml($page), $this->rbMarca . '-PL99'))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => rbBrwLimpar($this));

it("[T0] UC-RBPNC-01 · 'RecurringBilling/Planos/Create' grava o plano no business 98 com o slug derivado do nome, sem violação axe CRITICAL", function () {
    $nome = $this->rbMarca . ' Novo';

    $page = rbBrwAbrir('/recurring-billing/planos/novo', ['nome do plano', 'salvar plano']);
    $page->assertNoAccessibilityIssues(level: 0);

    // Input controlado do React: setter nativo + evento input, como o navegador faz ao digitar.
    $v = json_encode($nome);
    $page->script("(() => { const el = document.querySelector('input[maxlength=\"150\"]');"
        . " Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, {$v});"
        . " el.dispatchEvent(new Event('input', { bubbles: true })); })()");
    $page->script("[...document.querySelectorAll('button[type=\"submit\"]')].find((b) => b.innerText.includes('Salvar plano')).click()");

    $plano = null;
    for ($i = 0; $i < 40 && $plano === null; $i++) {
        $page->wait(0.25);
        $plano = DB::table('rb_plans')->where('name', $nome)->first();
    }
    expect($plano)->not->toBeNull();
    expect((int) $plano->business_id)->toBe(VisregEmptyTenantSeeder::BIZ_EMPTY);
    expect($plano->slug)->toBe(strtolower($this->rbMarca) . '-novo');
})->after(fn () => rbBrwLimpar($this));

it("[T0] UC-RBPNE-02 · 'RecurringBilling/Planos/Edit' abre o plano do 98 preenchido e dá 404 no id do 99, sem violação axe CRITICAL", function () {
    $p98 = rbBrwPlano($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'ED98');
    $p99 = rbBrwPlano($this, RBBRW_ADVERSARIO, 'ED99');

    $page = rbBrwAbrir("/recurring-billing/planos/{$p98}/editar", ['salvar alterações']);

    expect((string) $page->script("document.querySelector('input[maxlength=\"150\"]').value"))->toBe($this->rbMarca . '-ED98');
    $page->assertNoAccessibilityIssues(level: 0);

    $status = (int) $page->script("fetch('/recurring-billing/planos/{$p99}/editar', { headers: { 'Accept': 'text/html' } }).then((r) => r.status)");
    expect($status)->toBe(404);
    expect(str_contains(rbBrwHtml($page), $this->rbMarca . '-ED99'))->toBeFalse();
})->after(fn () => rbBrwLimpar($this));
