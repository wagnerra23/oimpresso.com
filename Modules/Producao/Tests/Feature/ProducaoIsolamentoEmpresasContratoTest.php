<?php
// @covers-us US-PCP-004
// @covers-us US-PCP-017
// @covers-us US-PCP-023
// @covers-us US-PCP-027
// @covers-us US-PCP-029

declare(strict_types=1);
// Cobre UC-PCISO-01..06 (Apontamento, Postos, Painel e Quadro `*.casos.md`) — lote 5.1 da auditoria da Produção.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Operacao;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Entities\Romaneio;
use Modules\Producao\Entities\RomaneioLinha;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: ADR 0093 (isolamento por empresa) + o `Aceite` de cada UC-PCISO. Fecha as tabelas que a
 * auditoria de 2026-11-05 achou sem prova cross-tenant de leitura (P5, P6, P7, P10 do eixo 1).
 *
 * Cada caso é discriminante e tem controle. Tenant 98 contra o 99 (ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP em SQLite / sem tabelas: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('pcp_romaneio_linhas') || ! Schema::hasTable('pcp_refugos') || ! Schema::hasTable('comvis_os')) {
        $this->markTestSkipped('SQLite-incompatível ou tabelas pcp_*/comvis_* ausentes nesta lane.');
    }
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));

    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->b99 = (int) $this->B99->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);

    $this->g98 = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B98);
    $this->g98->assignRole('producao.liberar#'.$this->b98);
    $this->g99 = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B99);
});

/** Login novo: o SetSessionData só preenche a empresa quando a sessão está vazia. */
function pcisoComo($teste, $user)
{
    $teste->flushSession();

    return $teste->actingAs($user);
}

function pcisoProp($teste, $user, string $rota, string $componente, string $prop): array
{
    pcisoComo($teste, $user);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->get($rota, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop, 'X-Inertia-Partial-Component' => $componente]);
    $r->assertOk();

    return (array) $r->json('props.'.$prop);
}

function pcisoPosto(int $biz, string $nome, ?string $setor = null): int
{
    return (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'ISO-'.uniqid(), 'nome' => $nome, 'setor' => $setor,
        'tipo_custo' => 'maquina', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
}

function pcisoOsCv(int $biz): array
{
    $orc = (int) DB::table('comvis_orcamentos')->insertGetId(['business_id' => $biz, 'numero' => 'ORC-ISO-'.uniqid(),
        'data_emissao' => '2026-10-20', 'created_at' => now(), 'updated_at' => now()]);
    $os = (int) DB::table('comvis_os')->insertGetId(['business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'OS-I'.random_int(100000, 999999),
        'status_etapa' => 'producao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now()]);

    return [$os, $orc];
}

function pcisoPeca(int $biz, int $orc, string $descricao, int $ordem): int
{
    return (int) DB::table('comvis_orcamento_itens')->insertGetId(['business_id' => $biz, 'orcamento_id' => $orc, 'descricao' => $descricao,
        'largura_m' => '1.000', 'altura_m' => '1.000', 'quantidade' => 1, 'area_m2' => '1.000', 'ordem' => $ordem,
        'created_at' => now(), 'updated_at' => now()]);
}

function pcisoEtapaCv(int $biz, int $os, int $posto, string $nome, string $status): int
{
    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => 'comvis_os', 'os_id' => $os, 'posto_id' => $posto,
        'nome' => $nome, 'inicio_previsto' => '2026-11-03 09:00:00', 'fim_previsto' => '2026-11-03 10:00:00', 'status' => $status,
        'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

function pcisoOrdem(int $biz): array
{
    $ordem = (int) DB::table('pcp_ordens')->insertGetId(['business_id' => $biz, 'transaction_id' => random_int(800000, 899999),
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '10.0000',
        'created_at' => now(), 'updated_at' => now()]);
    $etapa = (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $ordem,
        'posto_id' => pcisoPosto($biz, 'Corte'), 'nome' => 'Corte', 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(),
        'status' => 'pendente', 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);

    return [$ordem, $etapa];
}

function pcisoRefugo(int $biz, int $ordem, int $etapa, int $user, string $quantidade): void
{
    DB::table('pcp_refugos')->insert(['business_id' => $biz, 'chave' => 'iso-'.uniqid(), 'ordem_id' => $ordem, 'programacao_id' => $etapa,
        'motivo_id' => 1, 'motivo' => 'Corte', 'quantidade' => $quantidade, 'user_id' => $user, 'momento' => now(),
        'created_at' => now(), 'updated_at' => now()]);
}

it('UC-PCISO-01 · linhas de romaneio de outra empresa não são lidas nem marcam peça como já enviada', function () {
    [$os, $orc] = pcisoOsCv($this->b98);
    pcisoPeca($this->b98, $orc, 'Banner', 1);
    $etapa = pcisoEtapaCv($this->b98, $os, pcisoPosto($this->b98, 'Plotter'), 'Impressão', 'pendente');
    $v1 = pcisoComo($this, $this->g98)->postJson("/producao/os/{$os}/romaneio", ['programacao_id' => $etapa])->assertCreated();
    $pecaNova = pcisoPeca($this->b98, $orc, 'Adesivo', 2);

    // Discriminante: a 99 tem romaneio v1 com a MESMA OS-id e uma linha apontando a peça nova da 98.
    [$os99] = pcisoOsCv($this->b99);
    // SUPERADMIN: fixture grava na outra empresa; o escopo é provado nos asserts.
    $rom99 = Romaneio::withoutGlobalScopes()->create(['business_id' => $this->b99, 'os_tipo' => 'comvis_os', 'os_id' => $os, 'versao' => 1,
        'numero' => 'ROM-DA-99-v1', 'programacao_id' => pcisoEtapaCv($this->b99, $os99, pcisoPosto($this->b99, 'Plotter'), 'Impressão', 'pendente'),
        'gerado_por' => $this->g99->id, 'gerado_em' => now()]);
    // SUPERADMIN: fixture grava na outra empresa; o escopo é provado nos asserts.
    RomaneioLinha::withoutGlobalScopes()->create(['business_id' => $this->b99, 'romaneio_id' => $rom99->id, 'orcamento_item_id' => $pecaNova,
        'ordem' => 1, 'descricao' => 'Peça da 99', 'quantidade' => 1]);

    $v2 = pcisoComo($this, $this->g98)->postJson("/producao/os/{$os}/romaneio", ['programacao_id' => $etapa])->assertCreated();
    expect($v2->json('versao'))->toBe(2)
        ->and($v2->json('linhas.1.descricao'))->toBe('Adesivo')
        ->and($v2->json('linhas.1.ja_enviada_em'))->toBeNull()
        // Controle: a marca existe para a peça que saiu na v1 da própria empresa.
        ->and($v2->json('linhas.0.ja_enviada_em'))->toBe($v1->json('numero'));

    $this->getJson("/producao/romaneios/{$rom99->id}")->assertNotFound();
    pcisoComo($this, $this->g99)->getJson("/producao/romaneios/{$rom99->id}")->assertOk()->assertJsonPath('linhas.0.descricao', 'Peça da 99');

    $this->actingAs($this->g98);
    session(['user.business_id' => $this->b98]);
    expect(RomaneioLinha::query()->where('romaneio_id', $rom99->id)->count())->toBe(0);
    $this->actingAs($this->g99);
    session(['user.business_id' => $this->b99]);
    expect(RomaneioLinha::query()->where('romaneio_id', $rom99->id)->count())->toBe(1);
});

it('UC-PCISO-02 · a outra empresa não vê nem altera a ferramenta', function () {
    $linha = fn (int $biz, string $nome) => (int) DB::table('pcp_ferramentas')->insertGetId(['business_id' => $biz, 'codigo_qr' => 'FER-'.uniqid(),
        'nome' => $nome, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $f98 = $linha($this->b98, 'Furadeira da 98');
    $f99 = $linha($this->b99, 'Furadeira da 99');
    $qr98 = DB::table('pcp_ferramentas')->where('id', $f98)->value('codigo_qr');
    $idsVistos = fn ($user) => collect(pcisoProp($this, $user, '/producao/postos', 'Producao/Postos/Index', 'ferramentas'))->pluck('id')->all();

    expect($idsVistos($this->g99))->toContain($f99)->not->toContain($f98)
        ->and($idsVistos($this->g98))->toContain($f98)->not->toContain($f99);

    pcisoComo($this, $this->g99)->put("/producao/ferramentas/{$f98}", ['codigo_qr' => 'INVADIDA', 'nome' => 'Invadida'])->assertNotFound();
    expect(DB::table('pcp_ferramentas')->where('id', $f98)->first(['nome', 'codigo_qr']))
        ->nome->toBe('Furadeira da 98')->codigo_qr->toBe($qr98);

    pcisoComo($this, $this->g98)->put("/producao/ferramentas/{$f98}", ['codigo_qr' => $qr98, 'nome' => 'Parafusadeira'])->assertSessionHasNoErrors();
    expect(DB::table('pcp_ferramentas')->where('id', $f98)->value('nome'))->toBe('Parafusadeira');
});

it('UC-PCISO-03 · refugo de outra empresa não entra no indicador da semana do Painel', function () {
    $this->freezeTime();
    $indicador = fn ($user) => pcisoProp($this, $user, '/producao/painel', 'Producao/Painel/Index', 'painel')['indicadores']['refugo_semana'];
    $antes98 = $indicador($this->g98);
    $antes99 = $indicador($this->g99);

    [$ordem99, $etapa99] = pcisoOrdem($this->b99);
    pcisoRefugo($this->b99, $ordem99, $etapa99, (int) $this->g99->id, '7.0000');

    expect($indicador($this->g98))->toBe($antes98);
    $depois99 = $indicador($this->g99);
    expect($depois99['ocorrencias'])->toBe($antes99['ocorrencias'] + 1)
        ->and((float) $depois99['quantidade'])->toBe((float) $antes99['quantidade'] + 7.0);

    [$ordem98, $etapa98] = pcisoOrdem($this->b98);
    pcisoRefugo($this->b98, $ordem98, $etapa98, (int) $this->g98->id, '2.0000');
    $depois98 = $indicador($this->g98);
    expect($depois98['ocorrencias'])->toBe($antes98['ocorrencias'] + 1)
        ->and((float) $depois98['quantidade'])->toBe((float) $antes98['quantidade'] + 2.0);
});

it('UC-PCISO-04 · coluna manual de outra empresa não move o cartão da OS de mesmo número', function () {
    [$os] = pcisoOsCv($this->b98);
    pcisoEtapaCv($this->b98, $os, pcisoPosto($this->b98, 'Roland', 'Impressão'), 'Impressão', 'iniciada');
    pcisoEtapaCv($this->b98, $os, pcisoPosto($this->b98, 'Mesa', 'Acabamento'), 'Corte', 'pendente');
    $cartao = fn ($user) => collect(pcisoProp($this, $user, '/producao/quadro', 'Producao/Quadro/Index', 'cartoes'))->firstWhere('os_id', $os);

    // Discriminante: linha da 99 para a mesma OS-id, válida (nenhum evento depois do movimento).
    DB::table('pcp_quadro_colunas')->insert(['business_id' => $this->b99, 'os_tipo' => 'comvis_os', 'os_id' => $os, 'setor' => 'Acabamento',
        'ate_evento_id' => 999999999, 'movido_por' => $this->g99->id, 'created_at' => now(), 'updated_at' => now()]);

    $doGestor = $cartao($this->g98);
    expect($doGestor['coluna'])->toBe('Impressão')->and($doGestor['movida'])->toBeFalse()
        ->and($cartao($this->g99))->toBeNull();

    pcisoComo($this, $this->g98)->postJson('/producao/quadro/mover', ['os_tipo' => 'comvis_os', 'os_id' => $os, 'setor' => 'Acabamento'])->assertOk();
    $movido = $cartao($this->g98);
    expect($movido['coluna'])->toBe('Acabamento')->and($movido['movida'])->toBeTrue()
        ->and(DB::table('pcp_quadro_colunas')->where('os_id', $os)->where('business_id', $this->b99)->value('movido_por'))->toBe((int) $this->g99->id);
});

it('UC-PCISO-05 · operação de outra empresa não aparece na consulta da empresa', function () {
    $codigo = 'OP-'.uniqid();
    foreach ([$this->b98, $this->b99] as $biz) {
        // SUPERADMIN: fixture grava nas duas empresas; o escopo é provado nos asserts.
        Operacao::withoutGlobalScopes()->create(['business_id' => $biz, 'posto_id' => pcisoPosto($biz, 'Corte'), 'codigo' => $codigo, 'nome' => 'Cortar']);
    }
    // SUPERADMIN: leitura do id para provar o find; o escopo é provado nos asserts.
    $id99 = Operacao::withoutGlobalScopes()->where('business_id', $this->b99)->where('codigo', $codigo)->value('id');

    $this->actingAs($this->g98);
    session(['user.business_id' => $this->b98]);
    expect(Operacao::query()->where('codigo', $codigo)->pluck('business_id')->map('intval')->all())->toBe([$this->b98])
        ->and(Operacao::query()->find($id99))->toBeNull();

    $this->actingAs($this->g99);
    session(['user.business_id' => $this->b99]);
    expect(Operacao::query()->where('codigo', $codigo)->pluck('business_id')->map('intval')->all())->toBe([$this->b99]);
});

it('UC-PCISO-06 · Liberar recusa etapa real de outra empresa e não toca nela', function () {
    // SUPERADMIN: fixture das duas empresas; o escopo é provado nos asserts.
    $ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => random_int(800000, 899999),
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    // SUPERADMIN: fixture da empresa 98.
    $etapa = Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98, 'os_tipo' => Ordem::OS_TIPO, 'os_id' => $ordem->id,
        'posto_id' => pcisoPosto($this->b98, 'Corte'), 'bloco' => 1, 'nome' => 'Corte', 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(),
        'status' => 'pendente', 'created_by' => $this->g98->id]);
    [, $etapa99] = pcisoOrdem($this->b99);

    expect(fn () => app(SituacaoDaOrdem::class)->liberar($this->b98, $ordem->id, $this->g98, [$etapa99 => $this->g98->id]))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::RESPONSAVEL_INVALIDO));
    expect(DB::table('pcp_programacoes')->where('id', $etapa99)->value('user_id'))->toBeNull()
        ->and($ordem->fresh()->currentStage->key)->toBe('aguardando_liberacao');

    // Controle: o mesmo Liberar passa com a etapa da própria OS.
    app(SituacaoDaOrdem::class)->liberar($this->b98, $ordem->id, $this->g98, [$etapa->id => $this->g98->id]);
    expect($ordem->fresh()->currentStage->key)->toBe('na_fila')
        ->and((int) $etapa->fresh()->user_id)->toBe((int) $this->g98->id);
});
