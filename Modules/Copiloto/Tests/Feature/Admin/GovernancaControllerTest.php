<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Copiloto\Services\GovernancaService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * MEM-MCP-1.e (ADR 0053) — Cobertura mínima do dashboard de governança MCP.
 *
 * Não usa RefreshDatabase: roda contra DB dev real (UltimatePOS tem 100+
 * migrations + triggers que não migram bem em sqlite). Limpamos no afterEach
 * via marker em request_id (UUID com prefixo "test-gov-").
 */

function govBootstrapUser(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    try {
        $user = User::where('business_id', $business->id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: '.$e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    foreach (['copiloto.access', 'copiloto.mcp.usage.all'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'is_admin'         => true,
    ]);

    return [$business, $user];
}

function govGivePerm(User $user): void
{
    $perm = Permission::where('name', 'copiloto.mcp.usage.all')->first();
    if ($perm && ! $user->hasPermissionTo($perm)) {
        $user->givePermissionTo($perm);
    }
}

function govRevokePerm(User $user): void
{
    $perm = Permission::where('name', 'copiloto.mcp.usage.all')->first();
    if ($perm && $user->hasPermissionTo($perm)) {
        $user->revokePermissionTo($perm);
    }
}

function govSeedAuditRow(int $userId, array $overrides = []): string
{
    $requestId = 'test-gov-' . (string) Str::uuid();
    DB::table('mcp_audit_log')->insert(array_merge([
        'request_id'        => $requestId,
        'user_id'           => $userId,
        'business_id'       => null,
        'ts'                => now(),
        'endpoint'          => 'tools/call',
        'tool_or_resource'  => 'tasks-current',
        'status'            => 'ok',
        'duration_ms'       => 1500,
        'tokens_in'         => 100,
        'tokens_out'        => 200,
        'cache_read'        => 0,
        'cache_write'       => 0,
        'custo_brl'         => 0.01,
        'created_at'        => now(),
    ], $overrides));

    return $requestId;
}

afterEach(function () {
    try {
        DB::table('mcp_audit_log')->where('request_id', 'like', 'test-gov-%')->delete();
    } catch (\Throwable $e) {
        // tabela não existe (ambiente sem migration MCP) — nada a limpar
    }
});

it('responde 403 para usuário sem copiloto.mcp.usage.all', function () {
    [, $user] = govBootstrapUser();

    if ($user->can('superadmin') || $user->can('copiloto.mcp.usage.all')) {
        govRevokePerm($user);
    }

    $this->actingAs($user);
    $response = $this->get('/copiloto/admin/governanca');

    expect($response->status())->toBe(403);
});

it('responde 200 com permissão e props esperadas', function () {
    [, $user] = govBootstrapUser();
    govGivePerm($user);

    $this->actingAs($user);

    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get('/copiloto/admin/governanca?preset=30d');

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    expect($payload)->toBeArray()
        ->and($payload['component'] ?? null)->toBe('Copiloto/Admin/Governanca/Index');

    $props = $payload['props'] ?? [];
    expect($props)->toHaveKeys([
        'kpis', 'por_status', 'latency', 'top_tools', 'top_users',
        'denied_por_codigo', 'serie_diaria', 'periodo', 'filters',
    ]);
    expect($props['kpis'])->toHaveKeys([
        'total_calls', 'usuarios_ativos', 'custo_total', 'tokens_total', 'latency_avg_ms',
    ]);
    expect($props['latency'])->toHaveKeys(['p50', 'p95', 'p99', 'max']);
});

it('agrega calls do mcp_audit_log no painel', function () {
    [, $user] = govBootstrapUser();

    if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_audit_log')) {
        test()->markTestSkipped('Tabela mcp_audit_log não existe (rode migrations MCP).');
    }

    // Seed: 3 ok + 1 denied + 1 error, distribuídos hoje
    govSeedAuditRow($user->id, ['status' => 'ok', 'tool_or_resource' => 'tasks-current']);
    govSeedAuditRow($user->id, ['status' => 'ok', 'tool_or_resource' => 'tasks-current']);
    govSeedAuditRow($user->id, ['status' => 'ok', 'tool_or_resource' => 'decisions-search']);
    govSeedAuditRow($user->id, ['status' => 'denied', 'error_code' => 'no_permission', 'tool_or_resource' => null]);
    govSeedAuditRow($user->id, ['status' => 'error', 'error_code' => 'unknown', 'tool_or_resource' => null]);

    $svc = new GovernancaService;
    $painel = $svc->painel(now()->startOfDay(), now()->endOfDay());

    expect($painel['kpis']['total_calls'])->toBeGreaterThanOrEqual(5);
    expect($painel['kpis']['usuarios_ativos'])->toBeGreaterThanOrEqual(1);

    // Top tools deve ter tasks-current com pelo menos 2 calls
    $tasksCurrent = collect($painel['top_tools'])->firstWhere('tool', 'tasks-current');
    expect($tasksCurrent)->not->toBeNull();
    expect($tasksCurrent['calls'])->toBeGreaterThanOrEqual(2);

    // Por status: deve ter ok + denied + error
    $statusKeys = collect($painel['por_status'])->pluck('status')->toArray();
    expect($statusKeys)->toContain('ok');
    expect($statusKeys)->toContain('denied');

    // Denied por código: deve ter no_permission
    $deniedKeys = collect($painel['denied_por_codigo'])->pluck('error_code')->toArray();
    expect($deniedKeys)->toContain('no_permission');
});

it('calcula latency p50/p95 corretamente', function () {
    [, $user] = govBootstrapUser();

    if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_audit_log')) {
        test()->markTestSkipped('Tabela mcp_audit_log não existe.');
    }

    // Seed 100 rows com duration_ms = 100..1000 (10ms steps)
    for ($i = 1; $i <= 100; $i++) {
        govSeedAuditRow($user->id, ['duration_ms' => $i * 10]);
    }

    $svc = new GovernancaService;
    $painel = $svc->painel(now()->startOfDay(), now()->endOfDay());

    // Com 100 valores [10, 20, ..., 1000], p50 ≈ 500, p95 ≈ 950, p99 ≈ 990
    expect($painel['latency']['p50'])->toBeGreaterThanOrEqual(400)->toBeLessThanOrEqual(600);
    expect($painel['latency']['p95'])->toBeGreaterThanOrEqual(900)->toBeLessThanOrEqual(990);
    expect($painel['latency']['max'])->toBeGreaterThanOrEqual(900);
});

it('preset inválido cai em 30d default', function () {
    [, $user] = govBootstrapUser();
    govGivePerm($user);

    $this->actingAs($user);
    $response = $this->get('/copiloto/admin/governanca?preset=preset_invalido_que_nao_existe');

    expect($response->status())->toBe(200);
});
