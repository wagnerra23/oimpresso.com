<?php

declare(strict_types=1);
// Cobre US-FIN-075 (memory/requisitos/Financeiro/SPEC.md) — rateio do título em centros de custo.
// UC-RAT-01..07: backend ainda sem tela; os blocos entram no Unificado/Index.casos.md com o drawer do rateio.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\RateioService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regra mestre de VALOR: cada distribuição é conferida por DOIS caminhos — o serviço e a conta
 * à mão escrita no próprio teste (piso + maior resto), e a soma das linhas contra o total.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99 (seededSupportClientTenant). NUNCA biz=4.
 * `fin_titulos` não aceita delete: a limpeza é o rollback da transação.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_titulos depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_titulo_rateios')) {
        $this->markTestSkipped('fin_titulo_rateios ausente — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['financeiro.lancamentos.create']);
    $this->svc = app(RateioService::class);
});

function rateioCentro(int $biz, string $nome, bool $ativo = true): int
{
    return (int) DB::table('fin_centros_custo')->insertGetId(['business_id' => $biz, 'nome' => $nome,
        'ativo' => $ativo, 'created_at' => now(), 'updated_at' => now()]);
}

function rateioTitulo(int $biz, int $user, string $valor): Titulo
{
    $id = (int) DB::table('fin_titulos')->insertGetId(['business_id' => $biz, 'numero' => 'RAT'.substr(uniqid(), -9),
        'tipo' => 'pagar', 'status' => 'aberto', 'cliente_descricao' => 'fixture rateio', 'valor_total' => $valor,
        'valor_aberto' => $valor, 'moeda' => 'BRL', 'emissao' => '2031-04-05', 'vencimento' => '2031-04-20',
        'competencia_mes' => '2031-04', 'origem' => 'manual', 'created_by' => $user, 'created_at' => now(), 'updated_at' => now()]);

    return Titulo::withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: fixture lida fora de sessão de empresa
}

function rateioCentavos(array $linhas): int
{
    return array_sum(array_map(fn ($l) => (int) str_replace('.', '', $l['valor']), $linhas));
}

it('UC-RAT-01 distribui por percentual com piso + maior resto e fecha o total em centavos', function () {
    // À mão: 100001 c × 333333 = 33.333.633.333 → piso 33333, resto 633.333;
    //        100001 c × 333334 = 33.333.733.334 → piso 33333, resto 733.334.
    // Pisos somam 99999, sobram 2 c: 1º à linha 3 (maior resto), 2º à linha 1 (empata com a 2, vale a primeira).
    $r = $this->svc->distribuir('1000.0100', 'percentual', [
        ['centro_custo_id' => 1, 'percentual' => '33.3333'],
        ['centro_custo_id' => 2, 'percentual' => '33.3333'],
        ['centro_custo_id' => 3, 'percentual' => '33.3334'],
    ]);

    expect(array_column($r, 'valor'))->toBe(['333.34', '333.33', '333.34'])
        ->and(rateioCentavos($r))->toBe(100001);
});

it('UC-RAT-02 distribui por valor, deriva o percentual e a última linha fecha 100%', function () {
    $r = $this->svc->distribuir('300.0000', 'valor', [
        ['centro_custo_id' => 1, 'valor' => '100,00'],
        ['centro_custo_id' => 2, 'valor' => '100.00'],
        ['centro_custo_id' => 3, 'valor' => '100.00'],
    ]);

    expect(array_column($r, 'percentual'))->toBe(['33.3333', '33.3333', '33.3334'])
        ->and(rateioCentavos($r))->toBe(30000);
});

it('UC-RAT-03 recusa rateio que não fecha 100% ou o valor do título', function (string $modo, array $linhas) {
    $this->svc->distribuir('100.0000', $modo, $linhas);
})->with([
    'percentual 99,99' => ['percentual', [['centro_custo_id' => 1, 'percentual' => '60'], ['centro_custo_id' => 2, 'percentual' => '39.99']]],
    'percentual 100,01' => ['percentual', [['centro_custo_id' => 1, 'percentual' => '60'], ['centro_custo_id' => 2, 'percentual' => '40.01']]],
    'valor 1 centavo a menos' => ['valor', [['centro_custo_id' => 1, 'valor' => '50'], ['centro_custo_id' => 2, 'valor' => '49.99']]],
    'centro repetido' => ['percentual', [['centro_custo_id' => 1, 'percentual' => '50'], ['centro_custo_id' => 1, 'percentual' => '50']]],
])->throws(ValidationException::class);

it('UC-RAT-04 grava o rateio do título e substitui o anterior', function () {
    $a = rateioCentro($this->b98, 'ZZ Loja A');
    $b = rateioCentro($this->b98, 'ZZ Loja B');
    $t = rateioTitulo($this->b98, (int) $this->user->id, '1500.0000');

    $this->svc->salvar($t, $this->b98, 'percentual', [['centro_custo_id' => $a, 'percentual' => '100']]);
    $this->svc->salvar($t, $this->b98, 'valor', [['centro_custo_id' => $a, 'valor' => '1000'], ['centro_custo_id' => $b, 'valor' => '500']]);

    $gravado = DB::table('fin_titulo_rateios')->where('titulo_id', $t->id)->orderBy('centro_custo_id')->get();
    expect($gravado)->toHaveCount(2)
        ->and($gravado->pluck('business_id')->unique()->all())->toBe([$this->b98])
        ->and(rateioCentavos($gravado->map(fn ($l) => ['valor' => number_format((float) $l->valor, 2, '.', '')])->all()))->toBe(150000);
});

it('UC-RAT-05 recusa centro de custo de outra empresa (cross-tenant 98 × 99) e não grava nada', function () {
    $alheio = rateioCentro($this->b99, 'ZZ centro da 99');
    $t = rateioTitulo($this->b98, (int) $this->user->id, '100.0000');

    expect(fn () => $this->svc->salvar($t, $this->b98, 'percentual', [['centro_custo_id' => $alheio, 'percentual' => '100']]))
        ->toThrow(ValidationException::class);
    expect(DB::table('fin_titulo_rateios')->where('titulo_id', $t->id)->count())->toBe(0);
});

it('UC-RAT-06 recusa centro inativo', function () {
    $inativo = rateioCentro($this->b98, 'ZZ inativo', false);
    $t = rateioTitulo($this->b98, (int) $this->user->id, '100.0000');

    expect(fn () => $this->svc->salvar($t, $this->b98, 'percentual', [['centro_custo_id' => $inativo, 'percentual' => '100']]))
        ->toThrow(ValidationException::class);
});

it('UC-RAT-07 título sem rateio continua válido e lista vazia remove o rateio', function () {
    $a = rateioCentro($this->b98, 'ZZ Loja A');
    $t = rateioTitulo($this->b98, (int) $this->user->id, '100.0000');
    expect($t->rateios()->withoutGlobalScopes()->count())->toBe(0);

    $this->svc->salvar($t, $this->b98, 'percentual', [['centro_custo_id' => $a, 'percentual' => '100']]);
    $this->svc->salvar($t, $this->b98, 'percentual', []);

    expect(DB::table('fin_titulo_rateios')->where('titulo_id', $t->id)->count())->toBe(0);
});
