<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — portal público do paciente do Fisio (`/fisio/p/{token}`), com axe.
 * Tela: Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx (Fisio/Public/Portal).
 * Casos em `Portal.casos.md` ao lado.
 *
 * ── O BURACO QUE ISTO FECHA ───────────────────────────────────────────────────
 * Auditoria do Fisio (2026-11-05), item d18 / lote 9: nenhuma tela do Fisio tinha teste de
 * navegador nem axe. `git grep -l "Fisio/" tests/Browser/` devolvia só a entrada de
 * `visreg-screens.json`. Os UC-FPP-01/02/05 já são provados pelo `FisioPortalAcessoTest`, mas
 * lá a prova é o payload Inertia; nada mostrava o teclado do PIN montando, o PIN digitado pelo
 * teclado da tela levando ao consentimento, nem o que o axe acha nesses estados.
 *
 * ── O QUE PROVA (viewport 390, celular — o portal é a tela do paciente) ──────
 *   - UC-FPP-01: link válido abre o formulário do PIN e o HTML não traz a queixa do paciente.
 *   - UC-FPP-02: link adulterado mostra "Link inválido", sem teclado de PIN.
 *   - UC-FPP-05: o PIN digitado pelo teclado da tela leva ao "Antes de começar" (consentimento
 *     de teleatendimento) e a queixa segue fora do HTML.
 *   - Em cada estado: 0 violações axe-core CRITICAL (`level: 0`, piso do A11yAxeBrowserTest).
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Tenant fictício 98 (ADR 0358; nunca biz=4 nem biz=1). O portal só abre se a clínica tem o
 * pacote `fisio_module` (UC-FPP-07): a assinatura nasce aqui e sai no fim. Paciente, contato e
 * token do portal são marcados por `FPB_QUEIXA` e apagados pelo id. Token e PIN saem do
 * `PortalTokenService` real (o mesmo do envio por WhatsApp).
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI apenas (memory/proibicoes.md §Ambiente): step "E2E de comportamento + axe · Fisio/Portal
 * (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

const FPB_BIZ = 98;
const FPB_ADMIN = 98;
const FPB_ASSINATURA = 900094;
const FPB_QUEIXA = 'Queixa E2E portal FPB';

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', FPB_ADMIN)->where('business_id', FPB_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }
    if (! Schema::hasTable('fisio_portal_tokens')) {
        throw new RuntimeException('Tabela fisio_portal_tokens ausente: as migrations do Fisio não rodaram.');
    }

    fpbLimpar();
    DB::table('subscriptions')->insert([
        'id' => FPB_ASSINATURA,
        'business_id' => FPB_BIZ,
        'package_id' => 900001, // pacote do VisregTenantSeeder; o que vale aqui é o package_details
        'start_date' => '2026-01-01',
        'end_date' => '2099-12-31',
        'package_price' => 0,
        'package_details' => json_encode(['name' => 'Fixture Fisio portal', F::PACOTE => '1']),
        'created_id' => FPB_ADMIN,
        'status' => 'approved',
    ]);

    $this->fpbPaciente = F::paciente(FPB_BIZ, FPB_ADMIN, ['main_complaint' => FPB_QUEIXA]);
    $this->fpbPortal = F::emitirPortal($this->fpbPaciente);
});

afterEach(function () {
    fpbLimpar();
});

/** Apaga a assinatura e os pacientes marcados por FPB_QUEIXA (token, consentimento, contato). */
function fpbLimpar(): void
{
    DB::table('subscriptions')->where('id', FPB_ASSINATURA)->delete();

    // SUPERADMIN: limpeza da fixture do tenant 98, fora de request.
    $pacientes = Patient::withoutGlobalScopes()->where('business_id', FPB_BIZ)->where('main_complaint', FPB_QUEIXA)->get(['id', 'contact_id']);
    foreach ($pacientes as $p) {
        $tokens = DB::table('fisio_portal_tokens')->where('business_id', FPB_BIZ)->where('patient_id', $p->id)->pluck('id')->all();
        if ($tokens !== [] && Schema::hasTable('fisio_portal_devices')) {
            DB::table('fisio_portal_devices')->where('business_id', FPB_BIZ)->whereIn('token_id', $tokens)->delete();
        }
        DB::table('fisio_portal_tokens')->where('business_id', FPB_BIZ)->where('patient_id', $p->id)->delete();
        DB::table('fisio_consents')->where('business_id', FPB_BIZ)->where('patient_id', $p->id)->delete();
        DB::table('fisio_patients')->where('business_id', FPB_BIZ)->where('id', $p->id)->delete();
        DB::table('contacts')->where('business_id', FPB_BIZ)->where('id', $p->contact_id)->delete();
    }
}

/** Abre o portal sem login e espera o seletor aparecer; falha dizendo o h1 que viu. */
function fpbAbrir(string $token, string $seletor)
{
    $page = visit('/fisio/p/'.$token)->resize(390, 844);
    $sel = json_encode($seletor);

    for ($i = 0; $i < 40; $i++) {
        if ($page->script("!!document.querySelector({$sel})") === true) {
            return $page;
        }
        $page->wait(0.25);
    }

    $h1 = (string) $page->script("(document.querySelector('h1')?.textContent ?? '').trim()");
    throw new RuntimeException("O portal não mostrou {$seletor}. h1 visto: '{$h1}'.");
}

function fpbHtmlTemQueixa($page): bool
{
    return str_contains((string) $page->script('document.documentElement.outerHTML'), FPB_QUEIXA);
}

it('UC-FPP-01 · link válido pede o PIN e não traz a queixa do paciente, sem violação axe CRITICAL', function () {
    $page = fpbAbrir($this->fpbPortal['token'], '[data-contract="fisio-portal-pin"]');

    $page->assertSee('Digite seu PIN')
        ->assertVisible('[aria-label="Número 1"]');
    expect(fpbHtmlTemQueixa($page))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-FPP-02 · link adulterado mostra "Link inválido" e não monta o teclado do PIN', function () {
    $adulterado = substr($this->fpbPortal['token'], 0, -1).(str_ends_with($this->fpbPortal['token'], 'A') ? 'B' : 'A');

    $page = visit('/fisio/p/'.$adulterado)->resize(390, 844);
    $visto = false;
    for ($i = 0; $i < 40 && ! $visto; $i++) {
        $visto = $page->script("document.body.innerText.includes('Link inválido')") === true;
        if (! $visto) {
            $page->wait(0.25);
        }
    }

    expect($visto)->toBeTrue();
    expect($page->script("!!document.querySelector('[data-contract=\"fisio-portal-pin\"]')"))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-FPP-05 · o PIN digitado no teclado da tela leva ao consentimento antes de qualquer dado clínico', function () {
    $page = fpbAbrir($this->fpbPortal['token'], '[data-contract="fisio-portal-pin"]');

    foreach (str_split($this->fpbPortal['pin']) as $digito) {
        $page->script("document.querySelector('[aria-label=\"Número {$digito}\"]').click(); true");
    }
    $page->script("[...document.querySelectorAll('[data-contract=\"fisio-portal-pin\"] button[type=\"submit\"]')][0].click(); true");

    $consentimento = false;
    for ($i = 0; $i < 40 && ! $consentimento; $i++) {
        $page->wait(0.25);
        $consentimento = $page->script("!!document.querySelector('[data-contract=\"fisio-portal-consentimento\"]')") === true;
    }

    expect($consentimento)->toBeTrue();
    $page->assertSee('Antes de começar');
    expect(fpbHtmlTemQueixa($page))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});
