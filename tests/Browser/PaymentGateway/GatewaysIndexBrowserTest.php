<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — a tela de credenciais de gateway no navegador, com axe.
 * Tela: Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.tsx
 * (Settings/PaymentGateways/Index · /settings/payment-gateways). Casos em `Index.casos.md` ao lado.
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Settings/PaymentGateways/Index` (2026-11-19):
 * `e2e (Browser) ✗` e nenhum axe. Os UC-PGSET-01..07 já são provados pelo
 * `tests/Feature/PaymentGateway/PaymentGatewaysSettingsContratoTest.php` (lane
 * `paymentgateway-pest.yml`), mas lá a prova é o payload Inertia: nada mostrava que o navegador
 * pinta a lista depois do `<Deferred>`, nem que o modal de confirmação do toggle grava.
 * Auditoria do PaymentGateway (2026-11-19), lote 4, PR 4.1.
 *
 * ── O QUE PROVA (viewport 1280) ───────────────────────────────────────────────
 *   - UC-PGSET-01: a credencial do business 98 aparece com o apelido e o toggle ligado.
 *   - UC-PGSET-02 [T0]: a credencial do business 99 não chega ao DOM (controle: a do 98 chega).
 *   - UC-PGSET-03: toggle → modal "Desativar gateway?" → Desativar → a linha mostra o toggle
 *     desligado e o banco grava `ativo=0`.
 *   - UC-PGSET-04 [T0]: o POST de toggle feito pela própria página para o id do 99 devolve 404
 *     e o registro do 99 segue ativo.
 *   - UC-PGSET-05: o marcador do `config_json` não chega ao DOM.
 *   - UC-PGSET-06: o card "Credenciais ativas" mostra as contagens do business 98, lidas do banco.
 *   - UC-PGSET-07: abrir a tela não muda a contagem de credenciais nem de cobranças.
 *   - Em cada caso com tela: 0 violações axe-core CRITICAL (`level: 0`, piso do A11yAxeBrowserTest).
 *
 * ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
 *   - Forma comparada ao protótipo (pg-payment-gateways-page.jsx): é medição design-diff, que
 *     depende de staging com gateway cadastrado (fora deste PR).
 *   - Health check e wizard de novo gateway: chamam API externa ou ficam no backlog do casos.md.
 *   - Pixel: nenhum `screenshot()` (ADR 0445).
 *
 * ── COMO ENTRA ────────────────────────────────────────────────────────────────
 * Usuário `VisregEmptyTenantSeeder::USER_EMPTY` (id 98, business 98 — tenant fictício, ADR 0358;
 * nunca biz=4 nem biz=1). O `/_visreg-login` limpa o bloco `user` da sessão e o grupo de rotas
 * `settings.*` do módulo não tem `SetSessionData`: aberta direto, a tela leria business 0. Por
 * isso o login vai para `/home` (que tem `SetSessionData`) e só então a mesma aba navega para a
 * tela — é o caminho de quem usa o sistema.
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Cada caso grava credenciais com marcador único (98 e, quando precisa, 99) e apaga pelos ids no
 * `->after()`. `config_json` tem só um marcador inerte. Nenhum valor monetário.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI apenas (memory/proibicoes.md §Ambiente): step "E2E de comportamento + axe ·
 * PaymentGateway/Index (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use Database\Seeders\VisregEmptyTenantSeeder;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

const PGBRW_ROTA = '/settings/payment-gateways';
const PGBRW_ADVERSARIO = 99;

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', VisregEmptyTenantSeeder::USER_EMPTY)->where('business_id', VisregEmptyTenantSeeder::BIZ_EMPTY)->exists()) {
        throw new RuntimeException('Sem o usuario 98 do business 98: o VisregEmptyTenantSeeder nao rodou.');
    }
    if (! DB::table('business')->where('id', PGBRW_ADVERSARIO)->exists()) {
        throw new RuntimeException('Sem o business 99: o VisregTenantBLeakSeeder nao rodou.');
    }

    $this->pgMarca = 'PGBRW' . strtoupper(substr(uniqid(), -6));
    $this->pgIds = [];
});

/**
 * Grava uma credencial no business dado num par (gateway_key, ambiente) livre — a UNIQUE
 * (business_id, gateway_key, ambiente) vale também para o que outro caso deixou.
 */
function pgBrwCredencial($test, int $businessId, string $sufixo, array $attrs = []): PaymentGatewayCredential
{
    foreach (['inter', 'c6', 'asaas', 'bcb_pix', 'pesapal'] as $key) {
        foreach (['sandbox', 'production'] as $amb) {
            $livre = ! DB::table('payment_gateway_credentials')
                ->where('business_id', $businessId)->where('gateway_key', $key)->where('ambiente', $amb)->exists();
            if ($livre) {
                // Sem auth no processo do teste: o global scope não tem business; o business vai explícito.
                $cred = PaymentGatewayCredential::withoutGlobalScopes()->create(array_merge([
                    'business_id' => $businessId,
                    'gateway_key' => $key,
                    'ambiente' => $amb,
                    'ativo' => true,
                    'nome_display' => $test->pgMarca . '-' . $sufixo,
                    'config_json' => [],
                    'health_status' => 'ok',
                ], $attrs));
                $test->pgIds[] = (int) $cred->id;

                return $cred;
            }
        }
    }

    throw new RuntimeException("Sem par (gateway_key, ambiente) livre para o business {$businessId}.");
}

function pgBrwLimpar($test): void
{
    $ids = $test->pgIds ?? [];
    if ($ids !== []) {
        DB::table('payment_gateway_credentials')->whereIn('id', $ids)->delete();
    }
}

/**
 * Loga como o usuário 98 em /home (popula a sessão), navega para a tela e espera a lista adiada
 * resolver com os marcadores pedidos. Falha dizendo o último estado lido.
 */
function pgBrwAbrir(array $marcadores = [])
{
    $page = visit('/_visreg-login/' . VisregEmptyTenantSeeder::USER_EMPTY . '?to=' . urlencode('/home'))->resize(1280, 800);
    $page->navigate(PGBRW_ROTA);

    $marcas = json_encode(array_merge(['gateways de pagamento', 'credenciais ativas'], array_map('mb_strtolower', $marcadores)));
    $js = "(() => { const t = (document.body ? document.body.innerText : '').replace(/\\s+/g, ' ').toLowerCase();"
        . " if (/unauthorized action/.test(t)) return 'ERRO-403';"
        . " const falta = {$marcas}.filter((m) => !t.includes(m));"
        . " if (falta.length) return 'FALTA ' + falta.join(',');"
        . " if (!document.querySelector('[data-contract=\"paymentgateway.gateways.tabela\"]')) return 'SEM-TABELA';"
        . " const sk = document.querySelectorAll('.pg-skel').length;"
        . " return sk ? 'SKELETON ' + sk : 'PRONTO'; })()";

    $ultimo = null;
    for ($i = 0; $i < 40; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === 'PRONTO') {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("A tela " . PGBRW_ROTA . " nao estabilizou — ultimo estado: '{$ultimo}'.");
}

/** aria-pressed do toggle na linha cujo apelido é $nome ('true' | 'false' | 'SEM-LINHA'). */
function pgBrwToggleDaLinha($page, string $nome): string
{
    $n = json_encode($nome);

    return (string) $page->script("(() => { const tr = [...document.querySelectorAll('tbody tr')].find((r) => r.innerText.includes({$n}));"
        . " if (!tr) return 'SEM-LINHA'; const b = tr.querySelector('button[aria-pressed]'); return b ? b.getAttribute('aria-pressed') : 'SEM-TOGGLE'; })()");
}

it('UC-PGSET-01 · a credencial do business 98 aparece com o apelido e o toggle ligado, sem violação axe CRITICAL', function () {
    $cred = pgBrwCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'PROPRIA');

    $page = pgBrwAbrir([$cred->nome_display]);

    expect((string) $page->script('window.location.pathname'))->toBe(PGBRW_ROTA);
    expect(pgBrwToggleDaLinha($page, $cred->nome_display))->toBe('true');
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => pgBrwLimpar($this));

it('[T0] UC-PGSET-02 · UC-PGSET-05 · a credencial do 99 e o config_json não chegam ao DOM; a do 98 chega', function () {
    $segredo = 'SEGREDO-' . $this->pgMarca;
    $propria = pgBrwCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'PROPRIA', ['config_json' => ['client_secret' => $segredo]]);
    $alheia = pgBrwCredencial($this, PGBRW_ADVERSARIO, 'ALHEIA');

    $page = pgBrwAbrir([$propria->nome_display]);

    $html = (string) $page->script('document.documentElement.outerHTML');
    expect(str_contains($html, $propria->nome_display))->toBeTrue();
    expect(str_contains($html, $alheia->nome_display))->toBeFalse();
    expect(str_contains($html, $segredo))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => pgBrwLimpar($this));

it('UC-PGSET-03 · desativar pelo modal de confirmação desliga o toggle na tela e grava ativo=0', function () {
    $cred = pgBrwCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'TOGGLE');
    $nome = json_encode($cred->nome_display);

    $page = pgBrwAbrir([$cred->nome_display]);
    expect(pgBrwToggleDaLinha($page, $cred->nome_display))->toBe('true');

    $page->script("[...document.querySelectorAll('tbody tr')].find((r) => r.innerText.includes({$nome})).querySelector('button[aria-pressed]').click()");

    $modal = '';
    for ($i = 0; $i < 20 && $modal === ''; $i++) {
        $modal = (string) $page->script("(() => { const d = document.querySelector('[role=\"dialog\"]'); return d ? d.innerText : ''; })()");
        if ($modal === '') {
            $page->wait(0.1);
        }
    }
    expect($modal)->toContain('Desativar gateway?');
    $page->assertNoAccessibilityIssues(level: 0);

    $page->script("[...document.querySelectorAll('[role=\"dialog\"] button')].find((b) => b.innerText.trim() === 'Desativar').click()");

    $estado = 'true';
    for ($i = 0; $i < 40 && $estado !== 'false'; $i++) {
        $page->wait(0.25);
        $estado = pgBrwToggleDaLinha($page, $cred->nome_display);
    }
    expect($estado)->toBe('false');
    expect((int) DB::table('payment_gateway_credentials')->where('id', $cred->id)->value('ativo'))->toBe(0);
})->after(fn () => pgBrwLimpar($this));

it('[T0] UC-PGSET-04 · o toggle para a credencial do 99, feito pela página do 98, devolve 404 e não altera o registro', function () {
    pgBrwCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'PROPRIA');
    $alheia = pgBrwCredencial($this, PGBRW_ADVERSARIO, 'ALHEIA');

    $page = pgBrwAbrir();

    $status = (int) $page->script("fetch('" . PGBRW_ROTA . "/{$alheia->id}/toggle', { method: 'POST', headers: {"
        . " 'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '', 'Accept': 'application/json' } }).then((r) => r.status)");

    expect($status)->toBe(404);
    expect((int) DB::table('payment_gateway_credentials')->where('id', $alheia->id)->value('ativo'))->toBe(1);
})->after(fn () => pgBrwLimpar($this));

it('UC-PGSET-06 · o card de credenciais mostra as contagens do business 98 lidas do banco, sem violação axe CRITICAL', function () {
    pgBrwCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'ATIVA');
    pgBrwCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'INATIVA', ['ativo' => false]);
    pgBrwCredencial($this, PGBRW_ADVERSARIO, 'RUIDO'); // não pode entrar na conta

    $total = DB::table('payment_gateway_credentials')->where('business_id', VisregEmptyTenantSeeder::BIZ_EMPTY)->count();
    $ativos = DB::table('payment_gateway_credentials')->where('business_id', VisregEmptyTenantSeeder::BIZ_EMPTY)->where('ativo', 1)->count();

    $page = pgBrwAbrir();

    $kpis = (string) $page->script("document.querySelector('[data-contract=\"paymentgateway.gateways.kpis\"]').innerText.replace(/\\s+/g, ' ')");
    expect($kpis)->toContain("de {$total} configuradas");
    expect($kpis)->toContain(($total - $ativos) . ' inativas');
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => pgBrwLimpar($this));

it('UC-PGSET-07 · abrir a tela no navegador não cria credencial nem cobrança', function () {
    pgBrwCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY, 'LEITURA');

    $creds = DB::table('payment_gateway_credentials')->count();
    $cobs = DB::table('cobrancas')->count();

    pgBrwAbrir();

    expect(DB::table('payment_gateway_credentials')->count())->toBe($creds);
    expect(DB::table('cobrancas')->count())->toBe($cobs);
})->after(fn () => pgBrwLimpar($this));
