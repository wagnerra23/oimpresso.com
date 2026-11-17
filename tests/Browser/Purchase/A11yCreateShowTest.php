<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — acessibilidade (axe) de `Purchase/Create` e `Purchase/Show`.
 *
 * ── O BURACO QUE ISTO FECHA (medido) ──────────────────────────────────────────
 * Auditoria de 2026-11-05: nenhuma tela de Compras roda axe. O teste de axe do núcleo
 * (`A11yAxeBrowserTest`) não as cita.
 *
 * ── O QUE PROVA ──────────────────────────────────────────────────────────────
 * Zero violação CRITICAL (nível 0, mesmo ratchet do `A11yAxeBrowserTest`) com a tela React
 * montada. Comparação de pixel fica fora: a ADR 0445 tirou snapshots do projeto.
 *
 * ── O QUE **NÃO** PROVA ──────────────────────────────────────────────────────
 *   - Violação serious/moderate: o nível 0 só pega critical. Subir o ratchet é trabalho próprio.
 *   - O estado "grade aberta" do Create e os painéis condicionais do Show (preços propostos,
 *     inspeção): o axe roda na tela como ela abre.
 *   - Nenhum caso cita UC: os UC destas telas tratam de tenant, valor e rota, não de a11y.
 *     Citar id aqui seria citação por presença (LC-11).
 *
 * ── PRONTIDÃO ────────────────────────────────────────────────────────────────
 * Sem esperar o React, o axe audita a casca e passa por vácuo. Por isso cada tela tem um
 * sinal que só existe depois de montar, com sentinelas que nomeiam o modo de falha. O `?v=2`
 * mantém o Create na Page React mesmo se o dual-path mudar.
 *
 * ── TENANT E DADO ────────────────────────────────────────────────────────────
 * `Business::orderBy('id')->first()` = biz 1 fictício do `VisregTenantSeeder`. biz=4 é
 * PROIBIDO em teste (ADR 0358). A compra vem do `VisregComprasFlowSeeder` (`VISREG-COM-001`),
 * o mesmo dado de `IndexEtiquetaTest`, e nada aqui escreve além do seed idempotente.
 *
 * ── EXECUÇÃO ─────────────────────────────────────────────────────────────────
 * CI (`visual-regression.yml`) ou CT 100, nunca local. Nasce advisory (ADR 0261/0275).
 *
 * @see resources/js/Pages/Purchase/Create.tsx
 * @see resources/js/Pages/Purchase/Show.tsx
 * @see tests/Browser/CoreScreens/A11yAxeBrowserTest.php (mesmo nível)
 */

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;

const PUR_A11Y_REF = 'VISREG-COM-001';

const PUR_A11Y_JS_CREATE = <<<'JS'
(() => {
  if (document.querySelector('[data-contract="purchase-dados-gerais"]')) return 'PRONTO';
  return document.querySelector('form#add_purchase_form') ? 'BLADE' : 'SEM-REACT';
})()
JS;

const PUR_A11Y_JS_SHOW = <<<'JS'
(() => {
  const h1 = document.querySelector('h1');
  if (!h1) return 'SEM-H1';
  return (h1.textContent || '').indexOf('VISREG-COM-001') >= 0 ? 'PRONTO' : 'H1-OUTRA-COMPRA';
})()
JS;

beforeEach(function () {
    // Mesmo banco do browser: sem isto o id lido aqui não é o renderizado lá.
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
});

/** Falha alto: tenant ausente não pode virar verde silencioso. */
function purA11yAdmin(): User
{
    $business = Business::orderBy('id')->first();
    if (! $business) {
        throw new RuntimeException('Sem business seedado: o VisregTenantSeeder nao rodou.');
    }
    $admin = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $admin) {
        throw new RuntimeException('Sem user no business seedado: nao da pra autenticar.');
    }

    return $admin;
}

function purA11yCompraId(): int
{
    (new \Database\Seeders\VisregComprasFlowSeeder())->run();

    $id = DB::table('transactions')
        ->where('business_id', 1)
        ->where('type', 'purchase')
        ->where('ref_no', PUR_A11Y_REF)
        ->value('id');

    if (! $id) {
        throw new RuntimeException('Compra ' . PUR_A11Y_REF . ' nao existe apos o seeder.');
    }

    return (int) $id;
}

function purA11yAbrir(string $rota, string $sinal)
{
    $page = visit('/_visreg-login/' . purA11yAdmin()->id . '?to=' . urlencode($rota))
        ->resize(1280, 800);

    $ultimo = null;
    for ($i = 0; $i < 40; $i++) {
        $ultimo = (string) $page->script($sinal);
        if ($ultimo === 'PRONTO') {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("Tela {$rota} nao ficou pronta — ultimo sinal: '{$ultimo}'.");
}

it('Purchase/Create nao tem violacao axe critical', function () {
    purA11yAbrir('/purchases/create?v=2', PUR_A11Y_JS_CREATE)
        ->assertNoAccessibilityIssues(level: 0);
});

it('Purchase/Show nao tem violacao axe critical', function () {
    $id = purA11yCompraId();

    purA11yAbrir("/purchases/{$id}", PUR_A11Y_JS_SHOW)
        ->assertNoAccessibilityIssues(level: 0);
});
