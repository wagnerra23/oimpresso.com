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
