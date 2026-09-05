<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato das duas telas de colaborador do Ponto:
 *   - `/ponto/colaboradores`               → Colaboradores/Index.casos.md  (UC-COLIDX-01..02)
 *   - `/ponto/colaboradores/{id}/editar`   → Colaboradores/Edit.casos.md   (UC-COLEDT-01..02)
 *
 * Cada teste cita o UC no TÍTULO do `it()` — não em docblock. É o que o manifesto G-7 alcança
 * (`casos-results-collect` lê o `name` do `<testcase>`; método PHP não aceita hífen no nome).
 *
 * Os UC derivam do SDD §6.5 (`CU-PONTO-12`) + US-PONTO-007 + ADR 0093 + charter. NÃO do `.tsx`.
 *
 * ── Por que o adversário é o biz 99, e por que ele é criado aqui ───────────────────────
 * `PontoTestCase::garantirBizAlheio()` é o idioma único do módulo pro empregador fictício.
 * NUNCA biz=4 (ROTA LIVRE, cliente real — proibido sem exceção pela ADR 0358). Sem o stub, o
 * INSERT morre na FK e o caso morre no fixture, sem nunca chegar à asserção de isolamento.
 *
 * ── Nota de fixture que evita um achado falso ─────────────────────────────────────────
 * `ponto_colaborador_config` tem SoftDeletes. Escolher "um colaborador do meu business" via
 * `DB::table` (que ignora `deleted_at`) e bater na rota devolve 404 por estar APAGADO, não por
 * isolamento. Os casos que precisam de um registro meu filtram `whereNull('deleted_at')`.
 *
 * Tier 0: sem `RefreshDatabase` — a lane ponto-pest proíbe (dropar o schema limpa o seed).
 *
 * @see \Modules\Ponto\Http\Controllers\ColaboradorController
 */

const COL_MARCA = 'SDD-COL-CONTRATO';

function colPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Cria um colaborador COMPLETO (user + config) no empregador informado.
 *
 * Devolve também a matrícula e o CPF gerados, porque os asserts de isolamento procuram por
 * essas strings — e elas precisam ser exatamente as gravadas.
 *
 * ⚠️ Larguras do schema (`create_ponto_colaborador_config_table`): `matricula` é varchar(30)
 * e `cpf` é varchar(14). O marcador inteiro não cabe no CPF (16 chars só do prefixo), então o
 * CPF sintético é derivado por hash e tem 14 caracteres exatos. Estourar a coluna faria o
 * INSERT truncar (ou falhar) e o caso mediria uma string que não existe no banco.
 */
function colCriarColaborador(int $businessId, string $sufixo): array
{
    $matricula = COL_MARCA . '-M-' . $sufixo;        // <= 30
    $cpf       = 'CT' . substr(md5($sufixo), 0, 12); // == 14


    $userId = DB::table('users')->insertGetId([
        'user_type'   => 'user',
        'surname'     => 'Sr',
        'first_name'  => 'Contrato',
        'last_name'   => 'Colaborador',
        'username'    => strtolower(COL_MARCA) . '-' . $sufixo . '-' . uniqid(),
        'email'       => strtolower(COL_MARCA) . '-' . $sufixo . '-' . uniqid() . '@contrato.test',
        'password'    => bcrypt('irrelevante'),
        'language'    => 'pt_BR',
        'business_id' => $businessId,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $configId = DB::table('ponto_colaborador_config')->insertGetId([
        'business_id'     => $businessId,
        'user_id'         => $userId,
        'matricula'       => $matricula,
        'cpf'             => $cpf,
        'controla_ponto'  => 1,
        'usa_banco_horas' => 0,
        'admissao'        => '2020-01-01',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    return [
        'user_id'   => $userId,
        'config_id' => $configId,
        'matricula' => $matricula,
        'cpf'       => $cpf,
    ];
}

afterEach(function () {
    try {
        // Só o que ESTE arquivo criou (marcador na matrícula). Colaborador com marcação presa
        // por FK fica — marcação é append-only por lei (Portaria MTP 671/2021) e não se apaga
        // pra limpar teste.
        $ids = DB::table('ponto_colaborador_config')
            ->where('matricula', 'like', COL_MARCA . '%')
            ->whereNotIn('id', function ($sub) {
                $sub->select('colaborador_config_id')->from('ponto_marcacoes');
            })
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('ponto_colaborador_config')->whereIn('id', $ids)->delete();
        }

        DB::table('users')->where('username', 'like', strtolower(COL_MARCA) . '%')->delete();
    } catch (\Throwable $e) {
        // schema ausente ou FK residual — cleanup best-effort, igual aos irmãos do módulo.
    }
});

// =====================================================================
// Colaboradores/Index — a lista e a busca
// =====================================================================

it('UC-COLIDX-01 · buscar por matrícula ou CPF não alcança colaborador de outro empregador', function () {
    $this->actAsAdmin();
    colPrecisaDe(['ponto_colaborador_config']);

    $alheio = $this->garantirBizAlheio();
    $vitima = colCriarColaborador($alheio, 'alheio');

    $matriculaAlheia = $vitima['matricula'];
    $cpfAlheio       = $vitima['cpf'];

    // Pré-condição anti-vácuo: se a fixture não entrou, "não vazou" seria verdade por nada
    // existir do outro lado (LC-13 — verde por não-execução).
    expect(DB::table('ponto_colaborador_config')->where('id', $vitima['config_id'])->exists())
        ->toBeTrue('O colaborador do empregador adversário tem de existir — senão o caso não exerce isolamento.');

    // Busca pelo CPF do alheio, e procura a MATRÍCULA dele na resposta.
    //
    // O cruzamento é DELIBERADO: o controller devolve o termo buscado na prop `search`, então
    // procurar o termo que acabei de buscar casa por ECO e não prova nada. Foi exatamente o
    // falso-positivo que a sonda desta sessão produziu antes de ser corrigida.
    // ⚠️ `assertStringNotContainsString`, e NÃO `expect()->not->toContain($x, $msg)`: o
    // `toContain` do Pest recebe MÚLTIPLOS needles, então a mensagem viraria um 2º needle e o
    // `not` passaria sempre — assert vazio com cara de assert. É a classe já enterrada no §5 de
    // proibicoes.md (2026-07-28, 38 ocorrências no PR #4918), e ela reapareceu aqui: a 1ª versão
    // deste arquivo usava `toContain` e PASSOU no bite-test com a defesa removida.
    $porCpf = $this->inertiaGet('/ponto/colaboradores', ['q' => $cpfAlheio]);
    $porCpf->assertStatus(200);
    $this->assertStringNotContainsString(
        $matriculaAlheia,
        $porCpf->getContent(),
        'Buscar pelo CPF de colaborador de OUTRO empregador não pode trazer o cadastro dele '
        . '(CU-PONTO-12 · ADR 0093 · LGPD Art. 7º).'
    );

    // O caminho simétrico: busca pela matrícula, procura o CPF.
    $porMatricula = $this->inertiaGet('/ponto/colaboradores', ['q' => $matriculaAlheia]);
    $porMatricula->assertStatus(200);
    $this->assertStringNotContainsString(
        $cpfAlheio,
        $porMatricula->getContent(),
        'Buscar pela matrícula de colaborador de OUTRO empregador não pode trazer o cadastro dele. '
        . 'Atenção ao consertar: o filtro `where(business_id)` do controller NÃO defende esta '
        . 'consulta (ele fica do lado esquerdo de um OR); quem segura é o global scope do trait '
        . 'HasBusinessScope — defesa única, SDD §9 D-5.'
    );

    $this->removerBizAlheio();
});

it('UC-COLIDX-02 · busca que não casa ninguém devolve lista vazia, não a lista inteira', function () {
    $this->actAsAdmin();
    colPrecisaDe(['ponto_colaborador_config']);

    colCriarColaborador((int) $this->business->id, 'meu');

    $semBusca = $this->inertiaGet('/ponto/colaboradores');
    $semBusca->assertStatus(200);
    $totalSemBusca = (int) $semBusca->json('props.colaboradores.total');

    // Pré-condição anti-vácuo: sem ninguém na lista, "a busca filtrou" seria verdade por vácuo.
    expect($totalSemBusca)->toBeGreaterThan(0,
        'A lista sem busca tem de trazer ao menos um colaborador — senão o caso não compara nada.'
    );

    $termoImpossivel = COL_MARCA . '-NINGUEM-CASA-COM-ISTO';
    $comBusca = $this->inertiaGet('/ponto/colaboradores', ['q' => $termoImpossivel]);
    $comBusca->assertStatus(200);
    $totalComBusca = (int) $comBusca->json('props.colaboradores.total');

    expect($totalComBusca)->toBe(0,
        'Busca que não casa matrícula, nome nem CPF de ninguém tem de devolver lista VAZIA. '
        . 'Se voltar a lista inteira, a busca virou decoração e o operador conclui "está '
        . 'cadastrado" olhando o primeiro nome que aparecer (charter §Goals: os dois empty '
        . 'states só existem porque a busca de fato filtra).'
    );
});

// =====================================================================
// Colaboradores/Edit — a configuração
// =====================================================================

it('UC-COLEDT-01 · abrir a configuração de colaborador de outro empregador devolve 404', function () {
    $this->actAsAdmin();
    colPrecisaDe(['ponto_colaborador_config']);

    $alheio = $this->garantirBizAlheio();
    $vitima = colCriarColaborador($alheio, 'edit');

    expect(DB::table('ponto_colaborador_config')->where('id', $vitima['config_id'])->exists())
        ->toBeTrue('O cadastro adversário tem de existir — 404 contra id inexistente não prova isolamento.');

    $resp = $this->inertiaGet('/ponto/colaboradores/' . $vitima['config_id'] . '/editar');

    // 404 e não 403: 403 confirmaria que o id existe (CU-PONTO-12 fixa 404).
    $resp->assertStatus(404);

    $this->removerBizAlheio();
});

it('UC-COLEDT-02 · desligamento anterior à admissão é recusado', function () {
    $this->actAsAdmin();
    colPrecisaDe(['ponto_colaborador_config']);

    // Precisa ser um colaborador VIVO: o model aplica SoftDeletes, e um registro apagado
    // devolveria 404 por outro motivo — o caso mediria a coisa errada.
    $meu = colCriarColaborador((int) $this->business->id, 'datas');

    $resp = $this->put('/ponto/colaboradores/' . $meu['config_id'], [
        'admissao'       => '2024-06-01',
        'desligamento'   => '2024-01-01',
        'controla_ponto' => true,
    ]);

    // "Não é sucesso" em vez de um status cravado: trocar `back()->withErrors` por outro
    // mecanismo de recusa é correção legítima e não deve reprovar aqui.
    expect($resp->isSuccessful())->toBeFalse(
        'Salvar um vínculo que termina ANTES de começar tem de ser recusado — admissão e '
        . 'desligamento delimitam a janela de apuração da jornada.'
    );

    // A segunda metade importa: só "não foi sucesso" passaria também num 500.
    $erros = session('errors');
    expect($erros)->not->toBeNull('A recusa tem de chegar ao operador como erro de formulário.');
    expect($erros->has('desligamento'))->toBeTrue(
        'O erro tem de apontar o campo de desligamento — sem isso o operador não sabe o que corrigir.'
    );

    // E o cadastro não pode ter sido alterado pela tentativa recusada.
    $depois = DB::table('ponto_colaborador_config')->where('id', $meu['config_id'])->first();
    expect($depois->desligamento)->toBeNull(
        'A tentativa recusada não pode ter gravado o desligamento inválido.'
    );
});
