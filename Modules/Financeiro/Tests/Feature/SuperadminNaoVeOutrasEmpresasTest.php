<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * GUARD Tier 0 — superadmin NAO ve dado de outra empresa (ADR 0093).
 *
 * DECISAO [W] 2026-08-26, textual: "nao, so da empresa selecionada".
 *
 * Ate essa data o `BusinessScopeImpl::apply()` fazia `return` seco quando o usuario
 * tinha a permissao `superadmin` — o scope NAO filtrava e a tela mostrava todas as
 * empresas. Exposicao medida em producao no dia:
 *
 *   fin_titulos           151.427 linhas de 3 empresas
 *   fin_contas_bancarias       94 linhas de 69 empresas
 *   fin_planos_conta          433 linhas de 88 empresas
 *
 * Este caso existe pra impedir que o atalho volte. Ele NAO testa o `withoutGlobalScope`
 * explicito, que continua sendo o caminho legitimo pra cross-tenant (jobs, comandos,
 * seeders) — e cujo controle-positivo esta no fim.
 *
 * Tenants ficticios (ADR 0358): 98 e 97. NUNCA biz=4 (ROTA LIVRE, producao).
 */
beforeEach(function () {
    if (! Schema::hasTable('fin_titulos') || ! Schema::hasTable('business')) {
        test()->markTestSkipped('Schema ausente — rode as migrations.');
    }
});

const BIZ_A = 98;
const BIZ_B = 97;

function tsaSeed(): array
{
    foreach ([BIZ_A, BIZ_B] as $b) {
        Business::firstOrCreate(['id' => $b], ['name' => "Tenant ficticio {$b}", 'currency_id' => 1]);
    }

    $user = User::firstOrCreate(
        ['username' => 'tsa_superadmin'],
        [
            'email' => 'tsa_superadmin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_A,
            'first_name' => 'TSA',
            'last_name' => 'Super',
        ]
    );
    $perm = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($perm);
    }

    $ids = [];
    foreach ([BIZ_A, BIZ_B] as $b) {
        $ids[$b] = DB::table('fin_titulos')->insertGetId([
            'business_id' => $b,
            'numero' => "TSA-{$b}",
            'tipo' => 'receber',
            'status' => 'aberto',
            'cliente_descricao' => 'Cliente ficticio do guard',
            'valor_total' => 10.00,
            'valor_aberto' => 10.00,
            'moeda' => 'BRL',
            'emissao' => '2026-06-11',
            'vencimento' => '2026-06-11',
            'competencia_mes' => '2026-06',
            'parcela_numero' => 1,
            'parcela_total' => 1,
            'origem' => 'manual',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return [$user, $ids];
}

it('UC-FIN-TIER0 - superadmin logado no biz A NAO enxerga titulo do biz B', function () {
    [$user, $ids] = tsaSeed();

    $this->actingAs($user);
    session(['user.business_id' => BIZ_A]);

    // Controle: a permissao que ANTES abria o bypass esta de fato presente.
    expect($user->can('superadmin'))->toBeTrue();

    $vistos = Titulo::whereIn('id', array_values($ids))->pluck('business_id')->all();

    DB::table('fin_titulos')->whereIn('id', array_values($ids))->delete();

    expect($vistos)->toBe([BIZ_A]);
});

it('UC-FIN-TIER0b - withoutGlobalScope segue enxergando os dois (a escotilha nao foi fechada)', function () {
    [$user, $ids] = tsaSeed();

    $this->actingAs($user);
    session(['user.business_id' => BIZ_A]);

    $vistos = Titulo::withoutGlobalScope(\Modules\Financeiro\Models\Concerns\BusinessScopeImpl::class)
        ->whereIn('id', array_values($ids))
        ->pluck('business_id')
        ->sort()
        ->values()
        ->all();

    DB::table('fin_titulos')->whereIn('id', array_values($ids))->delete();

    expect($vistos)->toBe([BIZ_B, BIZ_A]);
});
