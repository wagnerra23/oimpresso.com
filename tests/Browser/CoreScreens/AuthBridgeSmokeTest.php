<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — SMOKE AUTENTICADO via AUTH BRIDGE cross-process (US-GOV-013 Fase B).
 *
 * Destrava o que estava bloqueado: as telas AUTENTICADAS (99% do app, onde mora o risco
 * visual) agora rodam no gate. Espelha tests/Browser/Public/PublicSmokeTest.php (Fase A),
 * mas atravessa o auth:
 *
 *   - O browser Playwright roda em SUBPROCESSO → a sessão do test process não cruza.
 *   - A rota /_visreg-login/{id}?to=<tela> (routes/web.php, env-guarded !isProduction)
 *     loga o user DENTRO do subprocesso do server e redireciona pra tela → 1 visit só,
 *     já autenticada.
 *   - Requer SESSION_DRIVER=file no .env do gate (array não persiste cross-request).
 *   - Dados committados (browser NÃO usa RefreshDatabase — ver tests/Pest.php); biz=1 +
 *     permissions vêm do schema-squash (#2221). `firstOrCreate` da permission é cinto+
 *     suspensório caso o slug não esteja seedado.
 *
 * SEM guard de skip (igual PublicSmokeTest): roda só pelo path que o workflow invoca
 * explicitamente (chromium garantido). Locators por TEXTO, nunca classe CSS (L-24).
 *
 * @see .github/workflows/visual-regression.yml
 * @see routes/web.php (rota _visreg-login, guard !isProduction)
 */

use App\Business;
use App\User;

beforeEach(function () {
    // CROSS-PROCESS DB: o browser (subprocesso) usa MySQL (.env, schema-squash #2221),
    // mas o test process usa sqlite :memory: (phpunit.xml força DB_CONNECTION/DB_DATABASE).
    // Realinha o test process pro MESMO MySQL (host/user/pass já vêm do .env).
    config([
        'database.default' => 'mysql',
        'database.connections.mysql.database' => 'oimpresso_test',
    ]);
    \Illuminate\Support\Facades\DB::purge('mysql');

    \Carbon\Carbon::setTestNow('2026-06-06 12:00:00');
});
afterEach(fn () => \Carbon\Carbon::setTestNow());

/**
 * Tela => [rota, slug-permissão (informativo — Admin#1 já concede tudo via Gate::before),
 * âncora-de-texto que prova que montou (não 403/login/erro)].
 *
 * Núcleo-6 de retenção (espelha tests/Browser/CoreScreens/SmokeTest.php, mas no padrão
 * auth-bridge que de fato roda no gate). Rotas confirmadas nos route files dos módulos.
 * Telas de módulo opcional podem gatear por install/enabled_modules — se o CI mostrar
 * 403/redirect, a tela sai daqui com nota (não bloqueia o ganho das demais).
 */
$screens = [
    'Financeiro/Unificado' => ['/financeiro/unificado',        'financeiro.unificado.access', 'Financeiro'],
    'Venda/Lista'          => ['/sells',                       'sell.view',                   'Vendas'],
    // /cliente exige MWART_CLIENTE_INDEX=true no .env do gate (vide visual-regression.yml):
    // sem o flag cai no Blade legacy → 500 (provado por screenshot do artifact #2320). Com
    // o flag, renderiza a Page Inertia canônica (âncora = H1 "Clientes").
    'Clientes'             => ['/cliente',                     'customer.view',               'Clientes'],
    'Compras'              => ['/compras',                     'compras.view',                'Compras'],
    'Fiscal/Cockpit'       => ['/fiscal',                      'fiscal.cockpit.access',       'Notas Fiscais'],
    'Fiscal/NF-e'          => ['/fiscal/nfe',                  'fiscal.nfe.access',           'NF-e'],
    'Fiscal/NFS-e'         => ['/fiscal/nfse',                 'fiscal.nfse.access',          'NFS-e'],
    // Workspace unificado (#2544): âncora = H1 "Oficina Auto", que renderiza mesmo sem o
    // processo FSM oficina_mecanica_os seedado (VisregTenantSeeder não traz o processo —
    // o corpo cai no empty-state, mas o header monta sempre).
    'Oficina/OS'           => ['/oficina-auto/ordens-servico', 'oficinaauto.orders.view',     'Oficina Auto'],
];

foreach ($screens as $nome => [$rota, $permissao, $ancora]) {
    it("{$nome} renderiza AUTENTICADA sem erro de console (auth bridge)", function () use ($rota, $ancora) {
        // Usa o tenant SEEDADO (VisregTenantSeeder roda no workflow): business 1 + admin
        // (id=1, role spatie Admin#1 → Gate::before concede tudo). Padrão do
        // FinanceiroTestCase — o schema-squash é schema-only, o seed traz os dados.
        // Sem seed = skip (não falha).
        $business = Business::first();
        if (! $business) {
            test()->markTestSkipped('Sem business seedado (DummyBusinessSeeder não rodou).');
        }
        $admin = User::where('business_id', $business->id)->orderBy('id')->first();
        if (! $admin) {
            test()->markTestSkipped('Sem user no business seedado.');
        }

        // 1 visit: loga o admin no subprocesso + redireciona pra tela → carrega autenticada.
        visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($rota))
            ->assertSee($ancora)
            ->assertNoConsoleLogs();
    });
}
