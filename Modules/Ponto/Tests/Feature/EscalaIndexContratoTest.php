<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato da LISTA de escalas — `/ponto/escalas` → Escalas/Index.casos.md (UC-ESCIDX-01..02).
 *
 * Cada teste cita o UC no TÍTULO do `it()` — é o que o manifesto G-7 alcança.
 *
 * Os UC derivam de `CU-PONTO-12` (SDD §6.5) + ADR 0093 + charter + CLT Art. 58. NÃO do `.tsx`.
 *
 * ── O que este arquivo NÃO cobre, de propósito ─────────────────────────────────────────
 * A tela irmã (`Escalas/Form`) já tem contrato próprio no `EscalaFormContratoTest`, com dois UC
 * que nascem failing-first por desenho (UC-ESCF-01 atributo fantasma no `@edit`; UC-ESCF-02
 * `validated()` num `Request` que não é `FormRequest`). Aqui só o que é da LISTA.
 * O eixo cross-tenant de `EscalaTurno` (SDD §9 D-6 — o turno não tem `HasBusinessScope`) tem
 * dono na lane: `Wave27CrossTenantEscalaTest`.
 *
 * `assertStringNotContainsString` e NÃO `expect()->not->toContain($x, $msg)`: o `toContain` do
 * Pest recebe MÚLTIPLOS needles, e a mensagem viraria um 2º needle (§5 proibicoes 2026-07-28).
 *
 * Tier 0: adversário é o biz fictício 99 via `garantirBizAlheio()`, NUNCA biz=4 (ADR 0358).
 * Sem `RefreshDatabase` — a lane ponto-pest proíbe.
 *
 * @see \Modules\Ponto\Http\Controllers\EscalaController::index
 */

const ESCIDX_MARCA = 'SDD-ESCIDX-CONTRATO';

function escIdxPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

function escIdxCriarEscala(int $businessId, string $sufixo): int
{
    return DB::table('ponto_escalas')->insertGetId([
        'business_id'           => $businessId,
        'nome'                  => ESCIDX_MARCA . '-' . $sufixo,
        'codigo'                => substr(ESCIDX_MARCA . $sufixo, 0, 30),
        'tipo'                  => 'FIXA',
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2640,
        'permite_banco_horas'   => 0,
        'ativo'                 => 1,
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
}

afterEach(function () {
    try {
        $ids = DB::table('ponto_escalas')->where('nome', 'like', ESCIDX_MARCA . '%')->pluck('id');
        if ($ids->isNotEmpty()) {
            // Turno primeiro: a FK de `ponto_escala_turnos` referencia a escala.
            DB::table('ponto_escala_turnos')->whereIn('escala_id', $ids)->delete();
            DB::table('ponto_escalas')->whereIn('id', $ids)->delete();
        }
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort, igual aos irmãos do módulo.
    }
});

it('UC-ESCIDX-01 · a lista não traz escala de outro empregador', function () {
    $this->actAsAdmin();
    escIdxPrecisaDe(['ponto_escalas']);

    $alheio = $this->garantirBizAlheio();
    $idAlheia = escIdxCriarEscala($alheio, 'alheia');
    $nomeAlheio = ESCIDX_MARCA . '-alheia';

    // Pré-condição anti-vácuo: sem a escala do outro lado, "não vazou" seria verdade por vácuo.
    expect(DB::table('ponto_escalas')->where('id', $idAlheia)->exists())
        ->toBeTrue('A escala do empregador adversário tem de existir — senão o caso não exerce isolamento.');

    $resp = $this->inertiaGet('/ponto/escalas');
    $resp->assertStatus(200);

    $this->assertStringNotContainsString(
        $nomeAlheio,
        $resp->getContent(),
        'Escala de OUTRO empregador não pode aparecer na lista — ela revela o padrão de jornada '
        . 'praticado por ele (CU-PONTO-12 · ADR 0093). Aqui a defesa é DUPLA (o where do controller '
        . 'e o global scope); este caso existe para que a queda de qualquer uma não passe em silêncio.'
    );

    $this->removerBizAlheio();
});

it('UC-ESCIDX-02 · cada escala informa quantos turnos tem', function () {
    $this->actAsAdmin();
    escIdxPrecisaDe(['ponto_escalas', 'ponto_escala_turnos']);

    // DUAS escalas com contagens DIFERENTES, e é isto que faz o caso discriminar.
    //
    // A 1ª versão criava só uma escala, com 1 turno, e afirmava `turnos_count === 1`. Passava —
    // e passava por SORTE: num banco onde a tabela de turnos tinha exatamente 1 linha naquele
    // instante, trocar `withCount('turnos')` por uma contagem GLOBAL (`DB::table(...)->count()`,
    // sem vínculo com a escala da linha) devolvia 1 também, e o teste ficava verde com o
    // agregado quebrado. Medido no CT 100: com essa mutação, `2 passed`.
    //
    // Com 1 e 2 turnos, qualquer agregado que ignore o vínculo devolve o MESMO número nas duas
    // linhas (aqui, 3) — e aí pelo menos um dos dois asserts cai. O caso passa a morder nos dois
    // eixos: `withCount` perdido (vira 0) e agregado sem vínculo (vira o total).
    $idUmTurno    = escIdxCriarEscala((int) $this->business->id, 'um-turno');
    $idDoisTurnos = escIdxCriarEscala((int) $this->business->id, 'dois-turnos');

    foreach ([[$idUmTurno, 1], [$idDoisTurnos, 2], [$idDoisTurnos, 3]] as [$escalaId, $diaSemana]) {
        DB::table('ponto_escala_turnos')->insert([
            'escala_id'    => $escalaId,
            'dia_semana'   => $diaSemana,
            'hora_entrada' => '08:00:00',
            'hora_saida'   => '17:00:00',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    $resp = $this->inertiaGet('/ponto/escalas');
    $resp->assertStatus(200);

    $linhas = collect($resp->json('props.escalas.data') ?? []);
    $comUm   = $linhas->firstWhere('id', $idUmTurno);
    $comDois = $linhas->firstWhere('id', $idDoisTurnos);

    // Pré-condição anti-vácuo: se as escalas não vieram na página, afirmar a contagem delas
    // seria afirmar sobre `null` (LC-13 — verde por não-execução).
    expect($comUm)->not->toBeNull(
        'A escala de 1 turno tem de aparecer na lista do meu empregador — senão o caso não '
        . 'chega a medir a contagem.'
    );
    expect($comDois)->not->toBeNull('A escala de 2 turnos idem.');

    // A contagem é o que distingue escala PRONTA de casca sem turno: sem turno a apuração não
    // tem contra o que comparar entrada e saída. Perder o `withCount('turnos')` faz o atributo
    // resolver null → 0 e a lista passa a dizer que TODA escala tem zero turnos.
    expect($comUm['turnos_count'] ?? null)->toBe(1,
        'A escala de 1 turno tem de informar 1. Zero ou ausente aqui é a assinatura de '
        . '`withCount` perdido — a família de "atributo fantasma" do SDD §9 D-1/D-8, em que a '
        . 'tela mostra número que o banco não confirmou.'
    );

    expect($comDois['turnos_count'] ?? null)->toBe(2,
        'A escala de 2 turnos tem de informar 2 — e é ESTE assert que separa a contagem VINCULADA '
        . 'de um agregado que ignora o vínculo: um total global devolveria o mesmo número nas '
        . 'duas linhas, e o contrato aqui é "quantos turnos ESTA escala tem".'
    );
});

it('UC-ESCIDX-06 · a linha mostra o horário do 1º turno da PRÓPRIA escala, ou diz que não há turno', function () {
    $this->actAsAdmin();
    escIdxPrecisaDe(['ponto_escalas', 'ponto_escala_turnos']);

    // TRÊS escalas, e cada uma separa uma mutação plausível:
    //  · `ordenada` recebe o turno de quarta ANTES do de segunda — sem o orderBy(dia_semana) o
    //    1º turno viria na ordem de inserção (10:00–19:00) e o assert cai;
    //  · `outra` tem horário diferente — um "1º turno" global (sem vínculo com a linha) daria o
    //    mesmo texto nas duas;
    //  · `casca` não tem turno — o texto tem de ser null, não o turno de outra escala.
    $idOrdenada = escIdxCriarEscala((int) $this->business->id, 'ordenada');
    $idOutra    = escIdxCriarEscala((int) $this->business->id, 'outra');
    $idCasca    = escIdxCriarEscala((int) $this->business->id, 'casca');

    foreach ([
        [$idOrdenada, 3, '10:00:00', '19:00:00'],
        [$idOrdenada, 1, '08:00:00', '17:00:00'],
        [$idOutra,    2, '13:00:00', '22:00:00'],
    ] as [$escalaId, $dia, $entrada, $saida]) {
        DB::table('ponto_escala_turnos')->insert([
            'escala_id'    => $escalaId,
            'dia_semana'   => $dia,
            'hora_entrada' => $entrada,
            'hora_saida'   => $saida,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    $resp = $this->inertiaGet('/ponto/escalas');
    $resp->assertStatus(200);

    $linhas = collect($resp->json('props.escalas.data') ?? []);
    $ordenada = $linhas->firstWhere('id', $idOrdenada);
    $outra    = $linhas->firstWhere('id', $idOutra);
    $casca    = $linhas->firstWhere('id', $idCasca);

    // Pré-condição anti-vácuo (LC-13): sem as três linhas, afirmar o horário seria afirmar sobre null.
    expect($ordenada)->not->toBeNull('A escala `ordenada` tem de aparecer na lista do meu empregador.');
    expect($outra)->not->toBeNull('A escala `outra` idem.');
    expect($casca)->not->toBeNull('A escala `casca` idem.');

    // O turno é filho da escala e herda o isolamento pelo parent. Se o escopo do eager-load
    // descartasse o turno do PRÓPRIO empregador, o horário viria null aqui.
    expect($ordenada['primeiro_turno'] ?? null)->toBe('08:00–17:00',
        'O 1º turno é o de MENOR dia da semana (segunda, 08:00–17:00), não o primeiro inserido '
        . '(quarta, 10:00–19:00).'
    );
    expect($outra['primeiro_turno'] ?? null)->toBe('13:00–22:00',
        'Cada linha mostra o turno da PRÓPRIA escala — um horário global repetiria o da `ordenada`.'
    );
    expect($casca)->toHaveKey('primeiro_turno');
    expect($casca['primeiro_turno'])->toBeNull(
        'Escala sem turno informa null — a tela escreve "sem turno configurado".'
    );
});

it('UC-ESCIDX-07 · GET /ponto/escalas/{id} não promete tela que não existe — 405, nunca 500', function () {
    // Transação revertida: `ensurePontoPermissions` atribui a role `Admin#{biz}` ao usuário do
    // tenant 98, e no UltimatePOS essa role libera TODA permissão (AuthServiceProvider).
    // Persistida, ela vaza para quem roda depois com o mesmo usuário — o FechamentoContratoTest
    // reprovou UC-PTF-06/07 por isso na lane do PR #8143 (2026-09-29).
    DB::beginTransaction();
    try {
        // Tenant fictício 98 (ADR 0358), não o `actAsAdmin()` dos irmãos: este caso grava escala,
        // e no CT 100 o `Business::first()` é a WR2 real (clone de prod).
        (function (): void {
            $this->business = $this->seededTenant();
            $this->admin = \App\User::where('business_id', $this->business->id)->first();
            if (! $this->admin) {
                $this->markTestSkipped("Tenant {$this->business->id} sem usuário.");
            }
            $this->ensurePontoPermissions((int) $this->business->id);
            session([
                'user.business_id' => $this->business->id,
                'user.id'          => $this->admin->id,
                'business.id'      => $this->business->id,
                'business.name'    => $this->business->name,
                'is_admin'         => true,
            ]);
            $this->actingAs($this->admin);
        })->call($this);
        escIdxPrecisaDe(['ponto_escalas']);

        // Escala REAL do próprio empregador: o id existe, então a recusa não é "registro não
        // achado". A URI segue viva para PUT/DELETE (update/destroy), logo GET nela é 405 —
        // "método não permitido", a resposta HTTP correta. Antes o resource registrava `show`
        // sem método no controller, e esse GET dava 500.
        $id = escIdxCriarEscala((int) $this->business->id, 'sem-show');

        $this->get("/ponto/escalas/{$id}")->assertStatus(405);

        // Controle: a mesma escala segue editável — o `except` tirou só o show.
        $this->get("/ponto/escalas/{$id}/edit")->assertStatus(200);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
});
