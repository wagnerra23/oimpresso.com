<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — as 6 telas de PLATAFORMA do Superadmin no navegador, com axe (auditoria
 * do Superadmin, lote L7, PR 1 de 3).
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `npm run screen:files` (2026-11-16): 0 das 12 telas do Superadmin com teste de navegador.
 * Os UCs de render existem, mas são provados no `Modules/Superadmin/Tests/Feature/*ContratoTest`,
 * que lê o PAYLOAD Inertia: nenhum prova que o browser pintou a tela depois do `<Deferred>`.
 *
 * E há um segundo buraco, mais sério: as 4 entradas do Superadmin em
 * `tests/Browser/visreg-screens.json` (Dashboard, Negócios, Assinaturas, Pacotes) navegam com o
 * `visreg_admin`, que NÃO é superadmin. O middleware `superadmin`
 * (`app/Http/Middleware/Superadmin.php`) só deixa passar username listado em
 * `constants.administrator_usernames` (env `ADMINISTRATOR_USERNAMES`), e o workflow não define
 * essa env. Logo aquelas entradas fotografam a página 403 — o mesmo que os `prod.json` antigos
 * de `memory/requisitos/Superadmin/medidas/` capturaram (`"403 Unauthorized action."`).
 * Este arquivo NÃO mexe nelas (rebaseline é decisão própria): ele só deixa de depender disso.
 *
 * ── COMO ENTRA COMO SUPERADMIN ────────────────────────────────────────────────
 * Usuário `VisregEmptyTenantSeeder::USER_EMPTY` (id 98, business 98 — tenant fictício, ADR 0358;
 * NUNCA biz=4). O `beforeEach` põe o username dele em `constants.administrator_usernames`.
 * O servidor do Pest Browser roda no MESMO processo do teste (é por isso que o
 * `database.default` setado aqui vale para as requisições — mesmo idioma do
 * `ArquivosIndexRenderTest`), então o `config()` chega ao middleware. Só este arquivo roda
 * com essa configuração: cada step do workflow é um processo à parte.
 *
 * ── O QUE PROVA ──────────────────────────────────────────────────────────────
 *   1. a tela monta autenticada como superadmin, sem a página de erro 403;
 *   2. as âncoras `data-contract` que existem em toda carga chegam ao DOM, e nenhum
 *      skeleton (`[data-slot="skeleton"]`) sobra — o `<Deferred>` resolveu;
 *   3. zero violação axe CRITICAL (`level: 0`, o mesmo piso do `A11yAxeBrowserTest`).
 *
 * ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
 *   - Regra de negócio (KPI, filtro, escrita): é dos `*ContratoTest` do módulo.
 *   - Pixel: nenhum `screenshot()`.
 *   - Âncoras condicionais (drawer aberto, prévia do comunicador, campo de segredo, cron da
 *     aba backup) ficam fora da lista: exigi-las faria o teste depender de clique ou de dado.
 *
 * ── EXECUÇÃO (CI / CT 100 — nunca local) ─────────────────────────────────────
 * Step "E2E de render · Superadmin plataforma (ADVISORY)" do `visual-regression.yml`.
 * Nasce advisory (ADR 0275): não pôde rodar antes do PR.
 *
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Comunicador/Index.tsx
 * @see Modules/Superadmin/Resources/js/Pages/superadmin/Configuracoes/Index.tsx
 */

use App\User;
use Database\Seeders\VisregEmptyTenantSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    $super = User::find(VisregEmptyTenantSeeder::USER_EMPTY);
    if (! $super || (int) $super->business_id !== VisregEmptyTenantSeeder::BIZ_EMPTY) {
        throw new RuntimeException('Sem o usuario 98 do business 98: o VisregEmptyTenantSeeder nao rodou.');
    }
    config(['constants.administrator_usernames' => $super->username]);
});

/** Probe: 403 visível, âncoras que faltam, skeleton que sobra — ou PRONTO. */
function spPlatProntoJs(array $contratos): string
{
    $sel = json_encode(array_map(fn ($c) => '[data-contract="'.$c.'"]', $contratos));

    return "(() => { const t = document.body ? document.body.innerText : '';"
        ." if (/Unauthorized action/i.test(t)) return 'ERRO-403';"
        ." const falta = {$sel}.filter((s) => !document.querySelector(s));"
        ." if (falta.length) return 'FALTA ' + falta.join(',');"
        ." const sk = document.querySelectorAll('[data-slot=\"skeleton\"]').length;"
        ." return sk ? 'SKELETON ' + sk : 'PRONTO'; })()";
}

/** Abre a rota como superadmin e espera a tela estabilizar; falha dizendo o último estado lido. */
function spPlatAbrir(string $rota, array $contratos)
{
    $page = visit('/_visreg-login/'.VisregEmptyTenantSeeder::USER_EMPTY.'?to='.urlencode($rota))->resize(1280, 800);

    $js = spPlatProntoJs($contratos);
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

it('UC-SADASH-01 · Dashboard monta em Inertia como superadmin, com KPIs resolvidos e sem violação axe CRITICAL', function () {
    $page = spPlatAbrir('/superadmin', [
        'superadmin.dashboard.periodo', 'superadmin.dashboard.kpis',
        'superadmin.dashboard.tendencia', 'superadmin.dashboard.recentes',
    ]);

    $page->assertSee('Superadmin');
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SANEG-01 · Negócios monta em Inertia como superadmin, com a tabela resolvida e sem violação axe CRITICAL', function () {
    // O tenant de CI tem os businesses 1, 98 e 99: a tabela tem linha, nunca o vazio.
    $page = spPlatAbrir('/superadmin/business', [
        'superadmin.negocios.novo-botao', 'superadmin.negocios.busca-filtros',
        'superadmin.negocios.tabela', 'superadmin.negocios.paginacao',
    ]);

    $page->assertSee('Negócios');
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SAASS-01 · Assinaturas monta em Inertia como superadmin, com KPIs e filtros resolvidos e sem violação axe CRITICAL', function () {
    // A tabela não entra na lista: o tenant de CI pode não ter assinatura, e aí a tela mostra o
    // estado vazio. Sem skeleton, as duas formas valem.
    $page = spPlatAbrir('/superadmin/superadmin-subscription', [
        'superadmin.assinaturas.kpis', 'superadmin.assinaturas.filtros',
    ]);

    $page->assertSee('Assinaturas');
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SAPAC-01 · Pacotes monta em Inertia como superadmin, com a grade resolvida e sem violação axe CRITICAL', function () {
    $page = spPlatAbrir('/superadmin/packages', [
        'superadmin.pacotes.novo-botao', 'superadmin.pacotes.grid',
    ]);

    $page->assertSee('Pacotes de assinatura');
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SACOM-01 · Comunicador monta em Inertia como superadmin, com compor e histórico e sem violação axe CRITICAL', function () {
    $page = spPlatAbrir('/superadmin/communicator', [
        'superadmin.comunicador.compor', 'superadmin.comunicador.historico',
    ]);

    $page->assertSee('Comunicador');
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-SACFG-01 · Configurações monta em Inertia como superadmin e sem violação axe CRITICAL', function () {
    $page = spPlatAbrir('/superadmin/settings', ['superadmin.configuracoes']);

    $page->assertSee('Configurações do superadmin');
    $page->assertNoAccessibilityIssues(level: 0);
});
