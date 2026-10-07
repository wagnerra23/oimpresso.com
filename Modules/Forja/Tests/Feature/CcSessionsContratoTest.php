<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Sessões CC · contrato da tela `/team-mcp/cc-sessions` (feed + drawer de thread).
 *
 * Cobre os UC de `Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.casos.md`.
 * Os UC saem do charter + `cc-sessions-visual-comparison.md` + SDD do hub (§3, §5.1,
 * CU-TEAM-09/13) — não do `.tsx`. O controller só confirma o comportamento.
 *
 * DUAS FORÇAS, e a distinção importa (ADR 0264 G-7):
 *   - pernas de REGISTRO (rota, middleware, verbo, escopo de query) rodam em qualquer
 *     driver, inclusive na lane sqlite — provam que a trava está DECLARADA;
 *   - pernas de REQUEST (403 real, 404 de sessão alheia, thread truncada, paginação)
 *     exigem a stack UltimatePOS → só produzem veredito na lane MySQL `forja-pest.yml`.
 *     Em sqlite elas PULAM, e skip não é cobertura (leia assertions, não "0 failed").
 *
 * Tenant: o canônico de teste (biz=98, ADR 0358) via `seededTenant()`; o "outro business"
 * do caso Tier 0 é qualquer business ≠ esse (o seed do CI cria o biz=2). NUNCA biz=4.
 *
 * @see Modules\Forja\Http\Controllers\CcSessionsController
 * @see Modules\Jana\Entities\Mcp\McpCcSession::scopeAcessivelPara
 * @see memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md
 */

const CCS_READ_TEAM = 'jana.cc.read.team';
const CCS_READ_ALL = 'jana.cc.read.all';

/** Rotas `team-mcp.cc.*` como o router as enxerga. */
function ccsRotas(): \Illuminate\Support\Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with((string) $r->getName(), 'team-mcp.cc.'));
}

/** A stack UltimatePOS + as tabelas mcp_cc_* só existem no MySQL real. */
function ccsExigeSchemaMysql(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: middlewares UltimatePOS exigem schema MySQL com '.
            'business/users/permissions. Esta perna só dá veredito na lane forja-pest.yml.'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('mcp_cc_sessions') || ! Schema::hasTable('mcp_cc_messages')) {
        test()->markTestSkipped('Schema ausente (users/mcp_cc_*) — rode com DB_CONNECTION=mysql.');
    }
}

/**
 * Usuário NOVO no tenant de teste com exatamente as permissões pedidas.
 * Novo de propósito: sem `Admin#` (o `Gate::before` liberaria tudo e o caso negativo
 * viraria decorativo) e sem herdar permissão de outro teste.
 *
 * @param  list<string>  $permissoes
 */
function ccsUsuario(array $permissoes): User
{
    ccsExigeSchemaMysql();

    try {
        $business = test()->seededTenant(); // biz=98 (ADR 0358) — NUNCA biz=4
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    $user = test()->usuarioComPermissoes($permissoes, $business);

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'user.id'          => $user->id,
    ]);

    return $user;
}

/** Fixture de sessão. Insert cru de propósito: é cenário, não o caminho sob teste. */
function ccsSessao(int $userId, int $businessId, array $extra = []): string
{
    $uuid = (string) Str::uuid();

    DB::table('mcp_cc_sessions')->insert(array_merge([
        'session_uuid'   => $uuid,
        'user_id'        => $userId,
        'business_id'    => $businessId,
        'project_path'   => 'D:\\fixture-ccs',
        'git_branch'     => 'claude/fixture-ccs',
        'started_at'     => now()->subHour(),
        'total_messages' => 0,
        'status'         => 'closed',
        'summary_auto'   => 'Fixture do contrato CcSessions',
        'created_at'     => now(),
        'updated_at'     => now(),
    ], $extra));

    return $uuid;
}

/** N mensagens na sessão, em lote (é montagem de cenário). */
function ccsMensagens(string $sessionUuid, int $userId, int $businessId, int $quantas): void
{
    $sessionId = (int) DB::table('mcp_cc_sessions')->where('session_uuid', $sessionUuid)->value('id');
    $base = now()->subHour();

    $linhas = [];
    for ($i = 0; $i < $quantas; $i++) {
        $linhas[] = [
            'session_id'   => $sessionId,
            'msg_uuid'     => (string) Str::uuid(),
            'user_id'      => $userId,
            'business_id'  => $businessId,
            'msg_type'     => $i % 2 === 0 ? 'user' : 'assistant',
            'content_text' => 'mensagem '.$i,
            'ts'           => $base->copy()->addSeconds($i),
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }

    foreach (array_chunk($linhas, 200) as $lote) {
        DB::table('mcp_cc_messages')->insert($lote);
    }
}

// ---------------------------------------------------------------------------
// UC-CCS-01 — a rota abre a tela (a Page existe)
// ---------------------------------------------------------------------------

it('UC-CCS-01 · a rota da tela está registrada e aponta pro CcSessionsController@index', function () {
    $route = Route::getRoutes()->getByName('team-mcp.cc.index');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('team-mcp/cc-sessions');
    expect($route->getActionName())->toEndWith('CcSessionsController@index');
});

it('UC-CCS-01 · o componente Inertia que o controller renderiza existe em disco', function () {
    // Duas fontes cruzadas: a string de render no controller × a árvore de arquivos.
    // Renomear/mover a Page sem atualizar o render dá tela branca (Inertia 500).
    $controller = file_get_contents(base_path('Modules/Forja/Http/Controllers/CcSessionsController.php'));

    expect($controller)->toContain("Inertia::render('team-mcp/CcSessions/Index'");
    expect(file_exists(base_path('Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.tsx')))->toBeTrue(
        'O controller renderiza team-mcp/CcSessions/Index mas o .tsx não existe — Inertia 500.'
    );
});

// ---------------------------------------------------------------------------
// UC-CCS-02 — acesso: auth + jana.cc.read.team em TODA rota da tela
// ---------------------------------------------------------------------------

it('UC-CCS-02 · toda rota team-mcp.cc.* exige auth + can:jana.cc.read.team no registro', function () {
    $rotas = ccsRotas();

    expect($rotas->count())->toBeGreaterThanOrEqual(2, 'esperava index + show (+ search) sob team-mcp.cc.*');

    foreach ($rotas as $r) {
        $mw = $r->gatherMiddleware();

        expect(in_array('auth', $mw, true))->toBeTrue("Rota {$r->getName()} sem `auth`.");

        $exige = collect($mw)->contains(fn ($m): bool => is_string($m) && str_contains($m, CCS_READ_TEAM));
        expect($exige)->toBeTrue(
            "Rota {$r->getName()} não exige `".CCS_READ_TEAM.'` — a thread de sessão Claude Code '.
            'carrega prompt e saída de ferramenta; sem a trava qualquer funcionário logado lê. '.
            'Middleware visto: '.json_encode($mw)
        );
    }
});

it('UC-CCS-02 · autenticado SEM jana.cc.read.team leva 403 na tela', function () {
    $user = ccsUsuario([]); // usuário novo, sem papel Admin e sem a permissão

    $this->actingAs($user)->get('/team-mcp/cc-sessions')->assertStatus(403);
});

// ---------------------------------------------------------------------------
// UC-CCS-03 — sem jana.cc.read.all, o dev só vê as PRÓPRIAS sessões
// ---------------------------------------------------------------------------

it('UC-CCS-03 · o escopo acessivelPara restringe ao próprio user_id quando falta read.all', function () {
    // Perna de registro: monta a query sem tocar no banco. Usuário de teste em memória
    // cujo `can()` responde fixo — isola a regra do escopo da infraestrutura do Spatie.
    $semAll = new class extends User {
        public function can($abilities, $arguments = []): bool
        {
            return false;
        }
    };
    $semAll->id = 980001;

    $comAll = new class extends User {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    };
    $comAll->id = 980002;

    $filtrosDeUsuario = static function ($query): array {
        return collect($query->getQuery()->wheres)
            ->filter(fn ($w): bool => ($w['column'] ?? null) === 'user_id' || str_ends_with((string) ($w['column'] ?? ''), '.user_id'))
            ->values()
            ->all();
    };

    $restrito = $filtrosDeUsuario(McpCcSession::query()->acessivelPara($semAll));
    expect($restrito)->toHaveCount(1, 'sem read.all a query tem que filtrar por user_id');
    expect($restrito[0]['value'] ?? null)->toBe(980001);

    // Controle: com read.all o MESMO escopo não filtra por usuário — prova que a
    // perna acima discrimina, e não que o escopo filtra sempre.
    expect($filtrosDeUsuario(McpCcSession::query()->acessivelPara($comAll)))->toBe([]);

    // Sem usuário nenhum: nada passa.
    $nulo = McpCcSession::query()->acessivelPara(null)->toSql();
    expect(str_contains($nulo, '1=0'))->toBeTrue('acessivelPara(null) deveria fechar a query (1=0)');
});

it('UC-CCS-03 · sem read.all, abrir a thread de sessão de COLEGA dá 404; a própria abre', function () {
    // O colega nasce ANTES e direto pelo helper: `ccsUsuario` grava a sessão do request,
    // e quem fica nela tem que ser o `$eu`.
    ccsExigeSchemaMysql();
    $colega = test()->usuarioComPermissoes([CCS_READ_TEAM], test()->seededTenant());
    $eu = ccsUsuario([CCS_READ_TEAM]);
    $biz = (int) $eu->business_id;

    $minha = ccsSessao($eu->id, $biz);
    $dele = ccsSessao($colega->id, $biz);

    $this->actingAs($eu)->getJson("/team-mcp/cc-sessions/{$minha}")
        ->assertStatus(200)
        ->assertJsonPath('session.session_uuid', $minha);

    $this->actingAs($eu)->getJson("/team-mcp/cc-sessions/{$dele}")->assertStatus(404);
});

// ---------------------------------------------------------------------------
// UC-CCS-04 [T0] — sessão de OUTRO business nunca aparece, nem pra read.all
// ---------------------------------------------------------------------------

it('UC-CCS-04 · com read.all, sessão de outro business segue invisível (404)', function () {
    $admin = ccsUsuario([CCS_READ_TEAM, CCS_READ_ALL]);
    $biz = (int) $admin->business_id;

    $outroBiz = (int) DB::table('business')->where('id', '!=', $biz)->where('id', '!=', 4)->value('id');
    if ($outroBiz === 0) {
        test()->markTestSkipped('Sem segundo business no banco pra provar isolamento (o seed do CI cria o biz=2).');
    }
    $donoOutro = (int) (DB::table('users')->where('business_id', $outroBiz)->value('id') ?? 0);
    if ($donoOutro === 0) {
        test()->markTestSkipped("Business {$outroBiz} sem usuário pra ser dono da sessão.");
    }

    $mesmoBiz = ccsSessao($admin->id, $biz);
    $alheia = ccsSessao($donoOutro, $outroBiz);

    // Controle positivo: a rota funciona pra sessão do próprio business.
    $this->actingAs($admin)->getJson("/team-mcp/cc-sessions/{$mesmoBiz}")->assertStatus(200);

    // O caso Tier 0: read.all libera o TIME, nunca outro tenant (ADR 0093).
    $this->actingAs($admin)->getJson("/team-mcp/cc-sessions/{$alheia}")->assertStatus(404);
});

// ---------------------------------------------------------------------------
// UC-CCS-05 — a thread do drawer vem limitada a 500 mensagens, com aviso
// ---------------------------------------------------------------------------

it('UC-CCS-05 · thread com mais de 500 mensagens chega cortada em 500 e marcada truncated', function () {
    $eu = ccsUsuario([CCS_READ_TEAM]);
    $biz = (int) $eu->business_id;

    $longa = ccsSessao($eu->id, $biz, ['total_messages' => 503]);
    ccsMensagens($longa, $eu->id, $biz, 503);

    $curta = ccsSessao($eu->id, $biz, ['total_messages' => 3]);
    ccsMensagens($curta, $eu->id, $biz, 3);

    $r = $this->actingAs($eu)->getJson("/team-mcp/cc-sessions/{$longa}")->assertStatus(200);
    expect(count($r->json('messages')))->toBe(500);
    expect($r->json('truncated'))->toBeTrue('a tela precisa saber que a thread foi cortada pra avisar');

    // Controle: thread curta vem inteira e sem o aviso.
    $c = $this->actingAs($eu)->getJson("/team-mcp/cc-sessions/{$curta}")->assertStatus(200);
    expect(count($c->json('messages')))->toBe(3);
    expect($c->json('truncated'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// UC-CCS-06 — tela de leitura: nenhuma rota escreve
// ---------------------------------------------------------------------------

it('UC-CCS-06 · toda rota team-mcp.cc.* é GET-only', function () {
    foreach (ccsRotas() as $r) {
        $verbos = array_values(array_diff($r->methods(), ['HEAD']));

        expect($verbos)->toBe(['GET'],
            "Rota {$r->getName()} aceita ".implode('/', $verbos).'. A tela de sessões é read-only '.
            '(charter: Anti-hooks "NÃO escreve nada"; curadoria ficou fora da UI).'
        );
    }
});

// ---------------------------------------------------------------------------
// UC-CCS-07 — feed paginado 25 por página, mais recente primeiro
// ---------------------------------------------------------------------------

it('UC-CCS-07 · o feed chega paginado em 25 e na ordem do mais recente', function () {
    $eu = ccsUsuario([CCS_READ_TEAM]);
    $biz = (int) $eu->business_id;

    $antiga = ccsSessao($eu->id, $biz, ['started_at' => now()->subDays(3)]);
    $nova = ccsSessao($eu->id, $biz, ['started_at' => now()->subMinutes(5)]);

    // `sessions` é Inertia::defer — só vem no partial reload. O header de versão pergunta
    // ao middleware (sem ele o Inertia devolve 409 antes do controller rodar).
    $r = $this->actingAs($eu)->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Requested-With'            => 'XMLHttpRequest',
        'X-Inertia-Version'           => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'team-mcp/CcSessions/Index',
        'X-Inertia-Partial-Data'      => 'sessions',
    ])->get('/team-mcp/cc-sessions?user_id='.$eu->id);

    $r->assertStatus(200);
    $sessions = json_decode($r->getContent(), true)['props']['sessions'] ?? null;

    expect($sessions)->toBeArray('o partial reload não entregou `sessions`');
    expect((int) $sessions['per_page'])->toBe(25);

    $uuids = array_column($sessions['data'], 'session_uuid');
    $posNova = array_search($nova, $uuids, true);
    $posAntiga = array_search($antiga, $uuids, true);

    expect($posNova)->toBeInt('a sessão recente não veio no feed');
    expect($posAntiga)->toBeInt('a sessão antiga não veio no feed');
    expect($posNova)->toBeLessThan($posAntiga);
});
