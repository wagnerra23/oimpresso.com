<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Services\ScorecardBuilderService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Scorecard · contrato da tela `/team-mcp/scorecard` (Facts+Checks).
 *
 * Cobre os UC de `resources/js/Pages/team-mcp/Scorecard/Index.casos.md` que estavam
 * ÓRFÃOS — nenhum teste do repo citava `UC-SC-*` (medido 2026-07-28, `git grep -l`
 * sobre os dirs de teste (Modules, tests, e2e): 0 ocorrências pros 8.
 *
 *   - UC-SC-01 — a rota existe E o componente Inertia que ela renderiza existe
 *   - UC-SC-03 — Facts: as 7 chaves canônicas, com os tipos certos
 *   - UC-SC-04 — Checks: cada um com {name, ok:bool, detail:string}
 *   - UC-SC-05 — SEM série temporal no payload (a tela não pode fabricar sparkline)
 *   - UC-SC-07 — read-only: a rota é GET-only
 *   - UC-SC-08 — acesso: sem `jana.mcp.usage.all` → 403
 *
 * ── POR QUE ESTE ARQUIVO NÃO EXIGE SCHEMA MySQL (e por que isso importa) ─────
 * `ScorecardBuilderService` guarda TODO acesso a tabela com `Schema::hasTable`
 * (`buildFacts`, `checkSchema`, `checkBriefRecente`, `checkTokensSemOrphan`,
 * `checkCustoMedioSanidade`). Sem as tabelas MCP ele degrada — devolve zeros e
 * `ok=false` com detail — em vez de explodir. Logo a FORMA do contrato é provável
 * em sqlite :memory:.
 *
 * Isso é deliberado, não conveniência: na lane sqlite um teste que
 * `markTestSkipped` entrega ao manifesto por-UC o veredito `skip`, **nunca**
 * `pass` — e skip não é cobertura, é cobertura de mentira. O `Wave23ScorecardRotate
 * Test` já assertava a estrutura de buildFacts/buildChecks, mas atrás de
 * `requiresMcpSchema()` (skip em sqlite) e SEM citar UC nenhum: por isso os
 * `UC-SC-03/04` continuavam órfãos apesar do comportamento estar coberto.
 * Aqui a forma roda de verdade; o que exige request autenticado (UC-SC-08) segue
 * com skip gracioso e pertence à lane MySQL.
 *
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101 usa biz=1 canônico.
 *
 * @see Modules\Forja\Services\ScorecardBuilderService
 * @see resources/js/Pages/team-mcp/Scorecard/Index.casos.md
 * @see memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md (§5.3 F3 · CU-TEAM-08)
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

/** As 7 chaves que o frontend consome de `facts` (Index.tsx + casos.md UC-SC-03). */
function scorecardFactsKeys(): array
{
    return [
        'tokens_ativos',
        'calls_7d',
        'cost_7d_brl',
        'users_ativos_7d',
        'top_tools_7d',
        'audit_log_present',
        'tokens_table_present',
    ];
}

// -------------------------------------------------------------------------
// UC-SC-01 — a rota deixa de quebrar (a Page existe)
// -------------------------------------------------------------------------

it('UC-SC-01 · a rota do scorecard está registrada e aponta pro ScorecardController', function () {
    $route = Route::getRoutes()->getByName('team-mcp.scorecard.index');

    expect($route)->not->toBeNull('rota team-mcp.scorecard.index deve estar registrada');
    expect($route->getActionName())->toContain('ScorecardController');
});

it('UC-SC-01 · o componente Inertia que o controller renderiza existe em disco', function () {
    // O bug original do UC-SC-01: a rota existia e o controller renderizava
    // 'team-mcp/Scorecard/Index', mas o .tsx não existia → Inertia 500 (tela branca).
    // Cruza DUAS fontes: a string de render no controller × a árvore de arquivos.
    $controller = file_get_contents(base_path('Modules/Forja/Http/Controllers/ScorecardController.php'));

    expect($controller)->toContain("Inertia::render('team-mcp/Scorecard/Index'");

    expect(file_exists(base_path('resources/js/Pages/team-mcp/Scorecard/Index.tsx')))->toBeTrue(
        'O controller renderiza `team-mcp/Scorecard/Index` mas o .tsx não existe — '.
        'é exatamente o Inertia 500 que o UC-SC-01 nasceu pra impedir. '.
        'Renomeou/moveu a Page? Atualize o Inertia::render no mesmo PR.'
    );
});

// -------------------------------------------------------------------------
// UC-SC-03 — Facts são números reais do builder (forma + tipo)
// -------------------------------------------------------------------------

it('UC-SC-03 · buildFacts devolve as 7 chaves canônicas com os tipos certos', function () {
    $facts = app(ScorecardBuilderService::class)->buildFacts();

    expect($facts)->toHaveKeys(scorecardFactsKeys());

    expect($facts['tokens_ativos'])->toBeInt();
    expect($facts['calls_7d'])->toBeInt();
    expect($facts['users_ativos_7d'])->toBeInt();
    expect($facts['cost_7d_brl'])->toBeFloat();
    expect($facts['top_tools_7d'])->toBeArray();
    expect($facts['audit_log_present'])->toBeBool();
    expect($facts['tokens_table_present'])->toBeBool();
});

it('UC-SC-03 · Facts degradam sem o schema MCP em vez de explodir', function () {
    // O contrato do §7 (degradação graciosa): sem tabela, o número é 0 e a flag
    // `*_present` diz false — a tela mostra "0", não uma exception.
    $facts = app(ScorecardBuilderService::class)->buildFacts();

    if ($facts['tokens_table_present'] === false) {
        expect($facts['tokens_ativos'])->toBe(0, 'sem mcp_tokens, tokens_ativos tem que ser 0');
    }

    if ($facts['audit_log_present'] === false) {
        expect($facts['calls_7d'])->toBe(0);
        expect($facts['users_ativos_7d'])->toBe(0);
        expect($facts['top_tools_7d'])->toBe([]);
    }
})->skip(
    fn (): bool => Schema::hasTable('mcp_tokens') && Schema::hasTable('mcp_audit_log'),
    'Schema MCP presente — o caminho de degradação não é exercitável aqui.'
);

// -------------------------------------------------------------------------
// UC-SC-04 — Checks listam ok/fail com detalhe
// -------------------------------------------------------------------------

it('UC-SC-04 · buildChecks devolve itens com name/ok/detail bem tipados', function () {
    $checks = app(ScorecardBuilderService::class)->buildChecks();

    expect($checks)->toBeArray();
    expect(count($checks))->toBeGreaterThanOrEqual(4,
        'Facts+Checks (ADR 0091) prevê ao menos 4 dimensões de saúde; '.
        'hoje são 5 (schema mcp_tokens · schema mcp_audit_log · brief recente · '.
        'tokens sem orphan · custo médio).'
    );

    foreach ($checks as $c) {
        expect($c)->toHaveKeys(['name', 'ok', 'detail']);
        // NÃO citar o id do caso do semáforo aqui: o guard de cobertura casa UC por
        // SUBSTRING no corpus de testes, então mencionar um id que este arquivo não
        // testa o marcaria como coberto (cobertura-fantasma — o mesmo bug que criou
        // o META_TEST_RE em 2026-06-22). O semáforo segue sem teste, e é honesto.
        expect($c['ok'])->toBeBool('o semáforo da tela depende de `ok` ser booleano de verdade');
        expect($c['name'])->toBeString()->not->toBeEmpty();
        expect($c['detail'])->toBeString()->not->toBeEmpty(
            'todo check mostra o PORQUÊ na tela — detail vazio deixa o [W] sem ação'
        );
    }
});

it('UC-SC-04 · check de tabela inexistente reprova e diz que está ausente', function () {
    // Controle negativo: se isto passar a devolver ok=true, o semáforo do scorecard
    // vira decoração — reportaria "tudo verde" com o schema faltando.
    $check = app(ScorecardBuilderService::class)
        ->checkSchema('tabela_que_nao_existe_contrato', 'Tabela fake (controle negativo)');

    expect($check['ok'])->toBeFalse();
    expect($check['detail'])->toContain('AUSENTE');
});

// -------------------------------------------------------------------------
// UC-SC-05 — sem sparkline: o payload não carrega série temporal
// -------------------------------------------------------------------------

it('UC-SC-05 · nenhum Fact carrega série temporal (nada pra desenhar sparkline)', function () {
    $facts = app(ScorecardBuilderService::class)->buildFacts();

    // Contrato ancorado no COMPORTAMENTO ("não existe série no payload"), não numa
    // chave literal: renomear o campo não pode fazer o vazamento passar. Varre todo
    // valor de lista procurando marca de tempo nos itens.
    $marcaDeTempo = '/(^|_)(data|date|dia|day|ts|timestamp|periodo|period|serie|series|hora|hour|semana|week|mes|month)($|_)/i';

    foreach ($facts as $chave => $valor) {
        if (! is_array($valor)) {
            continue;
        }

        foreach ($valor as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (array_keys($item) as $subChave) {
                expect((bool) preg_match($marcaDeTempo, (string) $subChave))->toBeFalse(
                    "Fact `{$chave}` traz item com a chave temporal `{$subChave}`. ".
                    'O builder expõe SÓ pontos atuais — série no payload é convite pra tela '.
                    'desenhar gráfico de tendência (UC-SC-05 · §3 sem dado fantasma). '.
                    'Se a série passou a ser requisito, isso é decisão de [W]: atualize o '.
                    'casos.md e o SDD antes de mudar o teste.'
                );
            }
        }
    }
});

// -------------------------------------------------------------------------
// UC-SC-07 — read-only (a tela não muta nada)
// -------------------------------------------------------------------------

it('UC-SC-07 · o scorecard é GET-only (nenhuma rota de escrita sob o nome dele)', function () {
    $doScorecard = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with((string) $r->getName(), 'team-mcp.scorecard'));

    expect($doScorecard)->not->toBeEmpty('nenhuma rota team-mcp.scorecard.* encontrada');

    foreach ($doScorecard as $r) {
        $verbos = array_values(array_diff($r->methods(), ['HEAD']));

        expect($verbos)->toBe(['GET'],
            "Rota {$r->getName()} aceita ".implode('/', $verbos).'. O scorecard é tela de '.
            'LEITURA (UC-SC-07): o único efeito permitido é recarregar as props deferidas.'
        );
    }
});

// -------------------------------------------------------------------------
// UC-SC-08 — acesso (auth + permissão)
// -------------------------------------------------------------------------

it('UC-SC-08 · a rota do scorecard exige auth + can:jana.mcp.usage.all no registro', function () {
    // Perna que roda em QUALQUER driver: lê a stack de middleware como o ROUTER a
    // enxerga (não o texto do controller). Se o `can:` cair, isto avermelha mesmo
    // onde a stack UltimatePOS não sobe. É mais fraca que o 403 real abaixo —
    // prova que a trava está declarada, não que ela barra —, por isso as duas existem.
    $route = Route::getRoutes()->getByName('team-mcp.scorecard.index');
    $middleware = $route->gatherMiddleware();

    // `auth` vem do grupo de rotas; o `can:` vem do construtor do controller
    // (gatherMiddleware junta os dois). Comparo por SUBSTRING e não por igualdade
    // pra não quebrar por formatação do gate (`can:x` vs `can:x,arg`) — o contrato
    // é "a permissão está exigida", não "a string é exatamente esta".
    expect($middleware)->toContain('auth');

    $exigePermissao = collect($middleware)
        ->contains(fn ($m): bool => is_string($m) && str_contains($m, 'jana.mcp.usage.all'));

    expect($exigePermissao)->toBeTrue(
        'O scorecard é repo-wide cross-business por design (ADR 0093): sem o `can:'.
        'jana.mcp.usage.all'.'` ele serve a saúde do MCP de TODOS os businesses pra '.
        'qualquer funcionário logado. Middleware visto: '.json_encode($middleware)
    );
});

it('UC-SC-08 · autenticado SEM jana.mcp.usage.all leva 403 no scorecard', function () {
    // O 403 de verdade. Exige schema MySQL (stack UltimatePOS) → pula em sqlite.
    // Esta perna só produz veredito `pass` na lane MySQL `teammcp-pest.yml`.
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu/'.
            'CheckUserLogin) exigem schema MySQL com business/users/permissions (ADR 0101).'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('permissions')) {
        test()->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }

    try {
        $business = test()->seededTenant(); // biz=1 canônico (ADR 0101), nunca biz=4
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    session(['user.business_id' => $business->id, 'business.id' => $business->id]);

    // Filtra ADMIN de propósito: `Gate::before` (AuthServiceProvider) devolve true pra
    // qualquer ability de quem tem `Admin#{business_id}`. Com admin, o 403 passaria
    // mesmo se o `can:` fosse removido — falso-verde. Mesma trava do ForjaRoutesSmokeTest.
    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->get()
        ->first(static fn (User $u): bool => ! $u->hasRole('Admin#'.$business->id));

    if (! $user) {
        test()->markTestSkipped(
            "Sem usuário NÃO-admin em business_id={$business->id} — com admin o ".
            'Gate::before libera qualquer ability e o 403 seria falso-verde.'
        );
    }

    Permission::findOrCreate('jana.mcp.usage.all', 'web');
    $user->revokePermissionTo('jana.mcp.usage.all');
    $user->forgetCachedPermissions();

    session(['user.id' => $user->id]);

    $this->actingAs($user)->get(route('team-mcp.scorecard.index'))->assertStatus(403);
});
