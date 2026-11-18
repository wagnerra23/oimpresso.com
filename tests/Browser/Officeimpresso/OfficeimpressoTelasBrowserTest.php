<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — as 6 telas Inertia do Officeimpresso no navegador, com axe (auditoria do
 * módulo 2026-11-05, lote 6, PR 1).
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Officeimpresso/<Tela>` (2026-11-18): as 6
 * telas com `e2e (Browser) ✗`. Os UCs existem e são provados nos `*ContratoTest`/`LogsBaselineTest`
 * do módulo, que leem o payload Inertia ou o `viewData` do Blade: nenhum prova que o navegador
 * pintou a tela depois do `<Deferred>`, e nenhuma das 6 tinha axe.
 *
 * Telas (UCs nos `.casos.md` ao lado de cada `.tsx`):
 *   Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Clientes/Index.tsx
 *   Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresa/Show.tsx
 *   Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresas/Index.tsx
 *   Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Licencas/Index.tsx
 *   Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx
 *   Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Timeline.tsx
 *
 * ── O QUE PROVA, POR TELA (viewport 1280) ─────────────────────────────────────
 *   - Clientes/Index · UC-OICLI-02: com a flag ligada a tela React monta (cabeçalho; o formulário
 *     de nova credencial abre no clique) e a lista adiada resolve.
 *   - Empresa/Show · UC-EMP-19: `/officeimpresso/computadores` mostra a máquina da empresa da
 *     sessão.
 *   - Empresas/Index · UC-EMPS-01 · UC-EMPS-10: a rota serve a tela React e a empresa marcada
 *     como licenciada aparece na grade.
 *   - Licencas/Index · UC-OILIC-03 · UC-OILIC-06: a tela React lista a máquina, e a senha, a
 *     contra-senha e o serial gravados nela não chegam ao DOM.
 *   - Logs/Index · UC-LOGS-09: com a flag ligada a tela React lista a máquina.
 *   - Logs/Timeline · UC-TL-07 · UC-TL-08: a timeline da máquina monta com o acesso dela, não o
 *     estado vazio.
 *   - As 6: 0 violações axe-core CRITICAL (`level: 0`, o piso do A11yAxeBrowserTest).
 *
 * ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
 *   - Regra de negócio, filtros, escrita, 403: são dos testes de contrato do módulo.
 *   - Forma comparada ao protótipo: é o `*-visual-comparison.md` de cada tela (PR 2 do lote).
 *   - Pixel: nenhum `screenshot()` (ADR 0445).
 *
 * ── COMO ENTRA ────────────────────────────────────────────────────────────────
 * Usuário `VisregEmptyTenantSeeder::USER_EMPTY` (id 98, business 98 — tenant fictício, ADR 0358;
 * NUNCA biz=4 nem biz=1) posto em `constants.administrator_usernames`, como o
 * SuperadminTelasPlataformaTest. O `superadmin` passa no `AcessoOperador`. O servidor do Pest
 * Browser roda no mesmo processo do teste, por isso `config()` e o stub do FeatureFlagService
 * chegam às requisições.
 *
 * As 3 flags do módulo (`useV2OfficeimpressoClientes|Empresa|Logs`) são ligadas por stub: o
 * GrowthBook não é consultado, e a Clientes nem tem default ligado.
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Cada caso grava UMA máquina no business 98 (marcador único) e um acesso dela, e apaga pelos
 * ids no `->after()`. O caso de Empresas marca o business 98 como licenciado e devolve o valor
 * anterior no `->after()`. Valores fictícios.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI / CT 100 apenas (memory/proibicoes.md §Ambiente). Step "E2E de render + axe ·
 * Officeimpresso (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use App\Services\FeatureFlagService;
use App\User;
use Database\Seeders\VisregEmptyTenantSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Entities\LicencaLog;

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    $super = User::find(VisregEmptyTenantSeeder::USER_EMPTY);
    if (! $super || (int) $super->business_id !== VisregEmptyTenantSeeder::BIZ_EMPTY) {
        throw new RuntimeException('Sem o usuario 98 do business 98: o VisregEmptyTenantSeeder nao rodou.');
    }
    config(['constants.administrator_usernames' => $super->username]);

    // O LicencaLog lê IP e user-agent da requisição; aqui não há requisição.
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    app()->instance(FeatureFlagService::class, new class extends FeatureFlagService
    {
        public function __construct() {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return in_array($flag, ['useV2OfficeimpressoClientes', 'useV2OfficeimpressoEmpresa', 'useV2OfficeimpressoLogs'], true);
        }
    });

    $this->oiBrwMarca = 'OIBRW' . strtoupper(substr(uniqid(), -6));
    $this->oiBrwIds = [];
});

afterEach(function () {
    app()->forgetInstance(FeatureFlagService::class);
});

/** Grava uma máquina no business 98 e registra o id para o ->after() apagar. */
function oiBrwMaquina($test, array $attrs = []): int
{
    $id = (int) DB::table('licenca_computador')->insertGetId(array_merge([
        'business_id' => VisregEmptyTenantSeeder::BIZ_EMPTY,
        'hd'          => $test->oiBrwMarca . '-HD',
        'user_win'    => $test->oiBrwMarca,
        'hostname'    => $test->oiBrwMarca,
        'ip_interno'  => '10.0.0.1',
        'bloqueado'   => 0,
    ], $attrs));
    $test->oiBrwIds[] = $id;

    return $id;
}

/** Um acesso da máquina, com o `source`/`endpoint` que a timeline filtra. */
function oiBrwAcesso(int $licencaId): void
{
    LicencaLog::create([
        'licenca_id'  => $licencaId,
        'business_id' => VisregEmptyTenantSeeder::BIZ_EMPTY,
        'event'       => 'login_success',
        'source'      => 'delphi_middleware',
        'endpoint'    => '/connector/api/processa-dados-cliente',
        'http_status' => 200,
        'created_at'  => now(),
    ]);
}

/** Limpeza: só o que o caso gravou, pelos ids. */
function oiBrwLimpar($test): void
{
    $ids = $test->oiBrwIds ?? [];
    if ($ids !== []) {
        LicencaLog::whereIn('licenca_id', $ids)->delete();
        DB::table('licenca_computador')->whereIn('id', $ids)->delete();
    }
}

/**
 * Abre a rota como superadmin e espera: sem 403, os marcadores no texto, nenhum skeleton
 * sobrando (o `<Deferred>` resolveu). Falha dizendo o último estado lido. Devolve [$page, $texto]
 * com o texto em minúsculas (`innerText` aplica `text-transform`).
 */
function oiBrwAbrir(string $rota, array $marcadores): array
{
    $page = visit('/_visreg-login/' . VisregEmptyTenantSeeder::USER_EMPTY . '?to=' . urlencode($rota))->resize(1280, 800);

    $marcas = json_encode(array_map('mb_strtolower', $marcadores));
    $js = "(() => { const t = (document.body ? document.body.innerText : '').replace(/\\s+/g, ' ').toLowerCase();"
        . " if (/unauthorized action/.test(t)) return 'ERRO-403';"
        . " const falta = {$marcas}.filter((m) => !t.includes(m));"
        . " if (falta.length) return 'FALTA ' + falta.join(',');"
        . " const sk = document.querySelectorAll('[data-slot=\"skeleton\"]').length;"
        . " return sk ? 'SKELETON ' + sk : 'PRONTO'; })()";

    $ultimo = null;
    for ($i = 0; $i < 40; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === 'PRONTO') {
            $texto = mb_strtolower((string) $page->script("document.body.innerText.replace(/\\s+/g, ' ')"));

            return [$page, $texto];
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("A tela {$rota} nao estabilizou — ultimo estado: '{$ultimo}'.");
}

// ── Clientes/Index ───────────────────────────────────────────────────────────

it('UC-OICLI-02 · Clientes/Index com a flag ligada monta a tela React e sem violação axe CRITICAL', function () {
    [$page] = oiBrwAbrir('/officeimpresso/client', ['Clientes OAuth', 'Nova credencial']);

    expect((string) $page->script('window.location.pathname'))->toBe('/officeimpresso/client');
    expect((int) $page->script('document.querySelectorAll(\'[data-contract="header"]\').length'))->toBe(1);

    // O formulário só existe depois do clique (`novo` começa false em Clientes/Index.tsx); a 1ª
    // versão deste caso o exigia na carga e caiu no CI com "1 is identical to 2".
    $page->click('Nova credencial');
    $form = 0;
    for ($i = 0; $i < 20 && $form === 0; $i++) {
        $form = (int) $page->script('document.querySelectorAll(\'[data-contract="nova"]\').length');
        if ($form === 0) {
            $page->wait(0.1);
        }
    }
    expect($form)->toBe(1);
    $page->assertNoAccessibilityIssues(level: 0);
});

// ── Empresa/Show ─────────────────────────────────────────────────────────────

it('UC-EMP-19 · Empresa/Show em /computadores mostra a máquina da empresa da sessão e sem violação axe CRITICAL', function () {
    oiBrwMaquina($this);

    [$page, $texto] = oiBrwAbrir('/officeimpresso/computadores', ['Licenças Office Impresso', $this->oiBrwMarca]);

    expect($texto)->toContain(mb_strtolower($this->oiBrwMarca));
    expect((int) $page->script('document.querySelectorAll(\'[data-contract="ficha"], [data-contract="computadores"]\').length'))->toBe(2);
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => oiBrwLimpar($this));

// ── Empresas/Index ───────────────────────────────────────────────────────────

it('UC-EMPS-01 · UC-EMPS-10 · Empresas/Index serve a tela React com a empresa licenciada na grade e sem violação axe CRITICAL', function () {
    $this->oiBrwLicenciadaAntes = DB::table('business')->where('id', VisregEmptyTenantSeeder::BIZ_EMPTY)->value('is_officeimpresso');
    DB::table('business')->where('id', VisregEmptyTenantSeeder::BIZ_EMPTY)->update(['is_officeimpresso' => 1]);
    $nome = (string) DB::table('business')->where('id', VisregEmptyTenantSeeder::BIZ_EMPTY)->value('name');

    [$page, $texto] = oiBrwAbrir('/officeimpresso/businessall', ['Empresas Licenciadas', $nome]);

    expect((string) $page->script('window.location.pathname'))->toBe('/officeimpresso/businessall');
    expect($texto)->toContain(mb_strtolower($nome));
    expect((int) $page->script('document.querySelectorAll(\'[data-contract="kpis"], [data-contract="grade"]\').length'))->toBe(2);
    $page->assertNoAccessibilityIssues(level: 0);
})->after(function () {
    if (property_exists($this, 'oiBrwLicenciadaAntes')) {
        DB::table('business')->where('id', VisregEmptyTenantSeeder::BIZ_EMPTY)->update(['is_officeimpresso' => $this->oiBrwLicenciadaAntes]);
    }
});

// ── Licencas/Index ───────────────────────────────────────────────────────────

it('[T0] UC-OILIC-03 · UC-OILIC-06 · Licencas/Index lista a máquina sem a senha, a contra-senha e o serial no DOM, sem violação axe CRITICAL', function () {
    $senha = 'SN' . substr($this->oiBrwMarca, -6);
    $contra = 'CS' . $this->oiBrwMarca;
    $serial = 'SR' . $this->oiBrwMarca;
    oiBrwMaquina($this, ['senha' => $senha, 'contra_senha' => $contra, 'serial' => $serial]);

    [$page, $texto] = oiBrwAbrir('/officeimpresso/licenca_computador', ['Licenças de computador', $this->oiBrwMarca]);

    expect($texto)->toContain(mb_strtolower($this->oiBrwMarca));
    $html = (string) $page->script('document.documentElement.outerHTML');
    foreach ([$senha, $contra, $serial] as $segredo) {
        expect(str_contains($html, $segredo))->toBeFalse();
    }
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => oiBrwLimpar($this));

// ── Logs/Index ───────────────────────────────────────────────────────────────

it('UC-LOGS-09 · Logs/Index com a flag ligada monta a tela React com a máquina e sem violação axe CRITICAL', function () {
    $id = oiBrwMaquina($this);
    oiBrwAcesso($id);

    [$page, $texto] = oiBrwAbrir('/officeimpresso/licenca_log', ['Máquinas Cadastradas', $this->oiBrwMarca]);

    expect((string) $page->script('window.location.pathname'))->toBe('/officeimpresso/licenca_log');
    expect($texto)->toContain(mb_strtolower($this->oiBrwMarca));
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => oiBrwLimpar($this));

// ── Logs/Timeline ────────────────────────────────────────────────────────────

it('UC-TL-07 · UC-TL-08 · Logs/Timeline monta com o acesso da máquina, não o vazio, e sem violação axe CRITICAL', function () {
    $id = oiBrwMaquina($this);
    oiBrwAcesso($id);

    [$page, $texto] = oiBrwAbrir("/officeimpresso/licenca_log/timeline/{$id}", ['Timeline', $this->oiBrwMarca . '-HD']);

    expect($texto)->not->toContain('nenhum acesso registrado para esta máquina');
    expect($texto)->toContain('(1)');
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => oiBrwLimpar($this));
