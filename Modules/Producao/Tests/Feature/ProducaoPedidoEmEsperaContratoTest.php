<?php

declare(strict_types=1);
// Decisão [W] 2026-10-16 (FSM do pedido: ERP × app): pedido em espera pausa o apontamento.

use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Transaction;
use App\User;
use Database\Seeders\FsmProcessoVendaComProducaoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\PedidoEmEsperaService;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Venda vai para `on_hold` (por QUALQUER ação) → etapas do PCP dela em andamento são pausadas
 * (`origem = pedido`, evento com motivo "pedido em espera"); enquanto em espera o apontamento
 * recusa iniciar (`pedido_em_espera`); de volta a `in_production`, retoma.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra outro business. NUNCA biz=4. Fila `sync`.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
const PEE_DIA = '2026-10-15';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('pcp_programacoes') || ! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('Schema MySQL do PCP/FSM ausente nesta lane.');
    }
    Carbon::setTestNow(PEE_DIA.' 10:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    (new FsmProcessoVendaComProducaoSeeder)->runForBusiness($this->b98);
    $this->svc = app(ApontamentoOperadorService::class);
    $this->posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $this->b98, 'codigo' => 'PEE-'.uniqid(),
        'nome' => 'Impressão', 'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
    $this->diego = (int) DB::table('users')->insertGetId(['first_name' => 'PEE diego', 'username' => 'pee-diego-'.uniqid(),
        'password' => bcrypt('x'), 'business_id' => $this->b98, 'created_at' => now(), 'updated_at' => now()]);
    $this->gerente = (int) DB::table('users')->insertGetId(['first_name' => 'PEE gerente', 'username' => 'pee-ger-'.uniqid(),
        'password' => bcrypt('x'), 'business_id' => $this->b98, 'created_at' => now(), 'updated_at' => now()]);
    User::findOrFail($this->gerente)->assignRole("producao.pausar#{$this->b98}");
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(fn () => Carbon::setTestNow());

function peeStage(int $biz, string $chave): int
{
    return (int) DB::table('sale_process_stages as s')->join('sale_processes as p', 'p.id', '=', 's.process_id')
        ->where('p.business_id', $biz)->where('p.key', 'venda_com_producao')->where('s.key', $chave)->value('s.id');
}

function peeVenda(int $biz, int $criador, string $etapa = 'in_production'): int
{
    return (int) DB::table('transactions')->insertGetId(['business_id' => $biz, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 30, 'created_by' => $criador,
        'essentials_duration' => 0, 'current_stage_id' => $etapa === '' ? null : peeStage($biz, $etapa),
        'created_at' => now(), 'updated_at' => now()]);
}

function peeEtapa($t, int $venda, string $osTipo = Programacao::OS_VENDA): int
{
    return (int) Programacao::query()->create(['business_id' => $t->b98, 'os_tipo' => $osTipo, 'os_id' => $venda,
        'posto_id' => $t->posto, 'inicio_previsto' => PEE_DIA.' 08:00:00', 'fim_previsto' => PEE_DIA.' 09:00:00',
        'status' => 'pendente', 'user_id' => $t->diego, 'created_by' => $t->diego])->id;
}

function peeIniciar($t, int $etapa, string $chave = 'pee-i'): int
{
    return $t->svc->iniciar($t->b98, $t->diego, $etapa, $chave.'-'.$etapa, Carbon::parse(PEE_DIA.' 09:00:00'))->id;
}

/** Leva a venda à etapa pelo mesmo caminho do executor (flag + save), sem depender da chave da ação. */
function peeMover(int $venda, int $biz, string $etapa): void
{
    $tx = Transaction::query()->where('business_id', $biz)->findOrFail($venda);
    FsmAuthorizationFlag::mark($tx::class, $tx->getKey());
    $tx->current_stage_id = peeStage($biz, $etapa);
    $tx->save();
}

function peePausas(int $biz, int $venda): array
{
    return DB::table('pcp_apontamentos')->where('business_id', $biz)->where('os_id', $venda)
        ->where('tipo', 'pausa')->pluck('origem')->all();
}

it('pausar_producao (on_hold) pausa a etapa em andamento com motivo "pedido em espera"', function () {
    $venda = peeVenda($this->b98, $this->diego);
    peeIniciar($this, peeEtapa($this, $venda));

    $tx = Transaction::query()->where('business_id', $this->b98)->findOrFail($venda);
    app(ExecuteStageActionService::class)->execute($tx, 'pausar_producao', User::findOrFail($this->gerente));

    $ev = DB::table('pcp_eventos')->where('business_id', $this->b98)->where('os_id', $venda)
        ->where('tipo', PedidoEmEsperaService::EV_PAUSADO)->first();
    expect(peePausas($this->b98, $venda))->toBe(['pedido'])
        ->and(json_decode((string) $ev->dados, true)['motivo'])->toBe('pedido em espera')
        ->and((int) $ev->user_id)->toBe($this->diego);
});

it('qualquer mudança para on_hold pausa (sem depender da chave da ação), e pausar de novo é idempotente', function () {
    $venda = peeVenda($this->b98, $this->diego);
    peeIniciar($this, peeEtapa($this, $venda));
    peeMover($venda, $this->b98, 'on_hold');

    expect(app(PedidoEmEsperaService::class)->pausarEtapas($this->b98, $venda))->toBe(0)
        ->and(peePausas($this->b98, $venda))->toBe(['pedido']);
});

it('com a venda em espera o apontamento recusa iniciar (pedido_em_espera) e a API do app responde 409', function () {
    $venda = peeVenda($this->b98, $this->diego, 'on_hold');
    $etapa = peeEtapa($this, $venda);

    try {
        peeIniciar($this, $etapa);
        $codigo = 'nada';
    } catch (ApontamentoRecusado $e) {
        $codigo = $e->codigo;
    }
    expect($codigo)->toBe(ApontamentoRecusado::PEDIDO_EM_ESPERA)
        ->and(DB::table('pcp_apontamentos')->where('business_id', $this->b98)->where('programacao_id', $etapa)->count())->toBe(0);

    $mapa = (new ReflectionClass(Modules\Producao\Http\Controllers\Api\AppApontamentoController::class))->getConstant('RECUSAS');
    expect($mapa[ApontamentoRecusado::PEDIDO_EM_ESPERA])->toBe([409, 'pedido_em_espera', null]);
});

it('de volta a in_production a etapa pode ser retomada e o responsável recebe aviso', function () {
    $venda = peeVenda($this->b98, $this->diego);
    $etapa = peeEtapa($this, $venda);
    peeIniciar($this, $etapa);
    peeMover($venda, $this->b98, 'on_hold');
    peeMover($venda, $this->b98, 'in_production');

    $retomada = peeIniciar($this, $etapa, 'pee-r');
    expect($retomada)->toBeGreaterThan(0)
        ->and(DB::table('pcp_eventos')->where('business_id', $this->b98)->where('os_id', $venda)
            ->where('tipo', PedidoEmEsperaService::EV_RETOMADO)->where('user_id', $this->diego)->count())->toBe(1);
});

it('etapa comvis_os não é pausada pela venda', function () {
    $venda = peeVenda($this->b98, $this->diego);
    peeIniciar($this, peeEtapa($this, $venda));
    $comvis = (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $this->b98, 'os_tipo' => 'comvis_os', 'os_id' => $venda,
        'posto_id' => $this->posto, 'inicio_previsto' => PEE_DIA.' 11:00:00', 'fim_previsto' => PEE_DIA.' 12:00:00',
        'status' => 'pendente', 'user_id' => $this->gerente, 'created_by' => $this->gerente, 'created_at' => now(), 'updated_at' => now()]);
    $this->svc->iniciar($this->b98, $this->gerente, $comvis, 'pee-cv', Carbon::parse(PEE_DIA.' 09:00:00'));
    peeMover($venda, $this->b98, 'on_hold');

    $comvisPausada = DB::table('pcp_apontamentos')->where('business_id', $this->b98)->where('programacao_id', $comvis)->where('tipo', 'pausa')->count();
    expect(peePausas($this->b98, $venda))->toBe(['pedido'])->and($comvisPausada)->toBe(0);
});

it('Tier 0: venda de outra empresa em espera não pausa nada do 98, e o serviço não age com business errado', function () {
    $outro = (int) (DB::table('business')->where('id', '!=', $this->b98)->orderBy('id')->value('id') ?? 0);
    if ($outro === 0) {
        $this->markTestSkipped('Sem segundo business nesta base.');
    }
    $venda = peeVenda($this->b98, $this->diego);
    peeIniciar($this, peeEtapa($this, $venda));
    DB::table('transactions')->where('id', $venda)->update(['current_stage_id' => peeStage($this->b98, 'on_hold')]);

    expect(app(PedidoEmEsperaService::class)->pausarEtapas($outro, $venda))->toBe(0)
        ->and(peePausas($this->b98, $venda))->toBe([]);
});
