<?php

declare(strict_types=1);
// @covers-us US-FIN-080
// @covers-us US-FIN-083
// Cobre UC-FCC-27..28 (resources/js/Pages/Financeiro/Conciliacao/Index.casos.md).

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Financeiro\Http\Controllers\ConciliacaoConfigController;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Painel "Regras e maquininha". Chama os métodos do controller com a sessão do tenant (o gate de
 * permissão da rota é coberto pelo PermissaoRotasContratoTest). Tenant 98 contra 99. NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_* depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_conciliacao_regras') || ! Schema::hasTable('fin_cartao_adquirentes')) {
        $this->markTestSkipped('Migrations 2026_10_27_120000/130000 não aplicadas — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    session(['user.business_id' => $this->b98, 'business.id' => $this->b98]);
});

function cfgPost(array $dados): Request
{
    return Request::create('/x', 'POST', $dados);
}

it('UC-FCC-27 · cria regra no business da sessão, recusa regra sem critério e alvo de outro business [T0]', function () {
    $ctl = new ConciliacaoConfigController();

    $ctl->storeRegra(cfgPost(['nome' => 'PEST mensalidade', 'descricao_contem' => 'MENSALIDADE', 'sentido' => 'C', 'acao' => 'conciliar']));
    $regra = DB::table('fin_conciliacao_regras')->where('nome', 'PEST mensalidade')->first();
    expect((int) $regra->business_id)->toBe($this->b98);
    expect($regra->acao)->toBe('conciliar');
    expect((int) $regra->ativo)->toBe(1);

    // Sem trecho de descrição nem plano/categoria: casaria qualquer coisa.
    expect(fn () => $ctl->storeRegra(cfgPost(['nome' => 'PEST vazia', 'acao' => 'sugerir'])))
        ->toThrow(ValidationException::class);

    // Plano de contas que é do 99.
    $plano99 = DB::table('fin_planos_conta')->where('business_id', $this->b99)->value('id');
    if ($plano99) {
        expect(fn () => $ctl->storeRegra(cfgPost(['nome' => 'PEST alheia', 'plano_conta_id' => $plano99, 'acao' => 'sugerir'])))
            ->toThrow(ValidationException::class);
    }

    // Desligar e apagar só a do próprio business; a do 99 não é encontrada.
    $ctl->updateRegra(cfgPost(['ativo' => false]), (int) $regra->id);
    expect((int) DB::table('fin_conciliacao_regras')->where('id', $regra->id)->value('ativo'))->toBe(0);

    $do99 = DB::table('fin_conciliacao_regras')->insertGetId([
        'business_id' => $this->b99, 'nome' => 'PEST do 99', 'descricao_contem' => 'X', 'acao' => 'sugerir',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(fn () => $ctl->updateRegra(cfgPost(['ativo' => false]), $do99))->toThrow(ModelNotFoundException::class);
    expect(fn () => $ctl->destroyRegra($do99))->toThrow(ModelNotFoundException::class);
    expect(DB::table('fin_conciliacao_regras')->where('id', $do99)->whereNull('deleted_at')->exists())->toBeTrue();

    $ctl->destroyRegra((int) $regra->id);
    expect(DB::table('fin_conciliacao_regras')->where('id', $regra->id)->whereNotNull('deleted_at')->exists())->toBeTrue();
});

it('UC-FCC-28 · salva a maquininha padrão uma vez só e o painel não mostra nada do outro business [T0]', function () {
    $ctl = new ConciliacaoConfigController();
    $dados = ['nome' => 'PEST Stone', 'descricao_extrato' => 'STONE', 'taxa_debito' => '1.99', 'prazo_debito_dias' => 1,
        'taxa_credito' => '3.19', 'prazo_credito_dias' => 30];

    $ctl->salvarMaquininha(cfgPost($dados));
    $ctl->salvarMaquininha(cfgPost(['taxa_credito' => '2.99'] + $dados));

    $adqs = DB::table('fin_cartao_adquirentes')->where('business_id', $this->b98)->where('padrao', 1)->whereNull('deleted_at')->get();
    expect($adqs)->toHaveCount(1);
    expect((float) $adqs[0]->taxa_credito)->toBe(2.99);

    DB::table('fin_conciliacao_regras')->insert([
        'business_id' => $this->b99, 'nome' => 'PEST só do 99', 'descricao_contem' => 'X', 'acao' => 'sugerir',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $painel = ConciliacaoConfigController::painel($this->b98);
    expect(collect($painel['regras'])->pluck('nome')->all())->not->toContain('PEST só do 99');
    expect($painel['maquininha']['nome'])->toBe('PEST Stone');
    expect($painel['regras_ligadas'])->toBeFalse();
});
