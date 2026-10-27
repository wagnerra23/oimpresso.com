<?php

declare(strict_types=1);
// Cobre UC-FUNI-05..07 (resources/js/Pages/Financeiro/Unificado/Index.casos.md · US-FIN-075).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Rateio gravado pelo drawer do título (PUT /financeiro/unificado/{id}), junto com a edição.
 * Valor, dois caminhos: a conta à mão abaixo e a soma das linhas contra o total do título.
 * Tenant 98 contra o 99. NUNCA biz=4. `fin_titulos` não aceita delete: limpeza é o rollback.
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
    $this->user = $this->usuarioComPermissoes(['financeiro.dashboard.view']);

    $centro = fn (int $biz, string $nome, bool $ativo = true) => (int) DB::table('fin_centros_custo')->insertGetId([
        'business_id' => $biz, 'nome' => $nome, 'ativo' => $ativo, 'created_at' => now(), 'updated_at' => now()]);
    $this->A = $centro($this->b98, 'ZZ rateio A');
    $this->B = $centro($this->b98, 'ZZ rateio B');
    $this->C99 = $centro($this->b99, 'ZZ rateio 99');
    $this->titulo = (int) DB::table('fin_titulos')->insertGetId(['business_id' => $this->b98, 'numero' => 'URT'.substr(uniqid(), -9),
        'tipo' => 'pagar', 'status' => 'aberto', 'cliente_descricao' => 'fixture rateio drawer', 'valor_total' => '1000.0000',
        'valor_aberto' => '1000.0000', 'moeda' => 'BRL', 'emissao' => '2031-07-01', 'vencimento' => '2031-07-20',
        'competencia_mes' => '2031-07', 'origem' => 'manual', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
});

/** O que o TituloEditSheet manda no PUT, mais o que o teste quiser trocar. */
function urtPut(object $t, array $extra)
{
    return $t->actingAs($t->user)->put("/financeiro/unificado/{$t->titulo}", $extra + [
        'cliente_descricao' => 'fixture rateio drawer', 'observacoes' => null, 'categoria_id' => null,
        'plano_conta_id' => null, 'vencimento' => '2031-07-20', 'conta_bancaria_id' => null,
    ]);
}

function urtLinhas(object $t): array
{
    return DB::table('fin_titulo_rateios')->where('titulo_id', $t->titulo)->orderBy('centro_custo_id')
        ->pluck('valor', 'centro_custo_id')->map(fn ($v) => number_format((float) $v, 2, '.', ''))->all();
}

it('UC-FUNI-05 grava o rateio do drawer e, mudando o valor sem reenviar, refaz as fatias pelos percentuais', function () {
    urtPut($this, ['rateio_modo' => 'percentual', 'rateio' => [
        ['centro_custo_id' => $this->A, 'percentual' => '60'],
        ['centro_custo_id' => $this->B, 'percentual' => '40'],
    ]])->assertSessionHasNoErrors();
    expect(urtLinhas($this))->toBe([$this->A => '600.00', $this->B => '400.00']);

    // 1.500,01 × 60% = 900,006 → 900,00 e 1.500,01 × 40% = 600,004 → 600,00; sobra 1 c para a linha de
    // maior resto (a A, resto 6.000 contra 4.000). Soma 1.500,01.
    urtPut($this, ['valor_total' => '1500.01'])->assertSessionHasNoErrors();
    $linhas = urtLinhas($this);
    expect($linhas)->toBe([$this->A => '900.01', $this->B => '600.00'])
        ->and(array_sum(array_map(fn ($v) => (int) round(((float) $v) * 100), $linhas)))->toBe(150001);
});

it('UC-FUNI-06 rateio que não fecha 100% desfaz a edição inteira, valor incluído', function () {
    urtPut($this, ['valor_total' => '2000', 'rateio_modo' => 'percentual', 'rateio' => [
        ['centro_custo_id' => $this->A, 'percentual' => '60'],
        ['centro_custo_id' => $this->B, 'percentual' => '30'],
    ]])->assertSessionHasErrors('rateio');

    expect((float) DB::table('fin_titulos')->where('id', $this->titulo)->value('valor_total'))->toBe(1000.0)
        ->and(urtLinhas($this))->toBe([]);
});

it('UC-FUNI-07 recusa centro da empresa 99 e aceita centro inativado que já estava no rateio', function () {
    urtPut($this, ['rateio_modo' => 'valor', 'rateio' => [['centro_custo_id' => $this->C99, 'valor' => '1000,00']]])
        ->assertSessionHasErrors('rateio');
    expect(urtLinhas($this))->toBe([]);

    urtPut($this, ['rateio_modo' => 'percentual', 'rateio' => [['centro_custo_id' => $this->A, 'percentual' => '100']]])
        ->assertSessionHasNoErrors();
    DB::table('fin_centros_custo')->where('id', $this->A)->update(['ativo' => false]);

    urtPut($this, ['observacoes' => 'reeditado', 'rateio_modo' => 'percentual', 'rateio' => [['centro_custo_id' => $this->A, 'percentual' => '100']]])
        ->assertSessionHasNoErrors();
    expect(urtLinhas($this))->toBe([$this->A => '1000.00']);

    urtPut($this, ['rateio_modo' => 'percentual', 'rateio' => []])->assertSessionHasNoErrors();
    expect(urtLinhas($this))->toBe([]);
});
