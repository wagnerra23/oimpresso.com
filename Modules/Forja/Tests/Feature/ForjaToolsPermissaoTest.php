<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Contracts\Tool;
use Modules\Forja\Services\ToolRegistry;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread 11 do playbook Forja — decisão [W] D7 (2026-10-07):
 * "permissão própria + auditoria filtrada por empresa + autor = usuário logado".
 *
 * UC-TOOLS-03 `[T0]` — sem `forja.tools.execute`, executar tool dá 403 e nada é auditado.
 * UC-TOOLS-04 `[T0]` — o audit da tela é da empresa da sessão: execução do 99 não aparece pro 98.
 * UC-TOOLS-05        — o autor da execução é o usuário logado, nunca 'wagner' fixo.
 * UC-TSCOPE-04 `[T0]` — sem `forja.team_scopes.manage`, conceder/revogar dá 403 e nada muda;
 *                      com ela, o autor da concessão é o usuário logado.
 *
 * Fonte: `prototipo-ui/cowork/Wagner/cowork-inbox/forja/playbook/11-ferramentas-permissao.md`
 * + `_DECISOES-W-2026-10-07c.md` (D7) + ADR 0093. Nunca o `.tsx`.
 *
 * Tier 0 (ADR 0358): tenant fictício 98 × cliente fictício 99. NUNCA biz=4.
 * Tools FALSAS num ToolRegistry real (mesmo motivo do ToolsContratoTest).
 *
 * @see Modules/Forja/Http/Controllers/Admin/ToolsController.php
 * @see Modules/Forja/Http/Controllers/Admin/TeamScopesController.php
 */

const TPERM_TOOL = 'uc_tperm_fake_leitura';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a stack UltimatePOS das rotas /ads exige schema MySQL.');
    }
    foreach (['users', 'business', 'mcp_tool_executions', 'mcp_user_module_access'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode com DB_CONNECTION=mysql e o schema baseline.");
        }
    }

    $registry = new ToolRegistry();
    $registry->register(new class implements Tool
    {
        public function name(): string { return TPERM_TOOL; }

        public function description(): string { return 'Tool falsa do ForjaToolsPermissaoTest.'; }

        public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }

        public function category(): string { return 'leitura'; }

        public function isReadOnly(): bool { return true; }

        public function execute(array $input): array { return ['ok' => true, 'output' => [], 'error' => null]; }
    });
    app()->instance(ToolRegistry::class, $registry);
});

/** Usuário apto a logar, com só as permissões pedidas (usuarioComPermissoes não dá Admin#). */
function tpermUsuario(array $permissoes, $business = null): User
{
    // O helper resolve a permissão pelo cache do Spatie ANTES de limpá-lo; se outro
    // teste a criou numa transação já revertida, o id do cache não existe (FK 1452).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $user = test()->usuarioComPermissoes($permissoes, $business);
    DB::table('users')->where('id', $user->id)->update([
        'username' => 'tperm_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    return User::findOrFail($user->id);
}

function tpermSessao(User $user)
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => (int) $user->business_id,
            'user' => ['business_id' => (int) $user->business_id, 'id' => $user->id],
        ]);
}

it('UC-TOOLS-03 — sem forja.tools.execute, executar tool dá 403 e nada é auditado', function () {
    $semPermissao = tpermUsuario([]);
    $antes = DB::table('mcp_tool_executions')->max('id') ?? 0;

    tpermSessao($semPermissao)
        ->postJson('/ads/admin/tools/'.TPERM_TOOL.'/execute', ['input' => []])
        ->assertForbidden();

    expect(DB::table('mcp_tool_executions')->where('id', '>', $antes)->count())->toBe(0);

    // Controle positivo: com a permissão, a MESMA chamada passa e audita — o 403 acima
    // não vem de outra camada (rota, middleware, tool inexistente).
    tpermSessao(tpermUsuario(['forja.tools.execute']))
        ->postJson('/ads/admin/tools/'.TPERM_TOOL.'/execute', ['input' => []])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(DB::table('mcp_tool_executions')->where('id', '>', $antes)->count())->toBe(1);
});

it('UC-TOOLS-05 — o autor da execução é o usuário logado, nunca "wagner" fixo', function () {
    $user = tpermUsuario(['forja.tools.execute']);
    $antes = DB::table('mcp_tool_executions')->max('id') ?? 0;

    tpermSessao($user)
        ->postJson('/ads/admin/tools/'.TPERM_TOOL.'/execute', ['input' => []])
        ->assertOk();

    $linha = DB::table('mcp_tool_executions')->where('id', '>', $antes)->first();
    expect($linha)->not->toBeNull();
    expect($linha->triggered_by)->toBe($user->username);
    expect((int) $linha->business_id)->toBe((int) $user->business_id);
});

it('UC-TOOLS-04 — o audit da tela é da empresa da sessão: execução do 99 não aparece pro 98', function () {
    $biz98 = (int) $this->seededTenant()->id;
    $biz99 = (int) $this->seededSupportClientTenant()->id;
    expect($biz98)->not->toBe($biz99);

    $linha = fn (int $biz, string $tool) => DB::table('mcp_tool_executions')->insertGetId([
        'business_id' => $biz, 'decision_id' => null, 'tool_name' => $tool, 'is_read_only' => 1,
        'input' => '[]', 'ok' => 1, 'output' => null, 'error' => null, 'duration_ms' => 1,
        'triggered_by' => 'tperm_fixture', 'created_at' => now(),
    ]);
    $id98 = $linha($biz98, 'uc_tperm_audit_98');
    $id99 = $linha($biz99, 'uc_tperm_audit_99');

    $user = tpermUsuario([], $this->seededTenant());
    $inicial = tpermSessao($user)->get('/ads/admin/tools');
    expect($inicial->status())->toBe(200);

    // Props deferidas: o browser as pede num partial reload (X-Requested-With vai
    // junto porque o cliente Inertia o manda SEMPRE — lápide §5 2026-09-08).
    $parcial = tpermSessao($user)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) data_get($inicial->viewData('page'), 'version'),
            'X-Inertia-Partial-Component' => 'ads/Admin/Tools',
            'X-Inertia-Partial-Data' => 'recent_executions,kpis',
        ])
        ->get('/ads/admin/tools');
    expect($parcial->status())->toBe(200);

    $ids = collect(data_get($parcial->json(), 'props.recent_executions', []))->pluck('id')->map(fn ($i) => (int) $i);

    // Âncora positiva: a linha do 98 aparece — sem ela, o "99 não aparece" passaria
    // com uma lista vazia.
    expect($ids->contains($id98))->toBeTrue();
    expect($ids->contains($id99))->toBeFalse();

    // O KPI conta só a empresa da sessão.
    $esperado = DB::table('mcp_tool_executions')
        ->where('business_id', $biz98)
        ->where('created_at', '>=', now()->subDays(7))
        ->count();
    expect((int) data_get($parcial->json(), 'props.kpis.executions_7d'))->toBe($esperado);
});

it('UC-TSCOPE-04 — sem forja.team_scopes.manage, conceder e revogar dão 403; com ela, o autor é o logado', function () {
    $biz = $this->seededTenant();
    $dev = tpermUsuario([], $biz);
    $semPermissao = tpermUsuario([], $biz);

    $dados = ['user_id' => $dev->id, 'module' => 'Compras', 'can_read' => true, 'can_write' => true];

    tpermSessao($semPermissao)->post('/ads/admin/team-scopes/grant', $dados)->assertForbidden();
    expect(DB::table('mcp_user_module_access')->where('user_id', $dev->id)->count())->toBe(0);

    // Com a permissão: concede, e o autor gravado é quem concedeu.
    $operador = tpermUsuario(['forja.team_scopes.manage'], $biz);
    tpermSessao($operador)->post('/ads/admin/team-scopes/grant', $dados)->assertSessionHasNoErrors();

    $acesso = DB::table('mcp_user_module_access')->where('user_id', $dev->id)->where('module', 'Compras')->first();
    expect($acesso)->not->toBeNull();
    expect($acesso->granted_by)->toBe($operador->username);

    // Revogar sem a permissão: 403, e a concessão continua de pé.
    tpermSessao($semPermissao)
        ->post('/ads/admin/team-scopes/revoke', ['user_id' => $dev->id, 'module' => 'Compras'])
        ->assertForbidden();
    expect(DB::table('mcp_user_module_access')->where('user_id', $dev->id)->where('module', 'Compras')->exists())->toBeTrue();
});
