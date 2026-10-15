<?php

declare(strict_types=1);
// Cobre UC-PCV-28 (Producao.casos.md) e UC-PCV-31, UC-PCV-32 (Producao-celular.casos.md · emenda E02).

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Services\ApontamentoService;
use Modules\Producao\Services\CustoRealService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fundo do PCP — thread 02 + E02 do playbook de Produção (ADR 0431). Os casos vêm do
 * `Producao.casos.md` / `Producao-celular.casos.md`, não do código.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99, criado aqui se faltar. NUNCA biz=4.
 * Append-only: limpeza é o rollback da transação (trigger não dispara em rollback).
 * ⚠️ SKIP em SQLite (trigger e FK do schema MySQL): leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o fundo do PCP depende de trigger MySQL.');
    }
    if (! Schema::hasTable('pcp_apontamentos')) {
        $this->markTestSkipped('Tabelas pcp_* ausentes — migrations do Modules/Producao não rodaram.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = pcpGarantirBiz99();
    $this->u98 = pcpUsuario($this->b98, 'diego');
    $this->u99 = pcpUsuario($this->b99, 'outro');
    $this->svc = app(ApontamentoService::class);
});

function pcpGarantirBiz99(): int
{
    if (! DB::table('business')->where('id', 99)->exists()) {
        $dono = pcpUsuario(null, 'dono99');
        DB::table('business')->insert(['id' => 99, 'name' => 'PCP Tenant 99 (ficticio)', 'currency_id' => (int) (DB::table('currencies')->value('id') ?? 1),
            'owner_id' => $dono, 'stop_selling_before' => 0, 'weighing_scale_setting' => '', 'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', $dono)->update(['business_id' => 99]);
    }

    return 99;
}

function pcpUsuario(?int $biz, string $nome): int
{
    return (int) DB::table('users')->insertGetId(['first_name' => 'PCP '.$nome, 'username' => 'pcp-'.$nome.'-'.uniqid(),
        'password' => bcrypt('x'), 'business_id' => $biz, 'created_at' => now(), 'updated_at' => now()]);
}

function pcpPosto(int $biz, string $codigo, string $tipo, string $custo): Posto
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts, não aqui.
    return Posto::withoutGlobalScopes()->create(['business_id' => $biz, 'codigo' => $codigo, 'nome' => $codigo,
        'tipo_custo' => $tipo, 'custo_hora' => $custo]);
}

function pcpProgramacao(int $biz, int $postoId, int $responsavel): int
{
    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => 'repair_job_sheet', 'os_id' => 28,
        'posto_id' => $postoId, 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(), 'user_id' => $responsavel,
        'created_by' => $responsavel, 'created_at' => now(), 'updated_at' => now()]);
}

function pcpComo(int $userId, int $biz): void
{
    test()->actingAs(\App\User::find($userId));
    session(['user.business_id' => $biz]);
}

it('fundo · apontamento e evento são append-only (trigger, como o Ponto)', function () {
    $posto = DB::table('pcp_postos')->insertGetId(['business_id' => $this->b98, 'codigo' => 'LAM-AO', 'nome' => 'Laminação',
        'tipo_custo' => 'pessoa', 'custo_hora' => '28.00', 'created_at' => now(), 'updated_at' => now()]);
    $ap = DB::table('pcp_apontamentos')->insertGetId(['business_id' => $this->b98, 'chave_idempotencia' => 'ch-ao', 'tipo' => 'inicio',
        'posto_id' => $posto, 'tipo_custo' => 'pessoa', 'custo_hora' => '28.00', 'user_id' => $this->u98,
        'iniciado_por' => $this->u98, 'momento' => now()]);
    $ev = DB::table('pcp_eventos')->insertGetId(['business_id' => $this->b98, 'tipo' => 'x']);

    expect(fn () => DB::table('pcp_apontamentos')->where('id', $ap)->update(['justificativa' => 'mexi']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('pcp_apontamentos')->where('id', $ap)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('pcp_eventos')->where('id', $ev)->update(['tipo' => 'y']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('pcp_eventos')->where('id', $ev)->delete())->toThrow(QueryException::class);

    // Controle: a tabela mutável do fundo aceita update (o bloqueio não é geral) e a linha segue lá.
    expect(DB::table('pcp_postos')->where('id', $posto)->update(['custo_hora' => '30.00']))->toBe(1)
        ->and(DB::table('pcp_apontamentos')->where('id', $ap)->value('justificativa'))->toBeNull();
});

it('UC-PCV-28 · cada empresa vê só a sua produção', function () {
    foreach ([$this->b98 => $this->u98, $this->b99 => $this->u99] as $biz => $user) {
        $posto = pcpPosto($biz, 'ROLAND', Posto::MAQUINA, '42.00'); // mesmo código nas duas: único por empresa
        // SUPERADMIN: fixture grava nas duas empresas; o escopo é provado nos asserts.
        Apontamento::withoutGlobalScopes()->create(['business_id' => $biz, 'chave_idempotencia' => "ch-28-{$biz}", 'tipo' => 'inicio',
            'os_tipo' => 'repair_job_sheet', 'os_id' => 28, 'posto_id' => $posto->id, 'tipo_custo' => Posto::MAQUINA,
            'custo_hora' => '42.00', 'maquina_id' => $posto->id, 'iniciado_por' => $user, 'momento' => now()]);
        Evento::withoutGlobalScopes()->create(['business_id' => $biz, 'tipo' => 'etapa_iniciada', 'os_tipo' => 'repair_job_sheet', 'os_id' => 28]);
    }

    pcpComo($this->u98, $this->b98);
    expect(Posto::query()->where('codigo', 'ROLAND')->pluck('business_id')->map('intval')->all())->toBe([$this->b98])
        ->and(Apontamento::query()->where('os_id', 28)->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98])
        ->and(Evento::query()->where('os_id', 28)->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98]);

    // O posto da 99, lido pelo id a partir da 98, não existe para ela.
    $postoAlheio = Posto::withoutGlobalScopes()->where('business_id', $this->b99)->where('codigo', 'ROLAND')->value('id');
    expect(Posto::query()->find($postoAlheio))->toBeNull();

    // Discriminante: a mesma consulta como 99 devolve só a 99 (o filtro troca, não esvazia).
    pcpComo($this->u99, $this->b99);
    expect(Posto::query()->where('codigo', 'ROLAND')->pluck('business_id')->map('intval')->all())->toBe([$this->b99]);

    // Controle negativo: sem escopo os dois registros existem — o filtro de cima é real.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(Posto::withoutGlobalScopes()->where('codigo', 'ROLAND')->whereIn('business_id', [$this->b98, $this->b99])->count())->toBe(2);
});

it('UC-PCV-28 · operador da 98 não aponta em posto da 99, e nada é gravado', function () {
    $alheio = pcpPosto($this->b99, 'ROLAND28', Posto::MAQUINA, '42.00');
    $this->svc->iniciar($this->b99, $alheio->id, $this->u99, 'ch-28b-99', 'repair_job_sheet', 28);

    expect(fn () => $this->svc->iniciar($this->b98, $alheio->id, $this->u98, 'ch-28b-alheio'))->toThrow(ModelNotFoundException::class);
    $this->assertSame(0, DB::table('pcp_apontamentos')->where('chave_idempotencia', 'ch-28b-alheio')->count());
    $this->assertSame(0, $this->svc->abertos($this->b98)->where('os_id', 28)->count(), 'abertos da 98 não traz o da 99');
    // Etapa programada da 99 não serve para apontamento da 98.
    $posto98 = pcpPosto($this->b98, 'ROLAND28B', Posto::MAQUINA, '42.00');
    $prog99 = pcpProgramacao($this->b99, $alheio->id, $this->u99);
    expect(fn () => $this->svc->iniciar($this->b98, $posto98->id, $this->u98, 'ch-28b-prog', programacaoId: $prog99))->toThrow(ModelNotFoundException::class);
    $this->assertSame(0, DB::table('pcp_apontamentos')->where('chave_idempotencia', 'ch-28b-prog')->count());
    // Controle negativo: o aberto existe, na 99.
    $this->assertSame(1, $this->svc->abertos($this->b99)->where('os_id', 28)->count());
});

it('UC-PCV-31 · dois cronômetros: máquina e pessoa', function () {
    $roland = pcpPosto($this->b98, 'ROLAND31', Posto::MAQUINA, '42.00');
    $hp = pcpPosto($this->b98, 'HP31', Posto::MAQUINA, '40.00');
    $acab = pcpPosto($this->b98, 'ACAB31', Posto::PESSOA, '28.00');
    $lam = pcpPosto($this->b98, 'LAM31', Posto::PESSOA, '28.00');

    $etapa = pcpProgramacao($this->b98, $roland->id, $this->u98);
    $maq = $this->svc->iniciar($this->b98, $roland->id, $this->u98, 'ch-31-roland', 'repair_job_sheet', 31, programacaoId: $etapa);
    $pes = $this->svc->iniciar($this->b98, $acab->id, $this->u98, 'ch-31-acab', 'repair_job_sheet', 31);

    expect($this->svc->abertos($this->b98)->where('os_id', 31)->count())->toBe(2)
        ->and([$maq->maquina_id, $maq->user_id, $maq->iniciado_por])->toBe([$roland->id, null, $this->u98])
        ->and([$pes->maquina_id, $pes->user_id])->toBe([null, $this->u98])
        // A hora da Roland não é hora do Diego: só 1 apontamento de pessoa é dele.
        ->and($this->svc->abertos($this->b98)->where('user_id', $this->u98)->count())->toBe(1);

    // Não abre 2º de pessoa — e nada é gravado.
    expect(fn () => $this->svc->iniciar($this->b98, $lam->id, $this->u98, 'ch-31-lam'))->toThrow(DomainException::class);
    $this->assertSame(0, DB::table('pcp_apontamentos')->where('chave_idempotencia', 'ch-31-lam')->count());

    expect($maq->programacao_id)->toBe($etapa);

    // Controle negativo: N de máquina é permitido.
    $this->svc->iniciar($this->b98, $hp->id, $this->u98, 'ch-31-hp', 'repair_job_sheet', 31);
    expect($this->svc->abertos($this->b98)->where('os_id', 31)->count())->toBe(3);

    // Idempotência: reenvio da fila devolve a mesma linha; a chave é única POR empresa.
    expect($this->svc->iniciar($this->b98, $roland->id, $this->u98, 'ch-31-roland')->id)->toBe($maq->id)
        ->and(DB::table('pcp_apontamentos')->where('business_id', $this->b98)->where('chave_idempotencia', 'ch-31-roland')->count())->toBe(1);
    $roland99 = pcpPosto($this->b99, 'ROLAND31', Posto::MAQUINA, '42.00');
    expect($this->svc->iniciar($this->b99, $roland99->id, $this->u99, 'ch-31-roland')->business_id)->toBe($this->b99);

    // Pausa com motivo fecha o trecho (a pessoa fica livre) e também é append-only.
    $pausa = $this->svc->pausar($this->b98, $pes->id, $this->u98, 'ch-31-acab-pausa', 7);
    expect([$pausa->tipo, $pausa->inicio_id, $pausa->motivo_id])->toBe(['pausa', $pes->id, 7])
        ->and($this->svc->pessoaAberto($this->b98, $this->u98))->toBeFalse()
        ->and(fn () => DB::table('pcp_apontamentos')->where('id', $pausa->id)->update(['motivo_id' => 8]))->toThrow(QueryException::class);
    $pes = $this->svc->iniciar($this->b98, $acab->id, $this->u98, 'ch-31-acab-retoma', 'repair_job_sheet', 31); // retomar = início novo

    // Fechado, a pessoa volta a poder abrir; fechar de novo não acha o aberto.
    $this->svc->fechar($this->b98, $pes->id, $this->u98, 'ch-31-acab-fim');
    expect($this->svc->pessoaAberto($this->b98, $this->u98))->toBeFalse()
        ->and(fn () => $this->svc->fechar($this->b98, $pes->id, $this->u98, 'ch-31-acab-fim2'))->toThrow(ModelNotFoundException::class);
});

it('UC-PCV-32 · custo real soma máquina, pessoa e terceiro — dupla prova', function () {
    $roland = pcpPosto($this->b98, 'ROLAND32', Posto::MAQUINA, '42.00');
    $acab = pcpPosto($this->b98, 'ACAB32', Posto::PESSOA, '28.00');
    $t = fn (string $h) => Carbon::parse("2026-10-15 {$h}:00");
    $par = function (int $biz, Posto $posto, int $user, int $os, string $de, ?string $ate, string $k) use ($t) {
        $i = $this->svc->iniciar($biz, $posto->id, $user, "ch-32-{$k}", 'repair_job_sheet', $os, momento: $t($de));
        if ($ate) {
            $this->svc->fechar($biz, $i->id, $user, "ch-32-{$k}-fim", momento: $t($ate));
        }
    };

    $par($this->b98, $roland, $this->u98, 7001, '10:00', '12:00', 'a'); // 2 h máquina × 42
    $par($this->b98, $acab, $this->u98, 7001, '12:00', '13:00', 'b');   // 1 h pessoa × 28
    // Discriminantes — nada disto pode entrar: aberto, outra OS, mesma OS em outra empresa.
    $par($this->b98, $roland, $this->u98, 7001, '13:00', null, 'aberto');
    $par($this->b98, $roland, $this->u98, 7002, '08:00', '13:00', 'outra-os');
    $par($this->b99, pcpPosto($this->b99, 'ROLAND32', Posto::MAQUINA, '42.00'), $this->u99, 7001, '08:00', '18:00', 'outra-biz');

    // O custo-hora foi copiado no início: mexer no posto agora não reescreve o passado.
    DB::table('pcp_postos')->where('id', $roland->id)->update(['custo_hora' => '999.00']);

    $custo = app(CustoRealService::class)->daOs($this->b98, 'repair_job_sheet', 7001, '480.00');
    expect($custo)->toBe(['maquina' => '84.00', 'pessoa' => '28.00', 'terceiros' => '480.00', 'total' => '592.00']);

    // Caminho 2, independente do Service: soma no SQL (TIMESTAMPDIFF × custo copiado) + terceiro.
    $sql = (string) DB::table('pcp_apontamentos as i')->join('pcp_apontamentos as f', 'f.inicio_id', '=', 'i.id')
        ->where('i.business_id', $this->b98)->where('i.os_tipo', 'repair_job_sheet')->where('i.os_id', 7001)
        ->selectRaw('ROUND(SUM(TIMESTAMPDIFF(SECOND, i.momento, f.momento) / 3600 * i.custo_hora) + 480, 2) as total')->value('total');
    expect($sql)->toBe('592.00');

    // Controle negativo: o filtro discrimina — a OS 7002 dá 5 h × 42, não o mesmo número.
    expect(app(CustoRealService::class)->daOs($this->b98, 'repair_job_sheet', 7002)['total'])->toBe('210.00');
});
