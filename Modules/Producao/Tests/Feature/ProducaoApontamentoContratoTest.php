<?php

declare(strict_types=1);
// Cobre UC-PCV-19..23 (Producao.casos.md) e UC-PCV-31, 34, 35, 36 (Producao-celular.casos.md · emenda E04).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Apontamento do operador — thread 04 + E04 do playbook de Produção (ADR 0431). Os casos vêm do
 * `Producao.casos.md` / `Producao-celular.casos.md`, não do código.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99. NUNCA biz=4. Append-only: a limpeza é o rollback
 * da transação. Dia fixo com `Carbon::setTestNow` (a hora do aparelho é validada contra o servidor).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
const APT_DIA = '2026-10-15';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o fundo do PCP depende de trigger MySQL.');
    }
    if (! Schema::hasTable('pcp_apontamentos') || ! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Tabelas pcp_* ou ponto_* ausentes nesta lane.');
    }
    Carbon::setTestNow(APT_DIA.' 10:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = aptBiz99();
    $this->diego = aptUsuario($this->b98, 'diego');
    $this->felipe = aptUsuario($this->b98, 'felipe');
    $this->svc = app(ApontamentoOperadorService::class);
});

afterEach(fn () => Carbon::setTestNow());

function aptBiz99(): int
{
    if (! DB::table('business')->where('id', 99)->exists()) {
        $dono = aptUsuario(null, 'dono99');
        DB::table('business')->insert(['id' => 99, 'name' => 'PCP Tenant 99 (ficticio)', 'currency_id' => (int) (DB::table('currencies')->value('id') ?? 1),
            'owner_id' => $dono, 'stop_selling_before' => 0, 'weighing_scale_setting' => '', 'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', $dono)->update(['business_id' => 99]);
    }

    return 99;
}

function aptUsuario(?int $biz, string $nome): int
{
    return (int) DB::table('users')->insertGetId(['first_name' => 'APT '.$nome, 'username' => 'apt-'.$nome.'-'.uniqid(),
        'password' => bcrypt('x'), 'business_id' => $biz, 'created_at' => now(), 'updated_at' => now()]);
}

function aptPosto(int $biz, string $tipo): int
{
    return (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'APT-'.uniqid(), 'nome' => $tipo,
        'tipo_custo' => $tipo, 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
}

function aptEtapa(int $biz, int $posto, ?int $resp, string $inicio, int $os = 501, string $status = 'pendente'): int
{
    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'posto_id' => $posto, 'inicio_previsto' => $inicio, 'fim_previsto' => Carbon::parse($inicio)->addHour(), 'status' => $status,
        'user_id' => $resp, 'created_by' => $resp ?? 1, 'created_at' => now(), 'updated_at' => now()]);
}

/** Motivo de pausa do posto: o cadastro é da thread 03 (`pcp_motivos`); antes dela a coluna não tem FK. */
function aptMotivo(int $biz, int $posto): int
{
    if (! Schema::hasTable('pcp_motivos')) {
        return 7;
    }

    return (int) DB::table('pcp_motivos')->insertGetId(['business_id' => $biz, 'posto_id' => $posto, 'tipo' => 'pausa',
        'nome' => 'Troca de bobina', 'created_at' => now(), 'updated_at' => now()]);
}

function aptPonto(int $biz, int $userId, array $batidas): void
{
    $c = Colaborador::forceCreate(['business_id' => $biz, 'user_id' => $userId, 'matricula' => 'APT-'.uniqid(),
        'controla_ponto' => true, 'admissao' => '2025-01-01']);
    foreach ($batidas as $hora => $tipo) {
        Marcacao::create(['business_id' => $biz, 'colaborador_config_id' => $c->id, 'momento' => APT_DIA.' '.$hora,
            'origem' => Marcacao::ORIGEM_REP_P, 'tipo' => $tipo, 'usuario_criador_id' => $userId]);
    }
}

function aptRecusa(callable $f): string
{
    try {
        $f();
    } catch (ApontamentoRecusado $e) {
        return $e->codigo;
    }

    return 'nada';
}

function aptLinhas(int $biz, int $os = 501): array
{
    return DB::table('pcp_apontamentos')->where('business_id', $biz)->where('os_id', $os)->orderBy('id')->pluck('tipo')->all();
}

it('UC-PCV-19 · o operador vê só as etapas dele hoje, na ordem programada', function () {
    $p = aptPosto($this->b98, 'pessoa');
    $nove = aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 09:00:00');
    $oito = aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 08:00:00', 502);
    $doFelipe = aptEtapa($this->b98, $p, $this->felipe, APT_DIA.' 07:00:00', 503);
    aptEtapa($this->b98, $p, $this->diego, '2026-10-16 08:00:00', 504);
    aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 07:30:00', 505, 'concluida');

    $ids = $this->svc->minhasEtapasDeHoje($this->b98, $this->diego, Carbon::parse(APT_DIA))->pluck('id')->map('intval')->all();
    expect($ids)->toBe([$oito, $nove]);
    // Discriminante: o Felipe vê a dele, não as do Diego.
    expect($this->svc->minhasEtapasDeHoje($this->b98, $this->felipe, Carbon::parse(APT_DIA))->pluck('id')->map('intval')->all())->toBe([$doFelipe]);
});

it('UC-PCV-20 · QR abre a etapa dele e o cronômetro grava início, pausa e fim (append-only)', function () {
    $p = aptPosto($this->b98, 'pessoa');
    $etapa = aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 08:00:00');

    $qr = $this->svc->resolverQr($this->b98, $this->diego, 'comvis_os', 501);
    expect($qr['pode_apontar'])->toBeTrue()->and((int) $qr['programacao']->id)->toBe($etapa);

    $motivo = aptMotivo($this->b98, $p);
    $i1 = $this->svc->iniciar($this->b98, $this->diego, $etapa, 'c20-1', Carbon::parse(APT_DIA.' 08:00:00'));
    // Controle negativo: pausa sem motivo recusa e não grava.
    expect(aptRecusa(fn () => $this->svc->pausar($this->b98, $this->diego, $i1->id, 0, 'c20-x', Carbon::parse(APT_DIA.' 08:30:00'))))->toBe('motivo_obrigatorio');
    if (Schema::hasTable('pcp_motivos')) {
        // E04 A4: motivo de outro posto não serve.
        $alheio = aptMotivo($this->b98, aptPosto($this->b98, 'pessoa'));
        expect(aptRecusa(fn () => $this->svc->pausar($this->b98, $this->diego, $i1->id, $alheio, 'c20-y', Carbon::parse(APT_DIA.' 08:30:00'))))->toBe('motivo_obrigatorio');
    }
    $this->svc->pausar($this->b98, $this->diego, $i1->id, $motivo, 'c20-2', Carbon::parse(APT_DIA.' 08:30:00'));
    $i2 = $this->svc->iniciar($this->b98, $this->diego, $etapa, 'c20-3', Carbon::parse(APT_DIA.' 08:40:00'));
    $this->svc->concluir($this->b98, $this->diego, $i2->id, 'c20-4', Carbon::parse(APT_DIA.' 09:10:00'));

    expect(aptLinhas($this->b98))->toBe(['inicio', 'pausa', 'inicio', 'fim'])
        ->and(DB::table('pcp_apontamentos')->where('chave_idempotencia', 'c20-2')->value('motivo_id'))->toBe($motivo)
        ->and(DB::table('pcp_apontamentos')->where('chave_idempotencia', 'c20-1')->value('user_id'))->toBe($this->diego);
    // Tier 0: o QR da mesma OS lido pela empresa 99 não existe.
    expect(aptRecusa(fn () => $this->svc->resolverQr($this->b99, $this->diego, 'comvis_os', 501)))->toBe('nao_encontrado');
});

it('UC-PCV-21 · concluir passa a OS adiante e avisa o próximo responsável', function () {
    $p = aptPosto($this->b98, 'pessoa');
    $minha = aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 08:00:00');
    $proxima = aptEtapa($this->b98, $p, $this->felipe, APT_DIA.' 09:00:00');

    $i = $this->svc->iniciar($this->b98, $this->diego, $minha, 'c21-1', Carbon::parse(APT_DIA.' 08:00:00'));
    $this->svc->concluir($this->b98, $this->diego, $i->id, 'c21-2', Carbon::parse(APT_DIA.' 08:50:00'));

    expect(DB::table('pcp_programacoes')->where('id', $minha)->value('status'))->toBe('concluida')
        ->and(DB::table('pcp_programacoes')->where('id', $proxima)->value('status'))->toBe('pendente');
    $avisos = DB::table('pcp_eventos')->where('business_id', $this->b98)->where('tipo', 'aviso_proximo')->pluck('user_id')->all();
    expect($avisos)->toBe([$this->felipe]); // discriminante: quem concluiu não é avisado
});

it('UC-PCV-22 · cronômetro esquecido fecha no fim do turno, avisa o PCP e a correção não apaga o original', function () {
    $p = aptPosto($this->b98, 'pessoa');
    $etapa = aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 15:00:00');
    $i = $this->svc->iniciar($this->b98, $this->diego, $etapa, 'c22-1', Carbon::parse(APT_DIA.' 09:30:00'));

    // Discriminante: antes do fim do turno (18h padrão) nada fecha.
    expect($this->svc->fecharAbertosNoFimDoTurno($this->b98, Carbon::parse(APT_DIA.' 17:00:00')))->toBe(0);
    Carbon::setTestNow(APT_DIA.' 18:30:00');
    expect($this->svc->fecharAbertosNoFimDoTurno($this->b98, Carbon::now()))->toBe(1);

    $fim = DB::table('pcp_apontamentos')->where('inicio_id', $i->id)->first();
    expect($fim->origem)->toBe('turno')->and($fim->momento)->toBe(APT_DIA.' 18:00:00')
        ->and(DB::table('pcp_eventos')->where('tipo', 'fechado_automaticamente')->where('business_id', $this->b98)->count())->toBe(1);

    $this->svc->lancarCorrecao($this->b98, $this->diego, $i->id, Carbon::parse(APT_DIA.' 16:40:00'), 'esqueci de parar', 'c22-c');
    // Reenvio da fila: mesma chave e corpo não duplicam; corpo diferente é conflito.
    $this->svc->lancarCorrecao($this->b98, $this->diego, $i->id, Carbon::parse(APT_DIA.' 16:40:00'), 'esqueci de parar', 'c22-c');
    expect(aptRecusa(fn () => $this->svc->lancarCorrecao($this->b98, $this->diego, $i->id, Carbon::parse(APT_DIA.' 16:45:00'), 'esqueci de parar', 'c22-c')))->toBe('idempotencia_conflito');
    expect(aptRecusa(fn () => $this->svc->lancarCorrecao($this->b98, $this->diego, $i->id, Carbon::now(), ' ', 'c22-d')))->toBe('justificativa_obrigatoria');
    expect(DB::table('pcp_eventos')->where('tipo', 'correcao_solicitada')->count())->toBe(1)
        ->and(DB::table('pcp_apontamentos')->where('inicio_id', $i->id)->pluck('momento')->all())->toBe([APT_DIA.' 18:00:00']);
});

it('UC-PCV-23 · sem internet guarda a hora real e o reenvio não duplica', function () {
    $p = aptPosto($this->b98, 'pessoa');
    $etapa = aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 08:00:00');
    $hora = Carbon::parse(APT_DIA.' 08:05:00'); // tocado offline, enviado às 10:00

    $a = $this->svc->iniciar($this->b98, $this->diego, $etapa, 'c23-1', $hora);
    $b = $this->svc->iniciar($this->b98, $this->diego, $etapa, 'c23-1', $hora);
    expect($a->wasRecentlyCreated)->toBeTrue()->and($b->wasRecentlyCreated)->toBeFalse()->and($b->id)->toBe($a->id)
        ->and(DB::table('pcp_apontamentos')->where('chave_idempotencia', 'c23-1')->count())->toBe(1)
        ->and($a->momento->format('H:i'))->toBe('08:05');

    expect(aptRecusa(fn () => $this->svc->iniciar($this->b98, $this->diego, $etapa, 'c23-1', Carbon::parse(APT_DIA.' 08:06:00'))))->toBe('idempotencia_conflito')
        ->and(aptRecusa(fn () => $this->svc->concluir($this->b98, $this->diego, $a->id, 'c23-2', Carbon::now()->addMinutes(10))))->toBe('momento_invalido')
        ->and(aptRecusa(fn () => $this->svc->concluir($this->b98, $this->diego, $a->id, 'c23-3', Carbon::now()->subHours(73))))->toBe('momento_invalido')
        ->and(aptLinhas($this->b98))->toBe(['inicio']);
    // Controle: dentro da tolerância de 5 min à frente, grava.
    $this->svc->concluir($this->b98, $this->diego, $a->id, 'c23-4', Carbon::now()->addMinutes(4));
    expect(aptLinhas($this->b98))->toBe(['inicio', 'fim']);
});

it('UC-PCV-31 · máquina e pessoa abertos ao mesmo tempo; nunca dois de pessoa', function () {
    $roland = aptEtapa($this->b98, aptPosto($this->b98, 'maquina'), $this->diego, APT_DIA.' 08:00:00');
    $acab = aptEtapa($this->b98, aptPosto($this->b98, 'pessoa'), $this->diego, APT_DIA.' 08:10:00', 502);
    $outra = aptEtapa($this->b98, aptPosto($this->b98, 'pessoa'), $this->diego, APT_DIA.' 08:20:00', 503);

    $m = $this->svc->iniciar($this->b98, $this->diego, $roland, 'c31-1', Carbon::parse(APT_DIA.' 08:00:00'));
    $h = $this->svc->iniciar($this->b98, $this->diego, $acab, 'c31-2', Carbon::parse(APT_DIA.' 08:10:00'));
    expect($m->user_id)->toBeNull()->and($h->user_id)->toBe($this->diego);

    $rodando = $this->svc->rodandoAgora($this->b98, $this->diego, Carbon::now());
    expect($rodando['pessoa']->id)->toBe($h->id)->and($rodando['maquinas']->pluck('id')->all())->toBe([$m->id]);
    expect(aptRecusa(fn () => $this->svc->iniciar($this->b98, $this->diego, $outra, 'c31-3', Carbon::parse(APT_DIA.' 08:20:00'))))->toBe('pessoa_ocupada')
        ->and(DB::table('pcp_apontamentos')->where('chave_idempotencia', 'c31-3')->count())->toBe(0);
});

it('UC-PCV-34 · QR de etapa alheia mostra onde está e com quem, e não grava nada', function () {
    $etapa = aptEtapa($this->b98, aptPosto($this->b98, 'pessoa'), $this->felipe, APT_DIA.' 08:00:00');

    $qr = $this->svc->resolverQr($this->b98, $this->diego, 'comvis_os', 501);
    expect($qr['pode_apontar'])->toBeFalse()->and((int) $qr['programacao']->user_id)->toBe($this->felipe);
    expect(aptRecusa(fn () => $this->svc->iniciar($this->b98, $this->diego, $etapa, 'c34-1', Carbon::parse(APT_DIA.' 08:00:00'))))->toBe('etapa_alheia')
        ->and(aptLinhas($this->b98))->toBe([]);
    // Controle: o responsável lê o mesmo QR e pode apontar.
    expect($this->svc->resolverQr($this->b98, $this->felipe, 'comvis_os', 501)['pode_apontar'])->toBeTrue();
});

it('UC-PCV-35 · deixar a máquina rodando não pausa o apontamento dela', function () {
    $roland = aptEtapa($this->b98, aptPosto($this->b98, 'maquina'), $this->diego, APT_DIA.' 08:00:00');
    $acab = aptEtapa($this->b98, aptPosto($this->b98, 'pessoa'), $this->diego, APT_DIA.' 08:10:00', 502);
    $m = $this->svc->iniciar($this->b98, $this->diego, $roland, 'c35-1', Carbon::parse(APT_DIA.' 08:00:00'));

    // "Deixar rodando" volta à lista sem gravar; o operador segue para outra etapa.
    $this->svc->iniciar($this->b98, $this->diego, $acab, 'c35-2', Carbon::parse(APT_DIA.' 08:10:00'));
    expect(DB::table('pcp_apontamentos')->where('inicio_id', $m->id)->count())->toBe(0)
        ->and($this->svc->rodandoAgora($this->b98, $this->diego, Carbon::now())['maquinas']->pluck('id')->all())->toBe([$m->id]);
});

it('UC-PCV-36 · pessoa fecha na saída do ponto; excedente > 30 min vira pendência; o ponto não muda', function () {
    $p = aptPosto($this->b98, 'pessoa');
    aptPonto($this->b98, $this->diego, ['08:00:00' => Marcacao::TIPO_ENTRADA, '17:52:00' => Marcacao::TIPO_SAIDA]);
    aptPonto($this->b98, $this->felipe, ['08:00:00' => Marcacao::TIPO_ENTRADA, '09:00:00' => Marcacao::TIPO_SAIDA]);
    $iD = $this->svc->iniciar($this->b98, $this->diego, aptEtapa($this->b98, $p, $this->diego, APT_DIA.' 08:00:00'), 'c36-1', Carbon::parse(APT_DIA.' 08:30:00'));
    $iF = $this->svc->iniciar($this->b98, $this->felipe, aptEtapa($this->b98, $p, $this->felipe, APT_DIA.' 07:00:00', 502), 'c36-2', Carbon::parse(APT_DIA.' 07:00:00'));
    $marcacoes = DB::table('ponto_marcacoes')->count();

    Carbon::setTestNow(APT_DIA.' 18:30:00');
    $this->svc->fecharAbertosNoFimDoTurno($this->b98, Carbon::now());

    $fimD = DB::table('pcp_apontamentos')->where('inicio_id', $iD->id)->first();
    expect($fimD->origem)->toBe('ponto')->and($fimD->momento)->toBe(APT_DIA.' 17:52:00');
    // Felipe: apontado 07:00→09:00 = 120 min contra 60 de presença → pendência. Diego: 562 ≤ 592 + 30, sem pendência.
    expect(DB::table('pcp_apontamentos')->where('inicio_id', $iF->id)->value('momento'))->toBe(APT_DIA.' 09:00:00')
        ->and(DB::table('pcp_eventos')->where('business_id', $this->b98)->where('tipo', 'pendencia_gestor')->pluck('user_id')->all())->toBe([$this->felipe])
        ->and(DB::table('ponto_marcacoes')->count())->toBe($marcacoes);
});

// ── Produção da impressão no concluir (US-COMVIS-004 · UC-FLX-09) ─────────────────────────────

it('UC-PCIMP-01 · concluir a etapa de máquina grava m² impresso e tinta C/M/Y/K na linha fim', function () {
    $roland = aptPosto($this->b98, 'maquina');
    $i1 = $this->svc->iniciar($this->b98, $this->diego, aptEtapa($this->b98, $roland, $this->diego, APT_DIA.' 08:00:00'), 'imp1-1', Carbon::parse(APT_DIA.' 08:00:00'));
    $i2 = $this->svc->iniciar($this->b98, $this->diego, aptEtapa($this->b98, $roland, $this->diego, APT_DIA.' 08:30:00', 502), 'imp1-2', Carbon::parse(APT_DIA.' 08:30:00'));

    $fim = $this->svc->concluir($this->b98, $this->diego, $i1->id, 'imp1-3', Carbon::parse(APT_DIA.' 09:00:00'),
        ['m2_impresso' => '12.5', 'tinta_ml_c' => '3.2', 'tinta_ml_k' => '1', 'tinta_ml_y' => '']);
    $linha = DB::table('pcp_apontamentos')->find($fim->id);
    expect($linha->tipo)->toBe('fim')
        ->and($linha->m2_impresso)->toBe('12.500')->and($linha->tinta_ml_c)->toBe('3.20')->and($linha->tinta_ml_k)->toBe('1.00')
        // Vazio e ausente ficam NULL ("não informado"), não zero.
        ->and($linha->tinta_ml_m)->toBeNull()->and($linha->tinta_ml_y)->toBeNull();

    // Discriminante: concluir sem informar nada grava a linha fim com tudo NULL.
    $semDados = DB::table('pcp_apontamentos')->find($this->svc->concluir($this->b98, $this->diego, $i2->id, 'imp1-4', Carbon::parse(APT_DIA.' 09:10:00'))->id);
    expect($semDados->m2_impresso)->toBeNull()->and($semDados->tinta_ml_c)->toBeNull();
});

it('UC-PCIMP-02 · valor inválido recusa o concluir e nada grava; a etapa segue aberta', function () {
    $etapa = aptEtapa($this->b98, aptPosto($this->b98, 'maquina'), $this->diego, APT_DIA.' 08:00:00');
    $i = $this->svc->iniciar($this->b98, $this->diego, $etapa, 'imp2-1', Carbon::parse(APT_DIA.' 08:00:00'));

    foreach ([['m2_impresso' => '12,5'], ['m2_impresso' => '-1'], ['tinta_ml_m' => 'abc'], ['tinta_ml_c' => '1.234'], ['m2_impresso' => '1.000.5']] as $n => $ruim) {
        expect(aptRecusa(fn () => $this->svc->concluir($this->b98, $this->diego, $i->id, "imp2-x{$n}", Carbon::parse(APT_DIA.' 09:00:00'), $ruim)))->toBe('quantidade_invalida');
    }
    expect(aptLinhas($this->b98))->toBe(['inicio'])
        ->and(DB::table('pcp_programacoes')->where('id', $etapa)->value('status'))->not->toBe('concluida');

    // Controle: o mesmo concluir com valores válidos (3 casas no m², zero na tinta) passa.
    $this->svc->concluir($this->b98, $this->diego, $i->id, 'imp2-ok', Carbon::parse(APT_DIA.' 09:00:00'), ['m2_impresso' => '0.125', 'tinta_ml_m' => '0']);
    expect(aptLinhas($this->b98))->toBe(['inicio', 'fim'])
        ->and(DB::table('pcp_apontamentos')->where('chave_idempotencia', 'imp2-ok')->value('tinta_ml_m'))->toBe('0.00');
});

it('UC-PCIMP-03 · o reenvio da fila offline com m² e tinta grava uma linha fim só', function () {
    $i = $this->svc->iniciar($this->b98, $this->diego, aptEtapa($this->b98, aptPosto($this->b98, 'maquina'), $this->diego, APT_DIA.' 08:00:00'),
        'imp3-1', Carbon::parse(APT_DIA.' 08:00:00'));
    $dados = ['m2_impresso' => '8', 'tinta_ml_k' => '2.5'];

    $a = $this->svc->concluir($this->b98, $this->diego, $i->id, 'imp3-2', Carbon::parse(APT_DIA.' 09:00:00'), $dados);
    $b = $this->svc->concluir($this->b98, $this->diego, $i->id, 'imp3-2', Carbon::parse(APT_DIA.' 09:00:00'), $dados);
    expect($b->id)->toBe($a->id)->and($b->wasRecentlyCreated)->toBeFalse()
        ->and(DB::table('pcp_apontamentos')->where('inicio_id', $i->id)->count())->toBe(1)
        ->and(DB::table('pcp_apontamentos')->where('inicio_id', $i->id)->value('m2_impresso'))->toBe('8.000');
});
