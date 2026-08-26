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

/**
 * Os dois tenants vem do que a LANE ja semeia, nao de `Business::create`.
 *
 * Tentei criar (98 e 97) e o CI mostrou dois defeitos na minha suposicao:
 *   1. `business.owner_id` e FK NOT NULL pra `users` — criar sem dono viola a constraint
 *      `business_owner_id_foreign`;
 *   2. `id` nao e fillable, entao o `firstOrCreate(['id' => 98], ...)` nem gravava o id
 *      pedido — o INSERT saiu com `(name, currency_id, ...)` e id auto-increment.
 *
 * Reusar o que existe elimina os dois e ainda testa contra o schema real da lane.
 * biz=4 (ROTA LIVRE, producao) fica FORA por regra — ADR 0358.
 */
function tsaTenants(): array
{
    $ids = DB::table('business')->where('id', '!=', 4)->orderBy('id')->limit(2)->pluck('id')->all();

    if (count($ids) < 2) {
        test()->markTestSkipped('Lane com menos de 2 business — sem como provar isolamento entre tenants.');
    }

    return [(int) $ids[0], (int) $ids[1]];
}

function tsaSeed(): array
{
    [$a, $b] = tsaTenants();

    $user = User::firstOrCreate(
        ['username' => 'tsa_superadmin'],
        [
            'email' => 'tsa_superadmin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => $a,
            'first_name' => 'TSA',
            'last_name' => 'Super',
        ]
    );

    // O usuario pode ter sido criado num run anterior com outro business.
    if ((int) $user->business_id !== $a) {
        $user->business_id = $a;
        $user->save();
    }

    $perm = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($perm);
    }

    $ids = [];
    foreach ([$a, $b] as $biz) {
        $ids[$biz] = DB::table('fin_titulos')->insertGetId([
            'business_id' => $biz,
            'numero' => "TSA-{$biz}",
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

    return [$user, $a, $b, $ids];
}

it('UC-FIN-TIER0 - superadmin logado no biz A NAO enxerga titulo do biz B', function () {
    [$user, $a, $b, $ids] = tsaSeed();

    $this->actingAs($user);
    session(['user.business_id' => $a]);

    // Controle: a permissao que ANTES abria o bypass esta de fato presente. Sem isto o
    // teste poderia passar por AUSENCIA do gatilho, nao por isolamento.
    expect($user->can('superadmin'))->toBeTrue();
    expect($a)->not->toBe($b);

    $vistos = Titulo::whereIn('id', array_values($ids))->pluck('business_id')->all();

    DB::table('fin_titulos')->whereIn('id', array_values($ids))->delete();

    expect(array_map('intval', $vistos))->toBe([$a]);
});

it('UC-FIN-TIER0b - withoutGlobalScope segue enxergando os dois (a escotilha nao foi fechada)', function () {
    [$user, $a, $b, $ids] = tsaSeed();

    $this->actingAs($user);
    session(['user.business_id' => $a]);

    $vistos = Titulo::withoutGlobalScope(\Modules\Financeiro\Models\Concerns\BusinessScopeImpl::class)
        ->whereIn('id', array_values($ids))
        ->pluck('business_id')
        ->map(fn ($x) => (int) $x)
        ->sort()
        ->values()
        ->all();

    DB::table('fin_titulos')->whereIn('id', array_values($ids))->delete();

    $esperado = [$a, $b];
    sort($esperado);

    expect($vistos)->toBe($esperado);
});
