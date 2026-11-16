<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — render + axe das 7 telas Inertia de Modules/Producao.
 *
 * ── O BURACO QUE ISTO FECHA (auditoria Produção 2026-11-05, eixo 1 §5, achado P4) ──
 * `screen-coverage-map.mjs` dava `Producao · E2E 0`: nenhuma tela do módulo abria num browser
 * nem passava por axe. O único E2E (`e2e/producao-painel.spec.ts`) é stub `test.fixme`.
 * O servidor já é coberto pelos Feature de Modules/Producao/Tests/Feature; o que só o browser
 * prova é que a tela MONTA (o React não quebra com as props reais) e o que o axe mede no DOM.
 *
 * ── O QUE PROVA, POR TELA ─────────────────────────────────────────────────────
 *   - a tela abre para o admin do tenant 98 com o pacote `producao_module` (o cabeçalho
 *     `.pcv-ph` traz o título da tela; não caiu em 403, /login ou erro);
 *   - 0 violações axe-core CRITICAL (level 0, o piso do A11yAxeBrowserTest).
 * Cada caso cita o UC de "abrir a tela" do `.casos.md` dela.
 *
 * Sem comparação de pixel (ADR 0445): zero `screenshot()`, fora de `visreg-screens.json`.
 *
 * ── TENANT E DADOS ────────────────────────────────────────────────────────────
 * Tenant 98 (ADR 0358), admin 98 com role `Admin#98` (VisregEmptyTenantSeeder; o Gate::before
 * libera `producao.access`). Nunca biz=4. O tenant não tem assinatura, e Painel, Quadro, Linha,
 * Apontamento e a Ficha exigem o pacote `producao_module`: a assinatura nasce aqui. A Ficha da
 * OS precisa de uma OS: um posto, uma ordem e duas etapas do tenant 98, marcados por
 * `PCB_POSTO` e `PCB_VENDA`. Tudo sai no afterEach de arquivo.
 *
 * Lane: step "E2E de comportamento · Producao (ADVISORY)" do visual-regression.yml. Pest Browser
 * é CI-only (o container do CT 100 não tem chromium). Não foi executado antes do PR.
 */

use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\ProcessoDaOrdem;

const PCB_BIZ = 98;
const PCB_ADMIN = 98;
const PCB_ASSINATURA = 900095;
const PCB_POSTO = 'E2E-PCP-BROWSER-98';
const PCB_VENDA = 990098; // transaction_id fictício que marca a OS deste arquivo

beforeEach(function () {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', PCB_ADMIN)->where('business_id', PCB_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }

    pcbLimpar();
    DB::table('subscriptions')->insert([
        'id' => PCB_ASSINATURA,
        'business_id' => PCB_BIZ,
        'package_id' => 900001, // pacote do VisregTenantSeeder; o que vale aqui é o package_details
        'start_date' => '2026-01-01',
        'end_date' => '2099-12-31',
        'package_price' => 0,
        'package_details' => json_encode(['name' => 'Fixture Produção', 'producao_module' => '1']),
        'created_id' => PCB_ADMIN,
        'status' => 'approved',
    ]);
});

afterEach(function () {
    pcbLimpar();
});

/** Apaga a assinatura e a OS de fixture (etapas, ordem, posto), só do tenant 98. */
function pcbLimpar(): void
{
    DB::table('subscriptions')->where('id', PCB_ASSINATURA)->delete();

    $ordens = DB::table('pcp_ordens')->where('business_id', PCB_BIZ)->where('transaction_id', PCB_VENDA)->pluck('id')->all();
    if ($ordens !== []) {
        DB::table('pcp_programacoes')->where('business_id', PCB_BIZ)->where('os_tipo', Ordem::OS_TIPO)->whereIn('os_id', $ordens)->delete();
        DB::table('pcp_ordens')->where('business_id', PCB_BIZ)->whereIn('id', $ordens)->delete();
    }
    DB::table('pcp_postos')->where('business_id', PCB_BIZ)->where('codigo', PCB_POSTO)->delete();
}

/** Uma OS do tenant 98 com duas etapas programadas, como a do ProducaoFichaOsContratoTest. */
function pcbCriarOs(): int
{
    $etapa = app(ProcessoDaOrdem::class)->etapaInicial(PCB_BIZ);
    // SUPERADMIN: fixture do tenant 98 criada fora de request (sem sessão para o escopo).
    $posto = Posto::withoutGlobalScopes()->create(['business_id' => PCB_BIZ, 'codigo' => PCB_POSTO, 'nome' => 'Roland E2E',
        'setor' => 'Impressão', 'tipo_custo' => 'maquina', 'custo_hora' => '80.00']);
    // SUPERADMIN: idem — a ordem é do tenant 98 e a tela a lê pelo escopo da sessão.
    $ordem = Ordem::withoutGlobalScopes()->create(['business_id' => PCB_BIZ, 'transaction_id' => PCB_VENDA,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => $etapa->id]);
    foreach ([[1, 'Impressão', '08:00', '10:00'], [2, 'Recorte', '10:00', '10:30']] as [$bloco, $nome, $de, $ate]) {
        // SUPERADMIN: idem.
        Programacao::withoutGlobalScopes()->create(['business_id' => PCB_BIZ, 'os_tipo' => Ordem::OS_TIPO,
            'os_id' => $ordem->id, 'posto_id' => $posto->id, 'bloco' => $bloco, 'trilha' => 0, 'nome' => $nome, 'user_id' => null,
            'inicio_previsto' => "2026-11-10 {$de}:00", 'fim_previsto' => "2026-11-10 {$ate}:00", 'status' => 'pendente',
            'created_by' => PCB_ADMIN]);
    }

    return (int) $ordem->id;
}

/**
 * Abre a tela autenticado e espera o cabeçalho `.pcv-ph` mostrar o título esperado.
 * Devolve a página; se o título não vier, falha dizendo o que o cabeçalho tinha.
 */
function pcbAbrir(string $rota, string $titulo)
{
    $page = visit('/_visreg-login/'.PCB_ADMIN.'?to='.urlencode($rota))->resize(1280, 800);

    $js = '(() => (document.querySelector(".pcv-ph")?.textContent ?? "").replace(/\s+/g, " ").trim())()';
    $visto = '';
    for ($i = 0; $i < 40; $i++) {
        $visto = (string) $page->script($js);
        if (str_contains($visto, $titulo)) {
            // Props adiadas (<Deferred>) chegam depois do cabeçalho: audita o DOM já carregado.
            $page->wait(1);

            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Cabeçalho de {$rota} sem '{$titulo}'. Visto: '{$visto}'.");
}

// [rota, trecho do título no cabeçalho]
dataset('telas_producao', [
    'UC-PCV-01 · Producao/Painel/Index' => ['/producao/painel', '· Painel'],
    'UC-PCV-05 · Producao/Quadro/Index' => ['/producao/quadro', '· Quadro'],
    'UC-PCV-08 · Producao/Linha/Index' => ['/producao/linha', '· Linha do tempo'],
    'UC-PCV-19 · Producao/Apontamento/Index' => ['/producao/apontamento', 'Apontamento'],
    'UC-PCV-33 · Producao/Postos/Index' => ['/producao/postos', '· Postos e cadastros'],
    'UC-PCV-13 · Producao/Roteiros/Index' => ['/producao/roteiros', '· Roteiros'],
]);

it('a tela abre para o admin com o pacote e tem 0 violações axe CRITICAL', function (string $rota, string $titulo) {
    $page = pcbAbrir($rota, $titulo);

    expect((string) $page->script('window.location.pathname'))->toBe($rota);
    $page->assertNoAccessibilityIssues(level: 0);
})->with('telas_producao');

it('UC-PCV-10 · Producao/Os/Show · a Ficha da OS abre com o número da OS e tem 0 violações axe CRITICAL', function () {
    $id = pcbCriarOs();
    $page = pcbAbrir("/producao/ordens/{$id}", "OP {$id}");

    $page->assertSee('Impressão');
    $page->assertNoAccessibilityIssues(level: 0);
});
