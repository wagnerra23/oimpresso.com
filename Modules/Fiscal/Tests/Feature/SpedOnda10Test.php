<?php

declare(strict_types=1);

use Modules\Fiscal\Http\Controllers\SpedController;
use Modules\Fiscal\Services\SpedReferenciaArquivoService;

uses(Tests\TestCase::class);

/**
 * SpedOnda10Test — os Goals do charter do Cowork que a Onda 9 não entregou.
 *
 * Origem do contrato: `cowork-inbox/fiscal/Sped.charter.md` no projeto Cowork,
 * lido por ID em 2026-09-04 (o espelho local não servia — `--sla` mediu 1 de 258).
 * O charter tem 5 Goals; a Onda 9 fechou 1. Aqui entram:
 *
 *   Goal 1 (completar) → o motivo do mês em aberto passa a citar a DATA em que a
 *                        competência encerra (`UC-FSF1-01`).
 *   Goal 5            → quais registros cada bloco contém (`UC-FSF1-06`).
 *   Goal 4            → o que foi validado fora daqui (`UC-FSF1-07`).
 *
 * ⚠️ POR QUE NENHUM CASO AQUI É SOURCE-GREP. O `Sped.casos.md` §Backlog explica:
 * `expect($fonte)->toContain(...)` quebra num rename e passa com a lógica errada.
 * Os casos abaixo INVOCAM o serviço e o método da régua e olham o resultado. O que
 * Pest não alcança — se a barra está na PÁGINA ou dentro do drawer — não é fingido
 * aqui: fica declarado no `Sped.casos.md` como conferido por smoke visual.
 *
 * Onde rodar: ver `Sped.casos.md` §"Como rodar a suíte" (dono da receita).
 * Só o último caso toca banco, e ele skipa sozinho onde a tabela não existe.
 */

/** A régua do Controller, para uma competência dada. */
function regua10(\Carbon\Carbon $inicioMes, bool $travaLigada = false, bool $ehSuperadmin = false): array
{
    $metodo = new ReflectionMethod(SpedController::class, 'checagens');
    $metodo->setAccessible(true);

    return $metodo->invoke(new SpedController(), $inicioMes, $travaLigada, $ehSuperadmin);
}

/* ── UC-FSF1-01 · o bloqueio diz QUANDO deixa de bloquear ─────────────────── */

it('UC-FSF1-01 · a competência em aberto informa a data em que encerra', function () {
    $corrente = now()->startOfMonth();
    $encerra = $corrente->copy()->endOfMonth()->format('d/m/Y');

    $regua = collect(regua10($corrente))->keyBy('id');

    expect($regua['fechada']['ok'])->toBeFalse()
        // A data é o delta desta onda: até aqui o motivo dizia só "o mês ainda não
        // terminou", que informa o QUE falta mas não QUANDO deixa de faltar.
        ->and($regua['fechada']['motivo'])->toContain($encerra)
        // E o que a Onda 9 já garantia continua valendo — a norma citada.
        ->and($regua['fechada']['motivo'])->toContain('0000');
});

it('UC-FSF1-01 · a competência encerrada também diz em que data encerrou', function () {
    $anterior = now()->startOfMonth()->subMonth();
    $encerrou = $anterior->copy()->endOfMonth()->format('d/m/Y');

    $regua = collect(regua10($anterior))->keyBy('id');

    expect($regua['fechada']['ok'])->toBeTrue()
        ->and($regua['fechada']['motivo'])->toContain($encerrou);
});

it('UC-FSF1-01 · a data citada é o ENCERRAMENTO, não o prazo de entrega', function () {
    // O protótipo do Cowork cita ali o campo `entrega` (dia 15 do mês seguinte).
    // São coisas diferentes: 09/2026 encerra em 30/09 e a entrega é 15/10. Quem
    // lesse "fecha em 15/10" esperaria duas semanas a mais do que precisa.
    $corrente = now()->startOfMonth();
    $prazoEntrega = $corrente->copy()->addMonth()->day(15)->format('d/m/Y');

    $motivo = collect(regua10($corrente))->keyBy('id')['fechada']['motivo'];

    expect($motivo)->toContain($corrente->copy()->endOfMonth()->format('d/m/Y'))
        ->and($motivo)->not->toContain($prazoEntrega);
});

/* ── UC-FSF1-06 · os blocos do arquivo, medidos e não escritos ────────────── */

it('UC-FSF1-06 · a estrutura por bloco é medida no arquivo de referência', function () {
    $referencia = app(SpedReferenciaArquivoService::class)->referencia();

    expect($referencia['disponivel'])->toBeTrue()
        ->and($referencia['bytes'])->toBeGreaterThan(0)
        ->and($referencia['linhas'])->toBeGreaterThan(0)
        ->and($referencia['sha256'])->toHaveLength(64);

    // Os 5 blocos do perfil A, na ordem em que o layout os emite.
    expect(array_column($referencia['blocos'], 'id'))->toBe(['0', 'C', 'E', 'H', '9']);

    // E cada bloco traz os registros REAIS do arquivo, não uma lista decorativa.
    $porBloco = collect($referencia['blocos'])->keyBy('id');

    expect($porBloco['0']['registros'])->toContain('0000')
        ->and($porBloco['C']['registros'])->toContain('C100')
        ->and($porBloco['E']['registros'])->toContain('E110')
        ->and($porBloco['H']['registros'])->toContain('H001')
        ->and($porBloco['9']['registros'])->toContain('9999');
});

it('UC-FSF1-06 · a soma das linhas por bloco é a contagem real do arquivo', function () {
    // Se a agregação perdesse ou duplicasse linha, os dois números divergiriam —
    // e a tela mostraria uma estrutura que o arquivo não tem.
    $referencia = app(SpedReferenciaArquivoService::class)->referencia();

    expect(array_sum(array_column($referencia['blocos'], 'linhas')))
        ->toBe($referencia['linhas']);
});

it('UC-FSF1-06 · registro repetido conta linha, mas aparece uma vez na lista', function () {
    // O golden tem 22 linhas `9900` (uma por registro contado) e 2 linhas `0150`.
    // A tela precisa dos dois números: quantas linhas o bloco tem, e QUAIS
    // registros ele contém — sem repetir o mesmo nome 22 vezes.
    $porBloco = collect(app(SpedReferenciaArquivoService::class)->referencia()['blocos'])->keyBy('id');

    expect($porBloco['9']['linhas'])->toBeGreaterThan(count($porBloco['9']['registros']))
        ->and($porBloco['9']['registros'])->toBe(array_values(array_unique($porBloco['9']['registros'])));
});

it('UC-FSF1-06 · sem o arquivo, a estrutura é declarada ausente — nunca presumida', function () {
    // BITE-TEST: some a fonte, some a resposta. Um serviço que devolvesse os blocos
    // canônicos de memória passaria no caso acima e MENTIRIA aqui.
    $vazio = app(SpedReferenciaArquivoService::class)
        ->referenciaDe(base_path('Modules/Fiscal/Tests/Fixtures/nao-existe.txt'), 'origem/declarada.txt');

    expect($vazio['disponivel'])->toBeFalse()
        ->and($vazio['blocos'])->toBe([])
        ->and($vazio['bytes'])->toBeNull()
        ->and($vazio['sha256'])->toBeNull()
        // A origem continua dita, pra tela poder nomear o que faltou.
        ->and($vazio['origem'])->toBe('origem/declarada.txt');
});

/* ── UC-FSF1-07 · a tela não finge validação que não houve ────────────────── */

it('UC-FSF1-07 · o golden EXISTE, e a tela lê isso do disco em vez de afirmar', function () {
    // O charter do Cowork é de 2026-08-24 e diz "Golden file do TXT: não existe".
    // O golden nasceu em 2026-09-03 (PR #6708). Copiar aquela copy teria posto uma
    // afirmação falsa na tela — este caso é o que impede a cópia voltar.
    $validacao = app(SpedReferenciaArquivoService::class)->validacaoExterna();

    expect($validacao['golden']['presente'])->toBeTrue()
        ->and($validacao['golden']['bytes'])->toBeGreaterThan(0)
        ->and($validacao['golden']['origem'])->toBe(SpedReferenciaArquivoService::CAMINHO_GOLDEN);
});

it('UC-FSF1-07 · "nunca executado" do PVA-EFD vem da AUSÊNCIA do recibo', function () {
    $servico = app(SpedReferenciaArquivoService::class);

    // Hoje: sem recibo no repo → nunca executado.
    expect($servico->validacaoExterna()['pvaSmoke']['executado'])->toBeFalse();

    // BITE-TEST: apontando para um arquivo que EXISTE, vira executado. Um texto
    // fixo "nunca executado" passaria no primeiro assert e falharia neste.
    $comRecibo = $servico->validacaoExternaDe(
        $servico->referencia(),
        base_path(SpedReferenciaArquivoService::CAMINHO_GOLDEN),
        'recibo/simulado.md',
    );

    expect($comRecibo['pvaSmoke']['executado'])->toBeTrue()
        ->and($comRecibo['pvaSmoke']['origem'])->toBe('recibo/simulado.md');
});

it('UC-FSF1-07 · "apuração do ICMS no arquivo" é medido pelo Bloco E', function () {
    $servico = app(SpedReferenciaArquivoService::class);

    expect($servico->validacaoExterna()['apuracaoIcms']['noArquivo'])->toBeTrue();

    // BITE-TEST: sem arquivo, não há bloco E — e a afirmação cai junto.
    $semArquivo = $servico->validacaoExternaDe(
        $servico->referenciaDe(base_path('Modules/Fiscal/Tests/Fixtures/nao-existe.txt'), 'x'),
        base_path('Modules/Fiscal/Tests/Fixtures/nao-existe-tambem.md'),
        'y',
    );

    expect($semArquivo['apuracaoIcms']['noArquivo'])->toBeFalse()
        ->and($semArquivo['golden']['presente'])->toBeFalse();
});

/* ── As props chegam à tela (o payload, não o pixel) ──────────────────────── */

it('UC-FSF1-06 · UC-FSF1-07 · o Controller entrega referência e validação à tela', function () {
    // ⚠️ Este caso precisa da tabela `nfe_emissoes` (o painel agrega notas). Ele
    // SKIPA na lane SQLite do CI e RODA no CT 100, que provisionou o schema em
    // 2026-07-28. Skip sai com exit 0 e não prova nada (LC-13) — por isso os UCs
    // acima, que rodam em toda lane, não dependem deste.
    if (! \Illuminate\Support\Facades\Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('NfeBrasil ausente nesta lane — ver Sped.casos.md §recibo');
    }

    $user = \App\User::factory()->create(['business_id' => 98]);
    $user->givePermissionTo('fiscal.sped.export');
    $this->actingAs($user);
    session(['business.id' => 98, 'user.business_id' => 98]);

    $this->get('/fiscal/sped')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Fiscal/Sped')
            ->where('referenciaArquivo.disponivel', true)
            ->where('validacaoExterna.golden.presente', true)
            ->where('validacaoExterna.pvaSmoke.executado', false)
            ->has('referenciaArquivo.blocos', 5));
});
