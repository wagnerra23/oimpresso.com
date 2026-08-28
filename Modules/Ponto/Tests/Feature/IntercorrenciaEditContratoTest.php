<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato da edição de rascunho (`/ponto/intercorrencias/{id}/edit`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Intercorrencias/Edit.casos.md → UC-INTEDIT-01..03
 *
 * Os UC derivam do SDD §6.2 `CU-PONTO-05` ("só rascunho é editável"), do §6.5
 * `CU-PONTO-12` (isolamento) e da paridade com o `_form.blade.php` legado.
 * NÃO do `.tsx`.
 *
 * ── Por que este arquivo existe ────────────────────────────────────────────
 * Esta tela era a ÚLTIMA Blade viva do módulo (SDD §5.4 item 1: dos 21 renders
 * dos controllers do Ponto, 20 Inertia + 1 Blade). Migrada em 2026-08-28 por
 * decisão [W]. Tela nova nasce com o trio; estes são os casos dela.
 *
 * Nomes próprios (`INTEDIT_*`, `intEdit*`): const e function em arquivo Pest são
 * GLOBAIS no processo, e o `IntercorrenciaContratoTest` do lado já usa `INTC_*`.
 *
 * Tier 0: o adversário é o biz fictício 99 — NUNCA biz=4 (ROTA LIVRE).
 *
 * Contrato: resources/js/Pages/Ponto/Intercorrencias/Edit.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\IntercorrenciaController::edit()
 */

const INTEDIT_MARCADOR = 'SDD-INTEDIT-CONTRATO';
const INTEDIT_BIZ_ALHEIO = 99;
const INTEDIT_BIZ_NOME = 'INTEDIT Test Biz Adversario#99';

function intEditPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/** Stub do biz fictício — sem ele o INSERT morre na FK. */
function intEditGarantirBizAlheio(): void
{
    if (\App\Business::find(INTEDIT_BIZ_ALHEIO)) {
        return;
    }

    \App\Business::forceCreate([
        'id'                              => INTEDIT_BIZ_ALHEIO,
        'name'                            => INTEDIT_BIZ_NOME,
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

/**
 * GET com cabeçalho Inertia — sem isto o `->json('props...')` estoura, porque o
 * Inertia só responde JSON quando o request se declara Inertia.
 */
function intEditInertiaGet(string $url)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get($url);
}

function intEditCriarColaborador(int $businessId, int $userBusinessId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $userBusinessId,
        'user_type'   => 'user',
    ]);

    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => $user->id,
        'matricula'      => INTEDIT_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao'       => '2019-01-01',
    ])->save();

    return $colab;
}

/**
 * Rascunho com valores CONHECIDOS — é o que o UC-INTEDIT-02 confere no payload.
 * Justificativa neutra de propósito: nada de PII em fixture (LGPD).
 */
function intEditCriarRascunho(int $businessId, int $colaboradorId, int $solicitanteId, string $estado): Intercorrencia
{
    $i = new Intercorrencia();
    $i->forceFill([
        'business_id'           => $businessId,
        'colaborador_config_id' => $colaboradorId,
        'codigo'                => INTEDIT_MARCADOR . '-' . strtoupper(substr(uniqid(), -8)),
        'tipo'                  => 'ATESTADO_MEDICO',
        'data'                  => '2019-03-11',
        'dia_todo'              => true,
        'justificativa'         => 'Fixture de contrato SDD — texto neutro, sem PII.',
        'estado'                => $estado,
        'prioridade'            => 'URGENTE',
        'solicitante_id'        => $solicitanteId,
    ])->save();

    return $i;
}

afterEach(function () {
    try {
        DB::table('ponto_intercorrencias')
            ->where('codigo', 'like', INTEDIT_MARCADOR . '%')
            ->delete();

        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', INTEDIT_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('ponto_intercorrencias')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        \App\Business::where('id', INTEDIT_BIZ_ALHEIO)
            ->where('name', INTEDIT_BIZ_NOME)
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

it('UC-INTEDIT-01 · só rascunho é editável', function () {
    $this->actAsAdmin();
    intEditPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intEditCriarColaborador($this->business->id, $this->business->id);

    // PENDENTE = já submetida ao RH. O passado não se reescreve.
    $submetida = intEditCriarRascunho(
        $this->business->id,
        $colab->id,
        $this->admin->id,
        Intercorrencia::ESTADO_PENDENTE
    );

    $resp = $this->get("/ponto/intercorrencias/{$submetida->id}/edit");

    // 403 e não 404: o registro EXISTE e é do meu tenant — esconder mandaria o
    // operador caçar um bug que não existe. O que ele não pode é reescrever.
    expect($resp->status())->toBe(403,
        'Intercorrência em ' . Intercorrencia::ESTADO_PENDENTE . ' não pode abrir a edição. '
        . 'O ciclo RASCUNHO -> PENDENTE -> APROVADA só significa algo se o passado parar de '
        . 'ser reescrevível: o RH aprovaria um texto e o registro guardaria outro (CU-PONTO-05).'
    );
});

it('UC-INTEDIT-02 · o form abre com os valores atuais do rascunho', function () {
    $this->actAsAdmin();
    intEditPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intEditCriarColaborador($this->business->id, $this->business->id);
    $rascunho = intEditCriarRascunho(
        $this->business->id,
        $colab->id,
        $this->admin->id,
        Intercorrencia::ESTADO_RASCUNHO
    );

    $resp = intEditInertiaGet("/ponto/intercorrencias/{$rascunho->id}/edit");
    $resp->assertStatus(200);

    $payload = $resp->json('props.intercorrencia');

    // Anti-vácuo (LC-13): payload nulo faria todo assert abaixo passar por ausência.
    expect($payload)->not->toBeNull('A edição precisa entregar a intercorrência ao form.');

    // O ponto do caso: os VALORES chegam, não só as chaves. Chave presente com null
    // é exatamente o defeito perseguido — o módulo já sofreu isso 2x (US-PONTO-012:
    // `entrada`/`saida` da escala e `linhas_criadas` da importação, ambos lidos de
    // atributo inexistente, ambos exibindo vazio em silêncio).
    expect($payload['tipo'] ?? null)->toBe('ATESTADO_MEDICO',
        'O tipo gravado tem de voltar preenchido — form vazio faz o operador "corrigir um '
        . 'campo" e salvar um registro esvaziado por cima do rascunho dele.'
    );
    expect($payload['prioridade'] ?? null)->toBe('URGENTE', 'A prioridade gravada tem de voltar.');
    expect($payload['data'] ?? null)->toBe('2019-03-11', 'A data gravada tem de voltar.');
    expect($payload['justificativa'] ?? null)->not->toBeEmpty('A justificativa gravada tem de voltar.');
    expect((int) ($payload['colaborador_config_id'] ?? 0))->toBe((int) $colab->id,
        'O colaborador vinculado tem de voltar selecionado.'
    );

    // O form precisa das opções pra montar os selects — sem elas o operador vê
    // campos travados e não consegue corrigir nada.
    expect($resp->json('props.tipos'))->not->toBeEmpty('O form precisa da lista de tipos.');
});

it('UC-INTEDIT-03 · rascunho de outro empregador não abre', function () {
    $this->actAsAdmin();
    intEditPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);
    intEditGarantirBizAlheio();

    $colabAlheio = intEditCriarColaborador(INTEDIT_BIZ_ALHEIO, INTEDIT_BIZ_ALHEIO);
    $alheia = intEditCriarRascunho(
        INTEDIT_BIZ_ALHEIO,
        $colabAlheio->id,
        $this->admin->id,
        Intercorrencia::ESTADO_RASCUNHO
    );

    $resp = intEditInertiaGet("/ponto/intercorrencias/{$alheia->id}/edit");

    // Não crava o status: com o global scope ativo o registro não existe pra este
    // tenant e o findOrFail responde 404; se um dia virar abort(403) explícito a
    // proteção continua correta e o caso não pode reprovar por isso. O que NUNCA
    // pode é devolver o formulário com os dados alheios.
    expect($resp->isSuccessful())->toBeFalse(
        'Rascunho de OUTRO empregador não pode abrir a edição — ADR 0093, CU-PONTO-12.'
    );

    expect((string) $resp->getContent())->not->toContain($alheia->codigo,
        'Nem o código da intercorrência alheia pode vazar na resposta.'
    );
});
