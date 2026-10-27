<?php

declare(strict_types=1);
// Cobre UC-CCUSTO-01..04 (resources/js/Pages/Financeiro/CentrosCusto/Index.casos.md · US-FIN-075).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Tenant 98 (seededTenant, ADR 0358) contra o 99. NUNCA biz=4. A limpeza é o rollback.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_titulos depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_centros_custo')) {
        $this->markTestSkipped('fin_centros_custo ausente — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['financeiro.dashboard.view', 'financeiro.lancamentos.create']);
});

function ccCentro(int $biz, string $nome, ?string $codigo = null, bool $ativo = true): int
{
    return (int) DB::table('fin_centros_custo')->insertGetId(['business_id' => $biz, 'codigo' => $codigo,
        'nome' => $nome, 'ativo' => $ativo, 'created_at' => now(), 'updated_at' => now()]);
}

it('UC-CCUSTO-01 lista os centros com títulos e valor rateado na competência do mês', function () {
    $a = ccCentro($this->b98, 'ZZ cadastro A', 'ZZA');
    $mes = now()->format('Y-m');
    $titulo = fn (string $valor, string $status) => (int) DB::table('fin_titulos')->insertGetId(['business_id' => $this->b98,
        'numero' => 'CCC'.substr(uniqid(), -9), 'tipo' => 'pagar', 'status' => $status, 'cliente_descricao' => 'fixture cadastro',
        'valor_total' => $valor, 'valor_aberto' => $valor, 'moeda' => 'BRL', 'emissao' => $mes.'-01', 'vencimento' => $mes.'-28',
        'competencia_mes' => $mes, 'origem' => 'manual', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
    foreach ([['300.0000', 'aberto'], ['200.5000', 'quitado'], ['999.0000', 'cancelado']] as [$v, $st]) {
        DB::table('fin_titulo_rateios')->insert(['business_id' => $this->b98, 'titulo_id' => $titulo($v, $st),
            'centro_custo_id' => $a, 'percentual' => '100.0000', 'valor' => $v, 'created_at' => now(), 'updated_at' => now()]);
    }
    ccCentro($this->b99, 'ZZ centro da 99');

    $this->actingAs($this->user)->get('/financeiro/centros-custo')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Financeiro/CentrosCusto/Index')
            ->where('centros', function ($centros) use ($a) {
                $linha = collect($centros)->firstWhere('id', $a);
                $nomes = collect($centros)->pluck('nome');

                // 300,00 + 200,50 = 500,50; o cancelado (999,00) fica de fora.
                return $linha['titulos_mes'] === 2
                    && $linha['rateado_mes'] === 500.5
                    && $linha['ativo'] === true
                    && ! $nomes->contains('ZZ centro da 99');
            }));
});

it('UC-CCUSTO-02 cria centro ativo na empresa e recusa nome repetido', function () {
    $this->actingAs($this->user)->post('/financeiro/centros-custo', ['codigo' => 'zzn', 'nome' => 'ZZ Novo centro'])
        ->assertSessionHasNoErrors()->assertRedirect();

    $criado = DB::table('fin_centros_custo')->where('business_id', $this->b98)->where('nome', 'ZZ Novo centro')->first();
    expect($criado)->not->toBeNull()
        ->and((bool) $criado->ativo)->toBeTrue()
        ->and($criado->codigo)->toBe('zzn');

    $this->actingAs($this->user)->post('/financeiro/centros-custo', ['nome' => 'ZZ Novo centro'])
        ->assertSessionHasErrors('nome');
    expect(DB::table('fin_centros_custo')->where('business_id', $this->b98)->where('nome', 'ZZ Novo centro')->count())->toBe(1);
});

it('UC-CCUSTO-03 edita o nome e inativa o centro', function () {
    $id = ccCentro($this->b98, 'ZZ editar');

    $this->actingAs($this->user)->put("/financeiro/centros-custo/{$id}", ['codigo' => '', 'nome' => 'ZZ editado'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post("/financeiro/centros-custo/{$id}/toggle")->assertRedirect();

    $linha = DB::table('fin_centros_custo')->where('id', $id)->first();
    expect($linha->nome)->toBe('ZZ editado')
        ->and($linha->codigo)->toBeNull()
        ->and((bool) $linha->ativo)->toBeFalse();
});

it('UC-CCUSTO-04 centro da empresa 99 não é editado nem inativado pela 98', function () {
    $alheio = ccCentro($this->b99, 'ZZ alheio');

    $this->actingAs($this->user)->put("/financeiro/centros-custo/{$alheio}", ['nome' => 'invadido'])->assertNotFound();
    $this->actingAs($this->user)->post("/financeiro/centros-custo/{$alheio}/toggle")->assertNotFound();

    $linha = DB::table('fin_centros_custo')->where('id', $alheio)->first();
    expect($linha->nome)->toBe('ZZ alheio')->and((bool) $linha->ativo)->toBeTrue();
});
