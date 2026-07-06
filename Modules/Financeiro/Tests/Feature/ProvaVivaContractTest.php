<?php

declare(strict_types=1);

use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// Rastreabilidade casos.md (ADR 0264 G-2) — todo UC declarado citado por >=1 teste:
//   UC-PV-01, UC-PV-02 → cobertos por ProvaVivaControllerTest (rota 200 + guard 403).
//   UC-PV-03, UC-PV-05 → C1/C2 abaixo (contrato read-only + primitivos ADR 0253).
//   UC-PV-04 (Non-Goal, manual/doc) e UC-PV-06 (E2E/visual axe+screenshot) → sem teste
//   backend por natureza; citados aqui para o trio de rastreabilidade (Status ⬜ honesto).

/**
 * Prova viva (primitivos) — CONTRATO (MV batch 2026-07-06, piloto Módulo Vivo).
 *
 * Complementa ProvaVivaControllerTest.php (rota 200 + guard 403) fechando os UCs
 * que lá NÃO eram mordidos: o Non-Goal mais importante desta tela — read-only,
 * ZERO dado de tenant — que é a razão de o Tier 0 (ADR 0093) ser "trivial por
 * construção". Toda asserção deriva do charter/ADR 0253, não do código.
 *
 *  (C1) UC-PV-03 — payload Inertia SEM prop de negócio (títulos/kpis/rows/...).
 *                  Blinda a promessa read-only: migrar mock→dado real sem global
 *                  scope QUEBRA aqui e força revisão Tier 0.
 *  (C2) UC-PV-05 — critério de pronto ADR 0253: o .tsx é 100% primitivos —
 *                  sem `<div className="flex">` solto (fora das exceções do
 *                  RUNBOOK) e sem import/link de .css de tela. Asserção estática
 *                  sobre o fonte, replicando o gate do RUNBOOK-prova-viva.
 *
 * Padrão dos GUARDs Financeiro: skip gracioso (greenfield / module gate). Tenant
 * canônico via trait WithSeededTenant — biz=1 (ADR 0101), NUNCA Business::first
 * cru (catraca foundation-ratchet n_business_first) nem RefreshDatabase.
 *
 * @see resources/js/Pages/Financeiro/ProvaViva.charter.md
 * @see resources/js/Pages/Financeiro/ProvaViva.casos.md (UC-PV-03, UC-PV-05)
 * @see memory/decisions/0253-primitivos-layout.md
 * @see memory/requisitos/Financeiro/RUNBOOK-prova-viva.md
 */
function provaVivaContratoBootstrap(): User
{
    // Tenant canônico via trait WithSeededTenant (biz=1, skip acionável se seed ausente) —
    // NUNCA resolução crua de tenant em teste novo (catraca foundation-ratchet n_business_first).
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return $user;
}

function provaVivaContratoGet(User $user)
{
    // Lane backend do financeiro-pest não builda o JS → ensure_pages_exist dá
    // falso-negativo mesmo com ProvaViva.tsx no repo. Desligamos só a checagem de
    // existência de arquivo; component()+props seguem validando (mesmo padrão I1).
    config(['inertia.testing.ensure_pages_exist' => false]);

    $response = test()->actingAs($user)->get('/financeiro/prova-viva');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate / subscription bloqueia neste env.');
    }

    return $response;
}

/**
 * C1 · UC-PV-03 — read-only: o payload não carrega dado de tenant.
 *
 * O charter (Non-Goal "Consultar DB / dado de tenant") + o Controller
 * (Inertia::render sem props) prometem que os lançamentos são MOCK no .tsx.
 * Aqui asseramos que NENHUMA chave de negócio típica de tela financeira
 * (títulos/kpis/rows/lançamentos/calendário/guias) aparece no page.props —
 * só o baseline compartilhado do AppShell (auth/business/shell/...). Se alguém
 * migrar mock→dado real sem aplicar global scope, esta asserção QUEBRA.
 */
it('não expõe prop de dado de tenant — read-only por construção (UC-PV-03)', function () {
    $user = provaVivaContratoBootstrap();

    provaVivaContratoGet($user)->assertInertia(function (AssertableInertia $page) {
        $page->component('Financeiro/ProvaViva');

        // idioma canônico dos GUARDs Financeiro: inspecionar props via toArray()
        $props = $page->toArray()['props'] ?? [];

        // chaves de negócio que uma tela financeira COM dado real teria — NENHUMA
        // pode existir no payload desta tela (mock no .tsx). Se alguém migrar
        // mock→dado real sem global scope business_id, este teste QUEBRA (Tier 0).
        $proibidas = ['titulos', 'kpis', 'rows', 'lancamentos', 'calendario', 'guias', 'ledger'];
        $vazadas = array_values(array_intersect($proibidas, array_keys($props)));

        expect($vazadas)->toBe([], 'prop de negócio vazada num pilot read-only (viola Non-Goal do charter + ADR 0093): '.implode(', ', $vazadas));
    });
});

/**
 * C2 · UC-PV-05 — critério de pronto ADR 0253 (100% primitivos, zero flex/css solto).
 *
 * Replica o gate do RUNBOOK-prova-viva ("Como validar"): o fonte da tela não pode
 * ter `<div className="flex">` solto (fora das exceções toleradas: inline-flex,
 * flex-1, flex-col, place-items) nem import/link de .css de tela. Asserção estática
 * sobre o arquivo — trava o critério de pronto contra regressão silenciosa.
 */
it('o .tsx é 100% primitivos — sem flex solto nem .css de tela (UC-PV-05)', function () {
    $path = base_path('resources/js/Pages/Financeiro/ProvaViva.tsx');

    if (! is_file($path)) {
        test()->markTestSkipped('ProvaViva.tsx ausente neste checkout (lane sem front).');
    }

    $src = (string) file_get_contents($path);

    // 1) sem import/require de .css de tela (o AppShell tokeniza via @theme, não .css por tela)
    $cssImport = preg_match('/(?:import|@import|require\()\s*[\'"][^\'"]+\.css[\'"]/', $src);
    expect($cssImport)->toBe(0, 'ProvaViva.tsx importa .css de tela (viola ADR 0253 — tokeniza via @theme).');

    // 2) sem `flex` solto em className — as exceções do RUNBOOK (inline-flex/flex-1/
    //    flex-col/place-items) são helpers dos próprios primitivos e são permitidas.
    $lines = preg_split('/\R/', $src) ?: [];
    $offenders = [];
    foreach ($lines as $i => $line) {
        // pega className="... flex ..." como palavra isolada
        if (! preg_match('/className="[^"]*\bflex\b/', $line)) {
            continue;
        }
        // remove os tokens permitidos e revê se ainda sobra um `flex` isolado
        $stripped = preg_replace('/\b(?:inline-flex|flex-1|flex-col|flex-row|flex-wrap|place-items)\b/', '', $line);
        if (preg_match('/className="[^"]*\bflex\b/', (string) $stripped)) {
            $offenders[] = ($i + 1).': '.trim($line);
        }
    }

    expect($offenders)->toBe([], 'flex solto encontrado (viola ADR 0253): '.implode(' | ', $offenders));
});
