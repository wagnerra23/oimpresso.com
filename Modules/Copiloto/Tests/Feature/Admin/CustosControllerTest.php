<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Services\CustosService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * US-COPI-070 — Cobertura mínima do dashboard de custos de IA.
 *
 * Não usa RefreshDatabase: roda contra DB dev real (UltimatePOS tem 100+
 * migrations + triggers que não migram bem em sqlite). Limpamos no afterEach.
 *
 * Marca {{ skipped }} se não houver business/user no banco.
 */

function copiCustosBootstrapBusinessUser(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível — provável SQLite vazio em CI: '.$e->getMessage());
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

    foreach (['copiloto.access', 'copiloto.admin.custos.view'] as $name) {
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

function copiCustosGiveAdminPerm(int $businessId, User $user): void
{
    $perm = Permission::where('name', 'copiloto.admin.custos.view')->first();
    if (! $user->hasPermissionTo($perm)) {
        $user->givePermissionTo($perm);
    }
}

function copiCustosRevokeAdminPerm(User $user): void
{
    $perm = Permission::where('name', 'copiloto.admin.custos.view')->first();
    if ($perm && $user->hasPermissionTo($perm)) {
        $user->revokePermissionTo($perm);
    }
}

afterEach(function () {
    try {
        DB::table('copiloto_mensagens')
            ->whereIn('conversa_id', function ($q) {
                $q->select('id')
                  ->from('copiloto_conversas')
                  ->where('titulo', 'like', '__test_us_copi_070__%');
            })
            ->delete();

        DB::table('copiloto_conversas')
            ->where('titulo', 'like', '__test_us_copi_070__%')
            ->delete();
    } catch (\Throwable $e) {
        // sem tabelas (SQLite :memory: em CI) — nada a limpar
    }
});

it('calcula R$ corretamente a partir de tokens × pricing × câmbio', function () {
    config([
        'copiloto.ai.pricing.gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'copiloto.ai.cambio_brl_usd'      => 5.0,
        'copiloto.ai.pricing_default_model' => 'gpt-4o-mini',
    ]);

    $svc = new CustosService;

    // 1.000 tokens input → 0.00015 USD; 1.000 tokens output → 0.0006 USD
    // total USD = 0.00075 ; × 5.0 BRL = 0.00375 → arredondado pra 0.00 (centavos)
    expect($svc->calcularCustoBrl(1000, 1000))->toBe(0.00);

    // 100k input + 50k output → 0.015 USD + 0.030 USD = 0.045 USD × 5 = 0.225 → 0.23
    expect($svc->calcularCustoBrl(100_000, 50_000))->toBe(0.23);

    // 1M tokens input → 0.15 USD × 5 = 0.75; 1M output → 0.60 USD × 5 = 3.00; total 3.75
    expect($svc->calcularCustoBrl(1_000_000, 1_000_000))->toBe(3.75);
});

it('responde 403 para usuário sem a permissão copiloto.admin.custos.view', function () {
    [, $user] = copiCustosBootstrapBusinessUser();

    if ($user->can('superadmin') || $user->can('copiloto.admin.custos.view')) {
        // garante user "comum" pra esse caso
        copiCustosRevokeAdminPerm($user);
    }

    $this->actingAs($user);
    $response = $this->get('/copiloto/admin/custos');

    expect($response->status())->toBe(403);
});

it('responde 200 para usuário com permissão e devolve a estrutura esperada', function () {
    [, $user] = copiCustosBootstrapBusinessUser();
    copiCustosGiveAdminPerm($user->business_id, $user);

    $this->actingAs($user);

    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    $response = $this->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get('/copiloto/admin/custos?preset=mes_atual');

    expect($response->status())->toBe(200);

    $payload = json_decode($response->getContent(), true);
    expect($payload)->toBeArray()
        ->and($payload['component'] ?? null)->toBe('Copiloto/Admin/Custos/Index');

    $props = $payload['props'] ?? [];
    expect($props)->toHaveKeys(['kpis', 'por_usuario', 'serie_diaria', 'periodo', 'filters', 'pricing']);
    expect($props['kpis'])->toHaveKeys(['custo_brl', 'mensagens', 'tokens', 'usuarios_ativos']);
});

it('isola consumo por business_id (scope multi-tenant)', function () {
    [$business, $user] = copiCustosBootstrapBusinessUser();

    $outroBusiness = Business::where('id', '!=', $business->id)->first();
    if (! $outroBusiness) {
        test()->markTestSkipped('Precisa de >1 business no banco pra testar isolamento.');
    }

    $svc = new CustosService;
    $inicio = now()->startOfMonth();
    $fim    = now()->endOfMonth();

    // Snapshot ANTES do insert no outro business
    $antesBusiness = $svc->painel($business->id, $inicio, $fim);

    $convOutroId = DB::table('copiloto_conversas')->insertGetId([
        'business_id' => $outroBusiness->id,
        'user_id'     => $user->id,
        'titulo'      => '__test_us_copi_070__outro_business',
        'status'      => 'ativa',
        'iniciada_em' => now(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    DB::table('copiloto_mensagens')->insert([
        'conversa_id' => $convOutroId,
        'role'        => 'user',
        'content'     => 'mensagem do outro business — não deve vazar',
        'tokens_in'   => 100_000,
        'tokens_out'  => 50_000,
        'created_at'  => now(),
    ]);

    // Snapshot DEPOIS do insert
    $depoisBusiness = $svc->painel($business->id, $inicio, $fim);
    $painelOutro    = $svc->painel($outroBusiness->id, $inicio, $fim);

    // Asserção forte: KPIs do business "principal" NÃO mudam por insert em outro
    expect($depoisBusiness['kpis']['tokens'])->toBe($antesBusiness['kpis']['tokens']);
    expect($depoisBusiness['kpis']['mensagens'])->toBe($antesBusiness['kpis']['mensagens']);

    // E o outro business retornou pelo menos os 150k injetados
    expect($painelOutro['kpis']['tokens'])->toBeGreaterThanOrEqual(150_000);
});
