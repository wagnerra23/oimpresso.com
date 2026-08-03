<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Escala;
use Modules\Ponto\Entities\EscalaTurno;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do formulário de escala (`/ponto/escalas/{create,{id}/edit}`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Escalas/Form.casos.md → UC-ESCF-01..03
 *
 * ── De onde vieram os UC ───────────────────────────────────────────────────
 * O SDD do módulo NÃO tem `CU-PONTO-*` para Escalas. O que existe é o §10 Onda 1,
 * que manda "varrer as 4 famílias de tela não auditadas (Dashboard, Colaboradores,
 * Escalas, Configuracoes) atrás de outros atributos fantasma" e declara "não fiz
 * nesta corrida". Esta é aquela varredura — os UC-01 e UC-02 saem de defeito
 * MEDIDO (recibo no casos.md), não de leitura do `.tsx` (que seria tautológico,
 * proibicoes.md §5 2026-06-05). O UC-03 ancora em CU-PONTO-12 (§6.5).
 *
 * ⚠️ DOIS UC nascem FAILING-FIRST por desenho, denunciando regressão medida:
 *   UC-ESCF-01 → controller lê `entrada`/`saida`/`almoco_inicio`/`almoco_fim`;
 *                   as colunas são `hora_*` e não há accessor → tela sempre vazia.
 *                   3ª instância do padrão D-1/D-8 do SDD §9.
 *   UC-ESCF-02 → `update(Request $request)` chama `$request->validated()`, que
 *                   só existe em FormRequest → BadMethodCallException ao salvar.
 * Vermelho neles é o ACHADO, não defeito do teste. Correção é decisão [W].
 *
 * ── Como os asserts são escritos ───────────────────────────────────────────
 * Sobre COMPORTAMENTO ("o horário gravado aparece", "a alteração fica gravada"),
 * nunca sobre a chave literal do payload nem sobre um status HTTP específico —
 * há mais de uma correção legítima para cada achado, e assert por chave/status
 * reprovaria as demais arbitrariamente. Mesma disciplina do CU-PONTO-02 no SDD.
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101). Sem
 * RefreshDatabase: a lane ponto-pest proíbe (dropa schema + limpa seed biz=1).
 *
 * Contrato: resources/js/Pages/Ponto/Escalas/Form.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\EscalaController::edit
 * @see \Modules\Ponto\Http\Controllers\EscalaController::update
 */

const ESCF_MARCADOR = 'SDD-ESCFORM-CONTRATO';
const ESCF_BIZ_ALHEIO = 99;
const ESCF_BIZ_NOME = 'ESCFORM Test Biz Adversario#99';

function escfPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Stub do business fictício 99 — sem ele o INSERT morre na FK e o caso não exerce
 * isolamento nenhum (medido na run 30778424885). Padrão do Wave27CrossTenantEscalaTest.
 */
function escfGarantirBizAlheio(): void
{
    if (\App\Business::find(ESCF_BIZ_ALHEIO)) {
        return;
    }

    \App\Business::forceCreate([
        'id'                              => ESCF_BIZ_ALHEIO,
        'name'                            => ESCF_BIZ_NOME,
        'currency_id'                     => 1,
        'start_date'                      => now()->toDateString(),
        'default_profit_percent'          => 0,
        'owner_id'                        => 1,
        'stop_selling_before'             => 0,
        'weighing_scale_setting'          => '',
        'certificado'                     => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);
}

function escfCriarEscala(int $businessId): Escala
{
    $escala = new Escala();
    $escala->forceFill([
        'business_id'           => $businessId,
        'nome'                  => ESCF_MARCADOR . '-' . uniqid(),
        'codigo'                => 'ESCF',
        'tipo'                  => 'FIXA',
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2400,
        'permite_banco_horas'   => true,
    ])->save();

    return $escala;
}

afterEach(function () {
    try {
        $ids = Escala::withoutGlobalScopes()
            ->where('nome', 'like', ESCF_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('ponto_escala_turnos')->whereIn('escala_id', $ids)->delete();
            Escala::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        // Só o stub deste arquivo (filtro por nome próprio) — o Wave27 usa o mesmo id.
        \App\Business::where('id', ESCF_BIZ_ALHEIO)
            ->where('name', ESCF_BIZ_NOME)
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

// =====================================================================
// Escalas/Form
// =====================================================================

it('UC-ESCF-01 · os horários dos turnos configurados aparecem na edição', function () {
    $this->actAsAdmin();
    escfPrecisaDe(['ponto_escalas', 'ponto_escala_turnos']);

    $escala = escfCriarEscala($this->business->id);

    $turno = new EscalaTurno();
    $turno->forceFill([
        'escala_id'          => $escala->id,
        'dia_semana'         => 1,
        'hora_entrada'       => '08:00:00',
        'hora_almoco_inicio' => '12:00:00',
        'hora_almoco_fim'    => '13:00:00',
        'hora_saida'         => '17:00:00',
    ])->save();

    // Pré-condição anti-vácuo: o turno TEM de estar gravado com os horários, senão
    // "a tela não mostra" seria verdade por não haver o que mostrar.
    $gravado = DB::table('ponto_escala_turnos')->where('id', $turno->id)->first();
    expect($gravado->hora_entrada)->not->toBeNull(
        'O turno precisa nascer com hora_entrada gravada — senão o caso não exerce nada.'
    );

    $resp = $this->get("/ponto/escalas/{$escala->id}/edit");
    $resp->assertStatus(200);

    $turnos = collect($resp->json('props.escala.turnos') ?? []);
    expect($turnos)->not->toBeEmpty('A edição precisa listar o turno cadastrado.');

    // Comportamento, não chave: os horários gravados têm de CHEGAR à tela, de
    // qualquer forma que a correção escolha expor.
    $serializado = json_encode($turnos->all());

    expect($serializado)->toContain('08:00',
        'O horário de entrada gravado (08:00) tem de aparecer na edição. O controller lê '
        . '`$t->entrada`, mas a coluna é `hora_entrada` e não há accessor — SDD §9, 3ª '
        . 'instância do padrão D-1/D-8.'
    );
    expect($serializado)->toContain('17:00',
        'O horário de saída gravado (17:00) tem de aparecer na edição.'
    );
    expect($serializado)->toContain('12:00',
        'O início do almoço gravado (12:00) tem de aparecer na edição.'
    );
});

it('UC-ESCF-02 · salvar a edição da escala persiste os campos', function () {
    $this->actAsAdmin();
    escfPrecisaDe(['ponto_escalas']);

    $escala = escfCriarEscala($this->business->id);
    $nomeNovo = ESCF_MARCADOR . '-editada-' . uniqid();

    $this->put("/ponto/escalas/{$escala->id}", [
        'nome'                  => $nomeNovo,
        'codigo'                => 'ESCF2',
        'tipo'                  => 'FIXA',
        'carga_diaria_minutos'  => 420,
        'carga_semanal_minutos' => 2100,
        'permite_banco_horas'   => false,
    ]);

    // Lê o ESTADO PERSISTIDO, não o status HTTP: o assert continua válido qualquer
    // que seja a correção (trocar por FormRequest, usar $request->validate(), etc).
    $atual = DB::table('ponto_escalas')->where('id', $escala->id)->first();

    expect($atual)->not->toBeNull('A escala precisa continuar existindo após o PUT.');
    expect($atual->nome)->toBe($nomeNovo,
        'Editar a escala tem de gravar o nome novo. Hoje `EscalaController@update` recebe '
        . 'Illuminate\Http\Request e chama $request->validated(), método que só existe em '
        . 'FormRequest (medido: 0 ocorrências em Http/Request.php, 0 macros no projeto) — '
        . 'a chamada lança BadMethodCallException e nada é salvo.'
    );
    expect((int) $atual->carga_diaria_minutos)->toBe(420,
        'A carga diária editada tem de ser persistida.'
    );
});

it('UC-ESCF-03 · escala de outro empregador dá 404', function () {
    $this->actAsAdmin();
    escfPrecisaDe(['ponto_escalas']);
    escfGarantirBizAlheio();

    $minha  = escfCriarEscala($this->business->id);
    $alheia = escfCriarEscala(ESCF_BIZ_ALHEIO);

    // Controle positivo: a minha abre. Sem isto, "a alheia dá 404" passaria com a
    // rota quebrada devolvendo 404 pra tudo.
    $this->get("/ponto/escalas/{$minha->id}/edit")->assertStatus(200);

    // Nunca 200 com dado alheio, nunca 500.
    $this->get("/ponto/escalas/{$alheia->id}/edit")->assertStatus(404);
});
