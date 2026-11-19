<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\Support\WithSeededTenant;

uses(Tests\TestCase::class, WithSeededTenant::class, DatabaseTransactions::class);

/**
 * DS Rollout · smoke + contrato da prop `census` (/governance/ds-rollout).
 *
 * A tela é um PLANO estático (blocos A/B/C/D) + o Ledger de Conformidade DS
 * (parte viva, prop `census`, lida de `governance/ds-ledger.json`). O Controller
 * NÃO faz query — lê 1 JSON local; sem o artefato cai no `staticFallback()`
 * marcado `measured=false` + label `TODO ledger` (trava de governança: a tela só
 * mostra número que veio de gate rodando, nunca palavra).
 *
 * Como o conteúdo do plano é client-side (DsRollout.tsx), o backend testável é:
 *   - roteamento/auth (smoke)
 *   - contrato da prop `census` (chaves canônicas do Ledger)
 *   - a TRAVA de governança do fallback estático (measured=false + TODO ledger)
 *   - ausência de Inertia::defer (render estático eager, por design)
 *
 * Middlewares UltimatePOS (SetSessionData/AdminSidebarMenu/CheckUserLogin) exigem
 * schema MySQL → skip gracioso em sqlite (mesma estratégia de ForjaRoutesSmokeTest).
 * Tenant fictício 98 via WithSeededTenant — NUNCA biz=4 (ROTA LIVRE prod · ADR 0358).
 *
 * Ancora os casos de DsRollout.casos.md (Adendo MV batch 2026-07-06). As variantes
 * "b" são a versão backend-testável (mordível) dos UCs manuais base — o mesmo cenário
 * prova ambos, por isso cada it() cita o par base+b (ADR 0264 G-2, rastreabilidade):
 *   UC-DSR-01 / UC-DSR-01B (trava: número só de gate)  → cenário fallback measured=false
 *   UC-DSR-04 / UC-DSR-04B (Ledger renderiza census)   → cenário contrato prop census
 *   UC-DSR-05 (banner treeGuard)                        → chave treeGuard presente no census
 *   UC-DSR-08 / UC-DSR-08B (acesso auth)               → smoke anônimo bloqueado
 *   UC-DSR-09 (rota nomeada + throttle)                 → cenário rota
 *   UC-DSR-10 (render estático, sem defer)              → cenário sem Inertia::defer
 *
 * @see Modules\Governance\Http\Controllers\DsRolloutController
 * @see resources/js/Pages/governance/DsRollout.charter.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0239-governanca-ds-git-ssot.md
 */

/**
 * Bootstrap: tenant fictício 98 (ADR 0358) + usuário NOVO com só a permissão que a rota
 * exige (`can:governance.dashboard.view`) + sessão UltimatePOS. Antes pegava "o primeiro
 * usuário da empresa", que pode não ter a permissão e dar 403.
 */
function dsRolloutBootstrap(): User
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu/'.
            'CheckUserLogin) exigem schema MySQL com business/users. Roda na lane governance-pest.'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business')) {
        test()->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }

    $business = test()->seededTenant(); // 98
    $user = test()->usuarioComPermissoes(['governance.dashboard.view'], $business);

    session([
        'user.id'          => $user->id,
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'is_admin'         => true,
    ]);

    return $user;
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-DSR-09 — Rota nomeada existe + throttle leve (render estático)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DSR-09 · rota nomeada governance.ds-rollout.index existe', function () {
    expect(\Route::has('governance.ds-rollout.index'))->toBeTrue();
});

it('UC-DSR-09 · rota ds-rollout aplica throttle:60,1 (render estático leve)', function () {
    $route = \Route::getRoutes()->getByName('governance.ds-rollout.index');
    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('throttle:60,1');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-DSR-08 — Acesso: rota sob auth bloqueia anônimo
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DSR-08B · GET /governance/ds-rollout sem auth redireciona ou bloqueia', function () {
    $response = $this->get('/governance/ds-rollout');
    expect($response->status())->toBeIn([302, 401, 403])
        ->and($response->status())->not->toBe(200);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-DSR-04 — O Ledger renderiza da prop census (contrato de chaves canônicas)
// ─────────────────────────────────────────────────────────────────────────────
// A tabela do Ledger é populada de `census.ledger` (não hardcoded). O contrato
// que o frontend consome: ledger[] + progressPct + progressLabel + measured +
// counts{screens,done,references}. treeGuard fecha o banner (UC-DSR-05).
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DSR-04B · UC-DSR-05 · render Inertia expõe census com o contrato canônico do Ledger', function () {
    $user = dsRolloutBootstrap();

    $response = $this->actingAs($user)->get('/governance/ds-rollout');

    // Render que não dá 200 é DEFEITO, não ambiente: antes isto virava skip e escondia a falha.
    $response->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('governance/DsRollout')
        ->has('census', fn (AssertableInertia $c) => $c
            ->has('ledger')                 // linhas = telas (não hardcoded no .tsx)
            ->has('progressPct')            // barra de progresso
            ->has('progressLabel')
            ->has('measured')               // TRAVA: bool medido vs snapshot
            ->has('counts.screens')
            ->has('counts.done')
            ->has('counts.references')
            ->etc()                          // treeGuard/generatedAt/measuredAgainstSha (UC-DSR-05)
        )
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-DSR-01 — A TRAVA de governança: sem o artefato, census.measured=false + TODO
// ─────────────────────────────────────────────────────────────────────────────
// O contrato-mãe da tela: o placar SÓ mostra número que veio de gate rodando.
// Sem `governance/ds-ledger.json`, o Controller cai no staticFallback() com
// measured=false, progressPct=0 e progressLabel contendo "TODO ledger" — jamais
// um número real não-medido. Este teste é NÃO-tautológico: só passa se o fallback
// preservar a trava (measured=false quando artefato ausente).
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DSR-01B · sem ds-ledger.json a tela cai no fallback measured=false + TODO ledger', function () {
    $user = dsRolloutBootstrap();

    $ledgerPath = base_path('governance/ds-ledger.json');
    if (is_file($ledgerPath)) {
        // Artefato presente neste ambiente: a trava é medida=true e o teste
        // válido é o inverso (número real carimbado). Não removemos o artefato
        // (side-effect no repo) — asserimos o contrato do caminho medido.
        $response = $this->actingAs($user)->get('/governance/ds-rollout');
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('census.measured', true)
            ->has('census.measuredAgainstSha')
            ->has('census.generatedAt')
        );

        return;
    }

    // Checkout fresco (sem censo) — caminho canônico do fallback: a trava tem de segurar.
    $response = $this->actingAs($user)->get('/governance/ds-rollout');
    $response->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('census.measured', false)
        ->where('census.progressPct', 0)
        ->where('census.measuredAgainstSha', null)
        ->where('census.generatedAt', null)
        ->where('census.progressLabel', fn ($label) => str_contains((string) $label, 'TODO ledger'))
        // linha-referência Atendimento sempre presente (fora da conta do %)
        ->where('census.counts.references', 1)
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-DSR-10 — Render estático: Controller NÃO usa Inertia::defer (eager por design)
// ─────────────────────────────────────────────────────────────────────────────
// Ao contrário do ModuleGradeController (props caras → defer), aqui o census lê
// 1 JSON local (zero query) → eager. Guard anti-regressão: se alguém introduzir
// defer sem necessidade, quebra o espírito documentado do charter (p95 < 500ms,
// zero I/O caro no controller).
// ─────────────────────────────────────────────────────────────────────────────

it('UC-DSR-10 · Controller é render estático (sem Inertia::defer no census)', function () {
    $controllerPath = base_path('Modules/Governance/Http/Controllers/DsRolloutController.php');
    expect(file_exists($controllerPath))->toBeTrue();

    $source = file_get_contents($controllerPath);
    // `Inertia::defer(` com parêntese: o docblock do Controller CITA "sem Inertia::defer" e
    // a busca sem parêntese reprovava o próprio comentário que explica a decisão.
    expect($source)->not->toContain('Inertia::defer(');
    expect($source)->toContain("Inertia::render('governance/DsRollout'");
});
