<?php

declare(strict_types=1);
// Cobre UC-PCCH-03..06 (Modules/Producao/Resources/js/Pages/Producao/Postos/Index.casos.md · US-FIN-070 · UC-FLX-20).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Services\CusteioRkwService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Custeio RKW e ponto de equilíbrio do mês. Regra mestre de VALOR: cada número é conferido
 * por DOIS caminhos — o serviço e uma conta à mão no próprio teste, feita sobre a fixture.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99 (seededSupportClientTenant). NUNCA biz=4.
 * Mês isolado (2031-03). `pcp_apontamentos` e `fin_titulos` não aceitam delete: a limpeza é o
 * rollback da transação. ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
const RKW_MES = '2031-03';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fundo do PCP depende de schema MySQL.');
    }
    if (! Schema::hasTable('pcp_apontamentos') || ! Schema::hasColumn('fin_planos_conta', 'classe_custo')) {
        $this->markTestSkipped('pcp_* ou fin_planos_conta.classe_custo ausentes — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['financeiro.relatorios.view', 'producao.desempenho.ver']);
    $this->svc = app(CusteioRkwService::class);
});

function rkwPlano(int $biz, string $tipo, ?string $classe): int
{
    return (int) DB::table('fin_planos_conta')->insertGetId(['business_id' => $biz, 'codigo' => 'R'.uniqid(),
        'nome' => 'ZZ RKW '.($classe ?? 'sem classe'), 'tipo' => $tipo, 'nivel' => 4,
        'natureza' => $tipo === 'receita' ? 'credito' : 'debito', 'classe_custo' => $classe,
        'aceita_lancamento' => true, 'protegido' => false, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
}

function rkwTitulo(int $biz, int $user, int $plano, string $tipo, string $valor, string $status = 'aberto'): void
{
    DB::table('fin_titulos')->insert(['business_id' => $biz, 'numero' => 'RKW'.substr(uniqid(), -9), 'tipo' => $tipo,
        'status' => $status, 'cliente_descricao' => 'fixture RKW', 'valor_total' => $valor, 'valor_aberto' => $valor,
        'moeda' => 'BRL', 'emissao' => RKW_MES.'-05', 'vencimento' => RKW_MES.'-20', 'competencia_mes' => RKW_MES,
        'origem' => 'manual', 'plano_conta_id' => $plano, 'created_by' => $user, 'created_at' => now(), 'updated_at' => now()]);
}

function rkwPosto(int $biz, string $custo): int
{
    return (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'RKW-'.uniqid(), 'nome' => 'RKW '.$custo,
        'tipo_custo' => 'maquina', 'custo_hora' => $custo, 'created_at' => now(), 'updated_at' => now()]);
}

/** Trecho fechado: linha `inicio` + `fim` que a fecha. */
function rkwHoras(int $biz, int $posto, int $user, int $horas, string $custo, string $dia = '10'): int
{
    $base = ['business_id' => $biz, 'posto_id' => $posto, 'tipo_custo' => 'maquina', 'custo_hora' => $custo,
        'maquina_id' => $posto, 'iniciado_por' => $user, 'origem' => 'manual'];
    $inicio = (int) DB::table('pcp_apontamentos')->insertGetId($base + ['chave_idempotencia' => 'rkw-i-'.uniqid(), 'tipo' => 'inicio',
        'momento' => RKW_MES."-{$dia} 08:00:00"]);
    DB::table('pcp_apontamentos')->insert($base + ['chave_idempotencia' => 'rkw-f-'.uniqid(), 'tipo' => 'fim', 'inicio_id' => $inicio,
        'momento' => date('Y-m-d H:i:s', strtotime(RKW_MES."-{$dia} 08:00:00") + $horas * 3600)]);

    return $inicio;
}

/** Fixture do exemplo do PR: F=4000, V=2000, R=10000; posto A 30h a 30/h, posto B 10h a 50/h. */
function rkwCenario(object $t): array
{
    $u = (int) $t->user->id;
    $fixa = rkwPlano($t->b98, 'despesa', 'fixa');
    rkwTitulo($t->b98, $u, $fixa, 'pagar', '3000.0000');
    rkwTitulo($t->b98, $u, $fixa, 'pagar', '1000.0000');
    rkwTitulo($t->b98, $u, $fixa, 'pagar', '999.0000', 'cancelado');           // cancelado: fora
    rkwTitulo($t->b98, $u, rkwPlano($t->b98, 'despesa', 'variavel'), 'pagar', '2000.0000');
    rkwTitulo($t->b98, $u, rkwPlano($t->b98, 'despesa', null), 'pagar', '500.0000'); // sem classe: fora
    rkwTitulo($t->b98, $u, rkwPlano($t->b98, 'receita', null), 'receber', '10000.0000');
    $a = rkwPosto($t->b98, '30.00');
    $b = rkwPosto($t->b98, '50.00');
    $apont = rkwHoras($t->b98, $a, $u, 20, '30.00');
    rkwHoras($t->b98, $a, $u, 10, '30.00', '11');
    rkwHoras($t->b98, $b, $u, 10, '50.00');

    // Empresa 99 com despesa fixa e horas próprias: não pode entrar na conta da 98.
    $dono99 = (int) DB::table('business')->where('id', $t->b99)->value('owner_id');
    rkwTitulo($t->b99, $dono99, rkwPlano($t->b99, 'despesa', 'fixa'), 'pagar', '7777.0000');
    $p99 = rkwPosto($t->b99, '10.00');
    rkwHoras($t->b99, $p99, $dono99, 5, '10.00');

    return ['a' => $a, 'b' => $b, 'p99' => $p99, 'apont' => $apont];
}

it('UC-PCCH-03 rateia as despesas fixas pelas horas e sugere o custo-hora RKW (dois caminhos)', function () {
    $c = rkwCenario($this);
    $r = $this->svc->calcular($this->b98, RKW_MES);

    // Caminho 2: conta à mão sobre a fixture. F = 3000 + 1000; H = 30 + 10 horas.
    $f = 3000 + 1000;
    $porHora = $f / (30 + 10);                        // 100
    expect($r['despesas_fixas'])->toBe(number_format($f, 2, '.', ''))
        ->and($r['horas'])->toBe('40.00')
        ->and($r['parcela_fixa_por_hora'])->toBe(number_format($porHora, 2, '.', ''));

    $porPosto = collect($r['postos'])->keyBy('posto_id')->all();
    expect($porPosto[$c['a']]['rateio_fixo'])->toBe(number_format($f * 30 / 40, 2, '.', ''))
        ->and($porPosto[$c['b']]['rateio_fixo'])->toBe(number_format($f * 10 / 40, 2, '.', ''))
        ->and($porPosto[$c['a']]['custo_hora_rkw'])->toBe(number_format(30 + $porHora, 2, '.', ''))
        ->and($porPosto[$c['b']]['custo_hora_rkw'])->toBe(number_format(50 + $porHora, 2, '.', ''))
        ->and($porPosto)->not->toHaveKey($c['p99']);

    // Nada foi gravado: o cálculo só lê.
    expect(DB::table('pcp_postos')->where('id', $c['a'])->value('custo_hora'))->toBe('30.00');
});

it('UC-PCCH-04 calcula a margem de contribuição e o ponto de equilíbrio (dois caminhos)', function () {
    rkwCenario($this);
    $r = $this->svc->calcular($this->b98, RKW_MES);

    // Caminho 2: PE = F ÷ ((R − V) ÷ R), com os totais lidos direto do banco.
    $soma = fn (string $tipo, string $col, string $val) => (float) DB::table('fin_titulos as t')
        ->join('fin_planos_conta as p', 'p.id', '=', 't.plano_conta_id')
        ->where('t.business_id', $this->b98)->where('t.competencia_mes', RKW_MES)->where('t.tipo', $tipo)
        ->where('t.status', '!=', 'cancelado')->where('p.'.$col, $val)->sum('t.valor_total');
    $f = $soma('pagar', 'classe_custo', 'fixa');
    $v = $soma('pagar', 'classe_custo', 'variavel');
    $rec = $soma('receber', 'tipo', 'receita');

    expect($r['margem_contribuicao_pct'])->toBe(number_format(($rec - $v) / $rec * 100, 2, '.', ''))
        ->and($r['ponto_equilibrio'])->toBe(number_format($f / (($rec - $v) / $rec), 2, '.', ''))
        ->and($r['ponto_equilibrio'])->toBe('5000.00');
});

it('UC-PCCH-04 sem margem positiva devolve aviso no lugar do ponto de equilíbrio', function () {
    $u = (int) $this->user->id;
    rkwTitulo($this->b98, $u, rkwPlano($this->b98, 'despesa', 'fixa'), 'pagar', '1000.0000');
    rkwTitulo($this->b98, $u, rkwPlano($this->b98, 'despesa', 'variavel'), 'pagar', '900.0000');
    rkwTitulo($this->b98, $u, rkwPlano($this->b98, 'receita', null), 'receber', '800.0000');

    $r = $this->svc->calcular($this->b98, RKW_MES);
    expect($r['ponto_equilibrio'])->toBeNull()
        ->and($r['margem_contribuicao_pct'])->toBeNull()
        ->and(implode(' ', $r['avisos']))->toContain('ponto de equilíbrio não se aplica');
});

it('UC-PCCH-03 o rateio fecha ao centavo quando a divisão não é exata (maior resto)', function () {
    $u = (int) $this->user->id;
    rkwTitulo($this->b98, $u, rkwPlano($this->b98, 'despesa', 'fixa'), 'pagar', '1000.0000');
    foreach (['20.00', '20.00', '20.00'] as $custo) {
        rkwHoras($this->b98, rkwPosto($this->b98, $custo), $u, 1, $custo);
    }

    $r = $this->svc->calcular($this->b98, RKW_MES);
    $rateios = array_column($r['postos'], 'rateio_fixo');
    $centavos = array_sum(array_map(fn ($x) => (int) round((float) $x * 100), $rateios));
    expect($centavos)->toBe(100000)
        ->and($rateios)->toContain('333.34')
        ->and($r['parcela_fixa_por_hora'])->toBe('333.33');
});

it('UC-PCCH-05 aplicar grava o custo-hora sugerido, devolve antes→depois e não reescreve o apontamento', function () {
    $c = rkwCenario($this);
    session(['user.business_id' => $this->b98, 'business.id' => $this->b98]);

    $resp = $this->actingAs($this->user)->postJson('/financeiro/custeio-rkw/aplicar/'.$c['a'].'?mes='.RKW_MES);

    $resp->assertOk()->assertJson(['posto_id' => $c['a'], 'antes' => '30.00', 'depois' => '130.00']);
    expect(DB::table('pcp_postos')->where('id', $c['a'])->value('custo_hora'))->toBe('130.00')
        ->and(DB::table('pcp_postos')->where('id', $c['b'])->value('custo_hora'))->toBe('50.00')
        ->and(DB::table('pcp_apontamentos')->where('id', $c['apont'])->value('custo_hora'))->toBe('30.00');
});

it('UC-PCCH-06 isola por empresa e exige as duas permissões', function () {
    $c = rkwCenario($this);
    session(['user.business_id' => $this->b98, 'business.id' => $this->b98]);

    $this->actingAs($this->user)->getJson('/financeiro/custeio-rkw?mes='.RKW_MES)
        ->assertOk()->assertJsonPath('despesas_fixas', '4000.00');
    // Posto da empresa 99 → 404, e o custo dele não muda.
    $this->actingAs($this->user)->postJson('/financeiro/custeio-rkw/aplicar/'.$c['p99'].'?mes='.RKW_MES)->assertNotFound();
    expect(DB::table('pcp_postos')->where('id', $c['p99'])->value('custo_hora'))->toBe('10.00');

    $soFinanceiro = $this->usuarioComPermissoes(['financeiro.relatorios.view']);
    $this->actingAs($soFinanceiro)->getJson('/financeiro/custeio-rkw?mes='.RKW_MES)->assertForbidden();
    $soProducao = $this->usuarioComPermissoes(['producao.desempenho.ver']);
    $this->actingAs($soProducao)->postJson('/financeiro/custeio-rkw/aplicar/'.$c['a'].'?mes='.RKW_MES)->assertForbidden();
    expect(DB::table('pcp_postos')->where('id', $c['a'])->value('custo_hora'))->toBe('30.00');
});

it('UC-PCCH-03 o comando só lê, mesmo sem --dry-run', function () {
    $c = rkwCenario($this);

    $code = Artisan::call('financeiro:custeio-rkw', ['--business' => $this->b98, '--mes' => RKW_MES]);

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('130.00')
        ->and(DB::table('pcp_postos')->where('id', $c['a'])->value('custo_hora'))->toBe('30.00');
});
