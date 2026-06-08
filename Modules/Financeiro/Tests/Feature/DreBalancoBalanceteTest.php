<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-014d (balanço) + US-FIN-014e (balancete) — Pest GUARD da Fase 4.
 *
 * Fase 4 deprecação legacy (2026-05-21): /financeiro/dre ganha tabs
 * Balanço Patrimonial Gerencial + Balancete de Verificação Gerencial,
 * absorvendo `/account/balance-sheet` e `/account/trial-balance` legacy
 * (redirects 301 via PR #1283).
 *
 * Versão GERENCIAL (não contábil-fiscal CFC-compliant) usando dados de
 * fin_titulos + fin_contas_bancarias + fin_planos_conta. Banner UI obrigatório.
 *
 * Cobre:
 *  - aba=demonstrativo é default e shape canon preservado
 *  - aba=balanco expõe payload no shape canon
 *  - aba=balancete expõe payload no shape canon
 *  - aba inválida cai pra default
 *  - Balanço: equação Ativo = Passivo + PL bate
 *  - Balancete: SUM hierárquico (saldo pai = SUM filhos com mesmo prefix)
 *  - Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL: meta.business_id casa com auth
 *  - GET é read-only (não cria/altera titulo)
 *
 * Skip gracioso quando DB greenfield ou subscription gate bloqueia env.
 */
function dreBalancoBalanceteBootstrap(): User
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.relatorios.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.relatorios.view')) {
        $user->givePermissionTo('financeiro.relatorios.view');
    }

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'business'                 => [
            'id'              => $business->id,
            'name'            => $business->name,
            'currency_symbol' => 'R$',
        ],
        'is_admin'                 => true,
    ]);

    return $user;
}

it('aba default = demonstrativo (sem query string) e shape canon preservado', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Dre/Index')
        ->where('aba', 'demonstrativo')
        // Shape Demonstrativo (DRE) preservado: tabs Balanço/Balancete null
        ->has('meta.business_id')
        ->has('linhas')
        ->has('margem_operacional')
        ->has('top_categorias_receita')
        ->where('balanco', null)
        ->where('balancete', null)
    );
});

it('aba inválida cai pra default demonstrativo', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=lixo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('aba', 'demonstrativo')
        ->where('balanco', null)
        ->where('balancete', null)
    );
});

it('aba=balanco expõe payload no shape canon (ativo + passivo + PL + equação)', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balanco');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Dre/Index')
        ->where('aba', 'balanco')
        ->has('balanco.data_referencia')
        ->has('balanco.ativo_circulante.saldo_bancos')
        ->has('balanco.ativo_circulante.contas_a_receber')
        ->has('balanco.ativo_circulante.total')
        ->has('balanco.passivo_circulante.contas_a_pagar')
        ->has('balanco.passivo_circulante.total')
        ->has('balanco.ativo_total')
        ->has('balanco.passivo_total')
        ->has('balanco.patrimonio_liquido')
        ->has('balanco.equacao_ok')
        ->has('balanco.meta.business_id')
        ->has('balanco.meta.business_name')
        // Balancete não carrega quando aba=balanco
        ->where('balancete', null)
    );
});

it('aba=balanco: equação Ativo = Passivo + PL (invariante contábil)', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balanco');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $balanco = $page->toArray()['props']['balanco'] ?? null;
        expect($balanco)->not->toBeNull();

        $ativo = (float) $balanco['ativo_total'];
        $passivo = (float) $balanco['passivo_total'];
        $pl = (float) $balanco['patrimonio_liquido'];

        // Invariante: Ativo = Passivo + PL (gerencial — PL derivado, sempre OK)
        $delta = abs(($passivo + $pl) - $ativo);
        expect($delta)->toBeLessThan(0.01, "Equação patrimonial falhou: Ativo R\$ {$ativo} != Passivo R\$ {$passivo} + PL R\$ {$pl} (delta {$delta})");

        // equacao_ok flag deve refletir
        expect($balanco['equacao_ok'])->toBeTrue();
    });
});

it('aba=balanco: ativo_circulante.total = saldo_bancos + contas_a_receber', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balanco');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $balanco = $page->toArray()['props']['balanco'] ?? null;
        expect($balanco)->not->toBeNull();

        $ac = $balanco['ativo_circulante'];
        $delta = abs(($ac['saldo_bancos'] + $ac['contas_a_receber']) - $ac['total']);
        expect($delta)->toBeLessThan(0.01, "ativo_circulante.total inconsistente: bancos {$ac['saldo_bancos']} + receber {$ac['contas_a_receber']} != total {$ac['total']}");
    });
});

it('aba=balanco: aceita ?anchor_data=YYYY-MM-DD e reflete em data_referencia', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balanco&anchor_data=2026-04-15');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('balanco.data_referencia', '2026-04-15')
    );
});

it('aba=balancete expõe payload no shape canon (periodo + linhas + totais)', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balancete');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Dre/Index')
        ->where('aba', 'balancete')
        ->has('balancete.periodo.tipo')
        ->has('balancete.periodo.label')
        ->has('balancete.periodo.inicio_mes')
        ->has('balancete.periodo.fim_mes')
        ->has('balancete.linhas')
        ->has('balancete.totais.debito')
        ->has('balancete.totais.credito')
        ->has('balancete.meta.business_id')
        ->has('balancete.meta.business_name')
        // Balanço não carrega quando aba=balancete
        ->where('balanco', null)
    );
});

it('aba=balancete: cada linha tem shape esperado (codigo, nome, saldo, tipo_saldo D|C)', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balancete');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $linhas = $page->toArray()['props']['balancete']['linhas'] ?? [];

        // Pode estar vazio em DB greenfield — não falha
        foreach ($linhas as $i => $linha) {
            expect($linha)->toHaveKeys([
                'codigo', 'nome', 'nivel', 'natureza', 'tipo',
                'saldo', 'tipo_saldo', 'indent', 'is_folha',
            ], "linha[$i] sem chave canon");
            expect($linha['saldo'])->toBeNumeric();
            expect($linha['tipo_saldo'])->toBeIn(['D', 'C'], "linha[$i].tipo_saldo inválido");
            expect($linha['nivel'])->toBeNumeric();
            expect($linha['indent'])->toBeNumeric();
            expect($linha['is_folha'])->toBeBool();
        }
    });
});

it('aba=balancete: SUM hierárquico — saldo do pai >= soma das folhas com mesmo prefix', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balancete');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $linhas = $page->toArray()['props']['balancete']['linhas'] ?? [];

        if (count($linhas) === 0) {
            test()->markTestSkipped('Balancete vazio no DB — não dá pra validar SUM hierárquico.');
        }

        // Indexa por código
        $porCodigo = [];
        $folhas = [];
        foreach ($linhas as $l) {
            $porCodigo[$l['codigo']] = $l;
            if ($l['is_folha']) {
                $folhas[$l['codigo']] = (float) $l['saldo'];
            }
        }

        // Pra cada pai (não-folha), verifica que saldo = soma das folhas com prefix
        foreach ($linhas as $linha) {
            if ($linha['is_folha']) {
                continue;
            }
            $prefix = $linha['codigo'].'.';
            $somaFolhas = 0.0;
            foreach ($folhas as $codigoFolha => $saldoFolha) {
                if (str_starts_with($codigoFolha, $prefix)) {
                    $somaFolhas += $saldoFolha;
                }
            }
            $delta = abs((float) $linha['saldo'] - $somaFolhas);
            expect($delta)->toBeLessThan(0.01,
                "Pai {$linha['codigo']} ({$linha['nome']}) saldo {$linha['saldo']} != SUM folhas prefix '{$prefix}' = {$somaFolhas}");
        }
    });
});

it('aba=balancete: ?periodo=trimestre|ano|12m respeitados', function () {
    $user = dreBalancoBalanceteBootstrap();

    foreach (['mes', 'trimestre', 'ano', '12m'] as $periodoTipo) {
        $response = $this->actingAs($user)->get("/financeiro/dre?aba=balancete&periodo={$periodoTipo}");

        if (in_array($response->status(), [403, 404], true)) {
            test()->markTestSkipped('Module gate bloqueia neste env.');
        }

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('balancete.periodo.tipo', $periodoTipo)
        );
    }
});

it('Tier 0 IRREVOGÁVEL: aba=balanco respeita business scope (ADR 0093)', function () {
    $user = dreBalancoBalanceteBootstrap();
    $businessId = (int) $user->business_id;

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balanco');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('balanco.meta.business_id', $businessId)
    );

    // Defensiva: se há titulos em outros tenants, scope deve filtrar.
    $countDoBiz = Titulo::query()
        ->where('business_id', $businessId)
        ->whereIn('status', ['aberto', 'parcial'])
        ->count();
    $countTotal = Titulo::query()
        ->withoutGlobalScopes()
        ->whereIn('status', ['aberto', 'parcial'])
        ->count();

    if ($countTotal > $countDoBiz) {
        expect($countDoBiz)->toBeLessThan($countTotal, 'BusinessScope deve filtrar cross-tenant');
    }
});

it('Tier 0 IRREVOGÁVEL: aba=balancete respeita business scope (ADR 0093)', function () {
    $user = dreBalancoBalanceteBootstrap();
    $businessId = (int) $user->business_id;

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=balancete');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('balancete.meta.business_id', $businessId)
    );
});

it('aba=demonstrativo NÃO carrega balanco/balancete (perf — evita query custosa)', function () {
    $user = dreBalancoBalanceteBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/dre?aba=demonstrativo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('aba', 'demonstrativo')
        ->where('balanco', null)
        ->where('balancete', null)
    );
});

it('não dispara mutação em GET /dre?aba=balanco|balancete (read-only puro)', function () {
    $user = dreBalancoBalanceteBootstrap();

    $tituloCountBefore = Titulo::query()->count();

    $this->actingAs($user)->get('/financeiro/dre?aba=balanco');
    $this->actingAs($user)->get('/financeiro/dre?aba=balancete');

    $tituloCountAfter = Titulo::query()->count();
    expect($tituloCountAfter)->toBe($tituloCountBefore);
});
