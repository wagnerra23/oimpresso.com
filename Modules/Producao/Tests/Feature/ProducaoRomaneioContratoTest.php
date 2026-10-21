<?php
// @covers-us US-PCP-023

declare(strict_types=1);
// Cobre UC-PCROM-01..04 (Producao/Apontamento/Index.casos.md) — UC-FLX-12 do catálogo de fluxos.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Romaneio;
use Modules\Producao\Entities\RomaneioLinha;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-PCP-023 · UC-FLX-12 — romaneio de impressão peça a peça de uma OS de comunicação visual.
 *
 * Arranjo, tenant 98 contra o 99 (NUNCA biz=4): OS com orçamento de 2 peças —
 *   1. Banner 3,000 × 1,000 m × 2, lona 440 g → área 6,000 m²
 *   2. Adesivo 0,500 × 0,500 m × 10, sem material → área 2,500 m²
 * e a etapa de impressão da OS (posto "Plotter").
 *
 * ⚠️ SKIP em SQLite / sem tabelas: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_romaneios') || ! Schema::hasTable('comvis_os') || ! Schema::hasTable('comvis_orcamento_itens')) {
        $this->markTestSkipped('Tabelas pcp_romaneios ou comvis_* ausentes nesta lane.');
    }
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));

    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);

    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->op = $this->usuarioComPermissoes(['producao.access'], $this->B98);

    [$this->os, $this->orc] = romOs($this->b98);
    $this->lona = (int) DB::table('comvis_materiais')->insertGetId(['business_id' => $this->b98, 'nome' => 'Lona 440 g',
        'categoria' => 'lona', 'created_at' => now(), 'updated_at' => now()]);
    $this->itemBanner = romItem($this->b98, $this->orc, 'Banner fachada', '3.000', '1.000', 2, '6.000', 1, $this->lona);
    $this->itemAdesivo = romItem($this->b98, $this->orc, 'Adesivo vitrine', '0.500', '0.500', 10, '2.500', 2, null);
    $this->etapa = romEtapa($this->b98, $this->os, 'Plotter');
});

/** @return array{0:int, 1:int} [os, orçamento] */
function romOs(int $biz): array
{
    $orc = (int) DB::table('comvis_orcamentos')->insertGetId(['business_id' => $biz, 'numero' => 'ORC-ROM-'.uniqid(),
        'data_emissao' => '2026-10-20', 'created_at' => now(), 'updated_at' => now()]);
    $os = (int) DB::table('comvis_os')->insertGetId(['business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'OS-R'.random_int(100000, 999999),
        'status_etapa' => 'producao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now()]);

    return [$os, $orc];
}

function romItem(int $biz, int $orc, string $desc, string $l, string $a, int $q, string $area, int $ordem, ?int $material): int
{
    return (int) DB::table('comvis_orcamento_itens')->insertGetId(['business_id' => $biz, 'orcamento_id' => $orc, 'material_id' => $material,
        'descricao' => $desc, 'largura_m' => $l, 'altura_m' => $a, 'quantidade' => $q, 'area_m2' => $area, 'ordem' => $ordem,
        'created_at' => now(), 'updated_at' => now()]);
}

function romEtapa(int $biz, int $os, string $nomePosto): int
{
    $posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'ROM-'.uniqid(), 'nome' => $nomePosto,
        'tipo_custo' => 'maquina', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);

    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'posto_id' => $posto, 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(), 'status' => 'pendente',
        'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

it('UC-PCROM-01 · gerar o romaneio lista uma linha por peça da OS, com mídia, medida, quantidade e área', function () {
    $r = $this->actingAs($this->pcp)->postJson("/producao/os/{$this->os}/romaneio", ['programacao_id' => $this->etapa])->assertCreated();

    $numeroOs = DB::table('comvis_os')->where('id', $this->os)->value('numero');
    $r->assertJson(['versao' => 1, 'numero' => "ROM-{$numeroOs}-v1", 'os' => ['tipo' => 'comvis_os', 'id' => $this->os],
        'etapa' => ['programacao_id' => $this->etapa, 'nome' => 'Plotter'], 'gerado_por' => $this->pcp->id]);
    $linhas = $r->json('linhas');
    expect($linhas)->toHaveCount(2)
        ->and($linhas[0])->toMatchArray(['ordem' => 1, 'orcamento_item_id' => $this->itemBanner, 'descricao' => 'Banner fachada',
            'material' => ['id' => $this->lona, 'nome' => 'Lona 440 g'], 'largura_m' => '3.000', 'altura_m' => '1.000',
            'quantidade' => 2, 'area_m2' => '6.000', 'ja_enviada_em' => null])
        ->and($linhas[1])->toMatchArray(['ordem' => 2, 'descricao' => 'Adesivo vitrine', 'material' => null,
            'largura_m' => '0.500', 'altura_m' => '0.500', 'quantidade' => 10, 'area_m2' => '2.500']);

    // Leitura pelo operador devolve o mesmo romaneio.
    $this->actingAs($this->op)->getJson('/producao/romaneios/'.$r->json('id'))->assertOk()->assertJson(['numero' => "ROM-{$numeroOs}-v1"]);
});

it('UC-PCROM-02 · romaneio gravado não muda: orçamento alterado não reescreve, gerar de novo cria a v2 e sinaliza a peça já enviada', function () {
    $v1 = $this->actingAs($this->pcp)->postJson("/producao/os/{$this->os}/romaneio", ['programacao_id' => $this->etapa])->assertCreated();

    DB::table('comvis_orcamento_itens')->where('id', $this->itemBanner)->update(['quantidade' => 5, 'area_m2' => '15.000']);
    $this->getJson('/producao/romaneios/'.$v1->json('id'))->assertOk()
        ->assertJsonPath('linhas.0.quantidade', 2)->assertJsonPath('linhas.0.area_m2', '6.000');

    $rom = Romaneio::withoutGlobalScopes()->find($v1->json('id'));
    expect(fn () => $rom->update(['posto_nome' => 'Outro']))->toThrow(LogicException::class)
        ->and(fn () => $rom->delete())->toThrow(LogicException::class)
        ->and(fn () => RomaneioLinha::withoutGlobalScopes()->where('romaneio_id', $rom->id)->first()->update(['quantidade' => 9]))->toThrow(LogicException::class);

    $v2 = $this->postJson("/producao/os/{$this->os}/romaneio", ['programacao_id' => $this->etapa])->assertCreated();
    expect($v2->json('versao'))->toBe(2)
        ->and($v2->json('linhas.0.quantidade'))->toBe(5)
        ->and($v2->json('linhas.0.ja_enviada_em'))->toBe($v1->json('numero'))
        ->and(Romaneio::withoutGlobalScopes()->where('business_id', $this->b98)->where('os_id', $this->os)->count())->toBe(2);
    $this->getJson('/producao/romaneios/'.$v1->json('id'))->assertJsonPath('linhas.0.quantidade', 2)->assertJsonPath('linhas.0.ja_enviada_em', null);
});

it('UC-PCROM-03 · gerar exige o papel producao.liberar e ler exige producao.access; OS sem peças é recusada', function () {
    $this->actingAs($this->op)->postJson("/producao/os/{$this->os}/romaneio", ['programacao_id' => $this->etapa])->assertForbidden();
    expect(Romaneio::withoutGlobalScopes()->where('business_id', $this->b98)->where('os_id', $this->os)->count())->toBe(0);

    $id = $this->actingAs($this->pcp)->postJson("/producao/os/{$this->os}/romaneio", ['programacao_id' => $this->etapa])->assertCreated()->json('id');
    $semAcesso = $this->usuarioComPermissoes([], $this->B98);
    $this->actingAs($semAcesso)->getJson("/producao/romaneios/{$id}")->assertForbidden();

    [$vazia] = romOs($this->b98);
    $etapaVazia = romEtapa($this->b98, $vazia, 'Plotter');
    $this->actingAs($this->pcp)->postJson("/producao/os/{$vazia}/romaneio", ['programacao_id' => $etapaVazia])
        ->assertStatus(422)->assertJson(['codigo' => 'quantidade_invalida']);
});

it('UC-PCROM-04 · OS, etapa ou romaneio de outra empresa → 404, e nada é gravado', function () {
    [$os99, $orc99] = romOs($this->b99);
    romItem($this->b99, $orc99, 'Banner alheio', '1.000', '1.000', 1, '1.000', 1, null);
    $etapa99 = romEtapa($this->b99, $os99, 'Plotter');

    $this->actingAs($this->pcp)->postJson("/producao/os/{$os99}/romaneio", ['programacao_id' => $etapa99])->assertNotFound();
    // Etapa de outra OS (mesma empresa) também não serve de destino.
    [$outra] = romOs($this->b98);
    $this->postJson("/producao/os/{$this->os}/romaneio", ['programacao_id' => romEtapa($this->b98, $outra, 'Plotter')])->assertNotFound();
    expect(Romaneio::withoutGlobalScopes()->whereIn('os_id', [$os99, $this->os])->count())->toBe(0);

    // SUPERADMIN: romaneio gravado direto na outra empresa, para provar o 404 da leitura.
    $alheio = Romaneio::withoutGlobalScopes()->create(['business_id' => $this->b99, 'os_tipo' => 'comvis_os', 'os_id' => $os99, 'versao' => 1,
        'numero' => 'ROM-X-v1', 'programacao_id' => $etapa99, 'gerado_por' => 1, 'gerado_em' => now()]);
    $this->getJson("/producao/romaneios/{$alheio->id}")->assertNotFound();

    // Controle: a mesma rota com OS própria responde 201 (o 404 acima é do escopo, não da rota).
    $this->postJson("/producao/os/{$this->os}/romaneio", ['programacao_id' => $this->etapa])->assertCreated();
});
