<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Services\FeriasService;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato de `FeriasService` — US-PONTO-018 (férias da CLT, sem valor em dinheiro).
 *
 * A regra vem da CLT, não do código: aquisitivo de 12 meses desde a admissão (art. 130),
 * concessivo dos 12 meses seguintes (art. 134), vencido com saldo = dobra (art. 137) e a tabela
 * de dias por faltas injustificadas do art. 130 (≤5→30 · 6-14→24 · 15-23→18 · 24-32→12 · >32→0).
 *
 * Tier 0 (ADR 0093): tenant fictício 98 vs adversário 99 (ADR 0358). NUNCA biz=4, nunca biz=1.
 * Sem `RefreshDatabase` — a lane `ponto-pest` proíbe. Limpeza por id coletado e por marcador.
 *
 * @see \Modules\Ponto\Services\FeriasService
 */

const FERIAS_MARCADOR = 'US-PONTO-018-FERIAS';

$GLOBALS['ferias_ids'] = ['tipos' => [], 'licencas' => [], 'apuracoes' => []];

function feriasColaborador(int $bizId, int $userId, string $admissao): Colaborador
{
    return Colaborador::forceCreate([
        'business_id'    => $bizId,
        'user_id'        => $userId,
        'matricula'      => FERIAS_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao'       => $admissao,
    ]);
}

function feriasTipo(int $bizId, bool $ferias): int
{
    $id = (int) DB::table('essentials_leave_types')->insertGetId([
        'leave_type'  => FERIAS_MARCADOR,
        'business_id' => $bizId,
        'ferias_clt'  => $ferias,
        'created_at'  => now(), 'updated_at' => now(),
    ]);
    $GLOBALS['ferias_ids']['tipos'][] = $id;

    return $id;
}

function feriasLicenca(int $bizId, int $userId, int $tipoId, string $inicio, string $fim, string $status = 'approved'): void
{
    $GLOBALS['ferias_ids']['licencas'][] = (int) DB::table('essentials_leaves')->insertGetId([
        'essentials_leave_type_id' => $tipoId,
        'business_id' => $bizId, 'user_id' => $userId,
        'start_date' => $inicio, 'end_date' => $fim, 'status' => $status,
        'reason' => FERIAS_MARCADOR,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Dia previsto de 480 min sem nenhuma batida: a falta cheia que a apuração grava. */
function feriasFalta(Colaborador $c, string $data): void
{
    $GLOBALS['ferias_ids']['apuracoes'][] = (int) DB::table('ponto_apuracao_dia')->insertGetId([
        'business_id' => $c->business_id, 'colaborador_config_id' => $c->id, 'data' => $data,
        'prevista_carga_minutos' => 480, 'realizada_trabalhada_minutos' => 0, 'falta_minutos' => 480,
        'estado' => 'CALCULADO', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

afterEach(function () {
    $ids = $GLOBALS['ferias_ids'];
    DB::table('essentials_leaves')->whereIn('id', $ids['licencas'] ?: [0])->delete();
    DB::table('essentials_leave_types')->whereIn('id', $ids['tipos'] ?: [0])->delete();
    DB::table('ponto_apuracao_dia')->whereIn('id', $ids['apuracoes'] ?: [0])->delete();
    DB::table('ponto_colaborador_config')->where('matricula', 'like', FERIAS_MARCADOR . '%')->delete();
    $GLOBALS['ferias_ids'] = ['tipos' => [], 'licencas' => [], 'apuracoes' => []];
});

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['ponto_colaborador_config', 'ponto_apuracao_dia', 'essentials_leaves', 'essentials_leave_types'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — schema não migrado nesta lane.");
        }
    }
    if (! Schema::hasColumn('essentials_leave_types', 'ferias_clt')) {
        $this->markTestSkipped('Coluna ferias_clt ausente — migration do Essentials não rodou.');
    }

    $this->tenant     = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();
    $this->user       = \App\User::where('business_id', $this->tenant->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->svc  = app(FeriasService::class);
    $this->hoje = Carbon::parse('2026-10-26');
});

it('UC-FERIAS-01 · tabela do art. 130: dias de direito por faltas injustificadas', function () {
    $casos = [0 => 30, 5 => 30, 6 => 24, 14 => 24, 15 => 18, 23 => 18, 24 => 12, 32 => 12, 33 => 0];
    foreach ($casos as $faltas => $dias) {
        $this->assertSame($dias, FeriasService::diasDeDireito($faltas), "{$faltas} faltas");
    }
});

it('UC-FERIAS-02 · períodos desde a admissão: aquisitivo de 12 meses e concessivo dos 12 seguintes', function () {
    $c = feriasColaborador($this->tenant->id, $this->user->id, '2024-01-10');

    $p = $this->svc->periodos($c, $this->hoje);

    expect(count($p))->toBe(3);
    expect([$p[0]['inicio'], $p[0]['fim'], $p[0]['concessivo_fim']])->toBe(['2024-01-10', '2025-01-09', '2026-01-09']);
    expect([$p[1]['inicio'], $p[1]['fim'], $p[1]['concessivo_fim']])->toBe(['2025-01-10', '2026-01-09', '2027-01-09']);
    expect($p[2]['situacao'])->toBe(FeriasService::SITUACAO_EM_AQUISICAO);
    // Sem nenhuma férias gozada: o 1º passou do concessivo (dobra, art. 137) e o 2º está a gozar.
    expect($p[0]['situacao'])->toBe(FeriasService::SITUACAO_VENCIDO);
    expect($p[1]['situacao'])->toBe(FeriasService::SITUACAO_A_GOZAR);
    expect($p[1]['dias_para_vencer'])->toBe(75);
});

it('UC-FERIAS-03 · faltas injustificadas do aquisitivo reduzem os dias (6 faltas → 24 dias)', function () {
    $c = feriasColaborador($this->tenant->id, $this->user->id, '2024-01-10');
    foreach (['2025-02-03', '2025-02-04', '2025-02-05', '2025-02-06', '2025-02-07', '2025-02-10'] as $d) {
        feriasFalta($c, $d);
    }

    $p = $this->svc->periodos($c, $this->hoje);

    expect($p[1]['faltas_injustificadas'])->toBe(6);
    expect($p[1]['dias_direito'])->toBe(24);
    expect($p[0]['dias_direito'])->toBe(30); // as faltas são do 2º aquisitivo, não do 1º
});

it('UC-FERIAS-04 · só licença aprovada de tipo marcado como férias abate o saldo, do período mais antigo', function () {
    $c = feriasColaborador($this->tenant->id, $this->user->id, '2024-01-10');
    $ferias = feriasTipo($this->tenant->id, true);
    $outro  = feriasTipo($this->tenant->id, false);

    feriasLicenca($this->tenant->id, $this->user->id, $ferias, '2025-03-03', '2025-03-22');            // 20 dias
    feriasLicenca($this->tenant->id, $this->user->id, $outro, '2025-04-01', '2025-04-10');             // não é férias
    feriasLicenca($this->tenant->id, $this->user->id, $ferias, '2025-05-01', '2025-05-05', 'pending');  // não aprovada

    $p = $this->svc->periodos($c, $this->hoje);

    expect($p[0]['dias_gozados'])->toBe(20);
    expect($p[0]['saldo'])->toBe(10);
    expect($p[0]['situacao'])->toBe(FeriasService::SITUACAO_VENCIDO);
    expect($p[1]['dias_gozados'])->toBe(0);
});

it('UC-FERIAS-05 · gozo que cobre o saldo quita o período; o excedente vai para o seguinte', function () {
    $c = feriasColaborador($this->tenant->id, $this->user->id, '2024-01-10');
    $ferias = feriasTipo($this->tenant->id, true);
    feriasLicenca($this->tenant->id, $this->user->id, $ferias, '2026-02-02', '2026-03-13'); // 40 dias

    $p = $this->svc->periodos($c, $this->hoje);

    expect($p[0]['situacao'])->toBe(FeriasService::SITUACAO_QUITADO);
    expect($p[1]['dias_gozados'])->toBe(10);
    expect($p[1]['saldo'])->toBe(20);
});

it('UC-FERIAS-06 · licença e tipo do tenant adversário não abatem nada (ADR 0093)', function () {
    $c = feriasColaborador($this->tenant->id, $this->user->id, '2024-01-10');
    $tipoAlheio = feriasTipo($this->adversario->id, true);
    feriasLicenca($this->adversario->id, $this->user->id, $tipoAlheio, '2025-03-03', '2025-04-01');
    // Tipo do 98 marcado como férias, mas a licença é do 99: também não conta.
    $tipoMeu = feriasTipo($this->tenant->id, true);
    feriasLicenca($this->adversario->id, $this->user->id, $tipoMeu, '2025-05-04', '2025-05-20');

    $p = $this->svc->periodos($c, $this->hoje);

    expect($p[0]['dias_gozados'])->toBe(0);
});

it('UC-FERIAS-07 · alerta lista o vencido do empregador e não o do adversário; sem admissão não há período', function () {
    // `user_id` é NOT NULL e único em ponto_colaborador_config: um colaborador por usuário.
    $userAlheio = \App\User::where('business_id', $this->adversario->id)->first();
    if (! $userAlheio) {
        $this->markTestSkipped('Sem user no tenant adversário — seed mínimo não rodou.');
    }
    $vencido = feriasColaborador($this->tenant->id, $this->user->id, '2024-01-10');
    $alheio  = feriasColaborador($this->adversario->id, $userAlheio->id, '2023-01-10');

    $alertas = collect($this->svc->alertas($this->tenant->id, $this->hoje, 60))
        ->filter(fn ($a) => str_starts_with((string) $a['matricula'], FERIAS_MARCADOR));

    $ids = $alertas->pluck('colaborador_id')->unique()->values()->all();
    expect($ids)->toBe([(int) $vencido->id]);
    // Com 60 dias de antecedência só o vencido entra; o 2º período vence em 75 dias.
    expect($alertas->pluck('periodo.situacao')->all())->toBe([FeriasService::SITUACAO_VENCIDO]);
    expect($ids)->not->toContain((int) $alheio->id);

    // Sem a data de admissão não há período (nem alerta inventado).
    $semAdmissao = new Colaborador(['business_id' => $this->tenant->id]);
    expect($this->svc->periodos($semAdmissao, $this->hoje))->toBe([]);
});
