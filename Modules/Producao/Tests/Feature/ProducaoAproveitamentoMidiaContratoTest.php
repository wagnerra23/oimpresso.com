<?php
// @covers-us US-PCP-024

declare(strict_types=1);
// Cobre UC-PCAPV-01..05 (Producao/Apontamento/Index.casos.md) — UC-FLX-13 do catálogo de fluxos.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Services\ProcessoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-PCP-024 · UC-FLX-13 — simulador de aproveitamento de mídia na bobina.
 *
 * Arranjo, tenant 98 contra o 99 (NUNCA biz=4): lona com bobina de 1,600 m de largura útil e
 * margem de 0,020 m entre peças, que aceita girar.
 *   OS A: 2 × 0,780 × 0,500 m (lona)  + 1 peça de adesivo (outra mídia, não entra)
 *   OS B: 1 × 0,500 × 1,000 m e 1 × 0,580 × 0,500 m (lona)
 * Contas à mão em cada caso (mm inteiros).
 *
 * ⚠️ SKIP em SQLite / sem tabelas: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_bobinas') || ! Schema::hasTable('comvis_os') || ! Schema::hasTable('comvis_orcamento_itens')) {
        $this->markTestSkipped('Tabelas pcp_bobinas ou comvis_* ausentes nesta lane.');
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

    $this->lona = aprMidia($this->b98, 'Lona 440 g');
    $this->adesivo = aprMidia($this->b98, 'Adesivo branco');
    [$this->osA, $orcA] = aprOs($this->b98);
    aprItem($this->b98, $orcA, 'Faixa', '0.780', '0.500', 2, $this->lona, 1);
    aprItem($this->b98, $orcA, 'Adesivo vitrine', '0.300', '0.300', 4, $this->adesivo, 2);
    [$this->osB, $orcB] = aprOs($this->b98);
    aprItem($this->b98, $orcB, 'Banner em pé', '0.500', '1.000', 1, $this->lona, 1);
    aprItem($this->b98, $orcB, 'Placa', '0.580', '0.500', 1, $this->lona, 2);
    $this->etapaA = aprEtapa($this->b98, $this->osA);
    $this->etapaB = aprEtapa($this->b98, $this->osB);
});

function aprMidia(int $biz, string $nome): int
{
    return (int) DB::table('comvis_materiais')->insertGetId(['business_id' => $biz, 'nome' => $nome, 'categoria' => 'lona',
        'created_at' => now(), 'updated_at' => now()]);
}

/** @return array{0:int, 1:int} [os, orçamento] */
function aprOs(int $biz): array
{
    $orc = (int) DB::table('comvis_orcamentos')->insertGetId(['business_id' => $biz, 'numero' => 'ORC-APV-'.uniqid(),
        'data_emissao' => '2026-10-20', 'created_at' => now(), 'updated_at' => now()]);
    $os = (int) DB::table('comvis_os')->insertGetId(['business_id' => $biz, 'orcamento_id' => $orc, 'numero' => 'OS-A'.random_int(100000, 999999),
        'status_etapa' => 'producao', 'valor_total' => 0, 'created_at' => now(), 'updated_at' => now()]);

    return [$os, $orc];
}

function aprItem(int $biz, int $orc, string $desc, string $l, string $a, int $q, int $material, int $ordem): void
{
    DB::table('comvis_orcamento_itens')->insert(['business_id' => $biz, 'orcamento_id' => $orc, 'material_id' => $material,
        'descricao' => $desc, 'largura_m' => $l, 'altura_m' => $a, 'quantidade' => $q,
        'area_m2' => number_format((float) $l * (float) $a * $q, 3, '.', ''), 'ordem' => $ordem, 'created_at' => now(), 'updated_at' => now()]);
}

function aprEtapa(int $biz, int $os): int
{
    $posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'APV-'.uniqid(), 'nome' => 'Plotter',
        'tipo_custo' => 'maquina', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);

    return (int) DB::table('pcp_programacoes')->insertGetId(['business_id' => $biz, 'os_tipo' => 'comvis_os', 'os_id' => $os,
        'posto_id' => $posto, 'inicio_previsto' => now(), 'fim_previsto' => now()->addHour(), 'status' => 'pendente',
        'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
}

it('UC-PCAPV-01 · a mídia ganha largura útil, margem e giro; sem bobina cadastrada o simulador recusa', function () {
    $this->actingAs($this->op)->postJson('/producao/aproveitamento/simular', ['material_id' => $this->lona, 'os_ids' => [$this->osA]])
        ->assertStatus(422)->assertJson(['codigo' => 'material_invalido']);

    $this->actingAs($this->op)->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 1.6, 'margem_m' => 0.02])
        ->assertOk()->assertJson(['material_id' => $this->lona, 'nome' => 'Lona 440 g', 'largura_util_m' => '1.600', 'margem_m' => '0.020', 'permite_girar' => true]);
    // Trocar não duplica: uma bobina por mídia.
    $this->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 1.6, 'margem_m' => 0.02, 'permite_girar' => false])
        ->assertOk()->assertJson(['permite_girar' => false]);
    expect(DB::table('pcp_bobinas')->where('business_id', $this->b98)->where('material_id', $this->lona)->count())->toBe(1);
    $this->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 0])->assertUnprocessable();
});

it('UC-PCAPV-02 · girando, as 4 peças das duas OS cabem em 2 faixas: 1,020 m, perda 3,80% contra 52,37% uma a uma', function () {
    $this->actingAs($this->op)->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 1.6, 'margem_m' => 0.02])->assertOk();
    $antes = DB::table('pcp_arranjos')->where('business_id', $this->b98)->count();

    $r = $this->postJson('/producao/aproveitamento/simular', ['material_id' => $this->lona, 'os_ids' => [$this->osA, $this->osB]])->assertOk();

    // Banner 0,500 × 1,000 deita (1,000 × 0,500). Todas com 0,500 de altura, da mais larga para a mais estreita:
    // faixa 1 = banner 1,000 + 0,020 + placa 0,580 = 1,600; faixa 2 = faixa 0,780 + 0,020 + faixa 0,780 = 1,580.
    $faixas = $r->json('faixas');
    expect($faixas)->toHaveCount(2)
        ->and(array_column($faixas[0]['pecas'], 'descricao'))->toBe(['Banner em pé', 'Placa'])
        ->and($faixas[0]['pecas'][0])->toMatchArray(['largura_m' => '1.000', 'altura_m' => '0.500', 'girada' => true])
        ->and($faixas[0]['largura_usada_m'])->toBe('1.600')
        ->and(array_column($faixas[1]['pecas'], 'descricao'))->toBe(['Faixa', 'Faixa'])
        ->and($faixas[1]['largura_usada_m'])->toBe('1.580');
    // 0,500 + 0,020 + 0,500 = 1,020 m · útil 0,780 + 0,500 + 0,290 = 1,570 m² · consumida 1,6 × 1,02 = 1,632 m².
    // Uma a uma: 4 × 0,500 + 3 × 0,020 = 2,060 m · 3,296 m². O adesivo (outra mídia) não entra.
    $r->assertJson(['pecas' => 4, 'metros_lineares' => '1.020', 'area_util_m2' => '1.570', 'area_consumida_m2' => '1.632', 'perda_pct' => '3.80',
        'uma_a_uma' => ['metros_lineares' => '2.060', 'area_consumida_m2' => '3.296', 'perda_pct' => '52.37']]);
    // Rateio pela área: A 0,780 m² → 1,020 × 0,780/1,570 = 0,507 m; B fica com o resto, 0,513 m.
    expect($r->json('por_os'))->toBe([
        ['os_id' => $this->osA, 'area_m2' => '0.780', 'metros_previstos' => '0.507'],
        ['os_id' => $this->osB, 'area_m2' => '0.790', 'metros_previstos' => '0.513'],
    ]);
    // Simular não grava nada.
    expect(DB::table('pcp_arranjos')->where('business_id', $this->b98)->count())->toBe($antes);
});

it('UC-PCAPV-03 · sem giro o banner fica em pé e o rolo cresce para 1,520 m; peça maior que a bobina é recusada', function () {
    $this->actingAs($this->op)->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 1.6, 'margem_m' => 0.02, 'permite_girar' => false])->assertOk();

    // Faixa 1 (1,000 de altura): banner 0,500 + 0,020 + faixa 0,780 = 1,300; a outra faixa e a placa não cabem → faixa 2 (0,500):
    // 0,780 + 0,020 + 0,580 = 1,380. Total 1,000 + 0,020 + 0,500 = 1,520 m.
    $r = $this->postJson('/producao/aproveitamento/simular', ['material_id' => $this->lona, 'os_ids' => [$this->osA, $this->osB]])->assertOk();
    expect($r->json('metros_lineares'))->toBe('1.520')
        ->and($r->json('faixas'))->toHaveCount(2)
        ->and(collect($r->json('faixas.*.pecas'))->flatten(1)->pluck('girada')->unique()->all())->toBe([false]);

    [$osGrande, $orc] = aprOs($this->b98);
    aprItem($this->b98, $orc, 'Painel', '1.700', '1.700', 1, $this->lona, 1);
    $this->postJson('/producao/aproveitamento/simular', ['material_id' => $this->lona, 'os_ids' => [$osGrande]])
        ->assertStatus(422)->assertJson(['codigo' => 'material_invalido']);
});

it('UC-PCAPV-04 · aceitar exige producao.liberar, grava o arranjo e o consumo previsto por OS e gera o romaneio de cada OS', function () {
    $this->actingAs($this->pcp)->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 1.6, 'margem_m' => 0.02])->assertOk();
    $payload = ['material_id' => $this->lona, 'os' => [['os_id' => $this->osA, 'programacao_id' => $this->etapaA],
        ['os_id' => $this->osB, 'programacao_id' => $this->etapaB]]];

    $this->actingAs($this->op)->postJson('/producao/aproveitamento', $payload)->assertForbidden();
    expect(DB::table('pcp_arranjos')->where('business_id', $this->b98)->count())->toBe(0);

    $r = $this->actingAs($this->pcp)->postJson('/producao/aproveitamento', $payload)->assertCreated()
        ->assertJson(['metros_lineares' => '1.020', 'perda_pct' => '3.80']);
    $arranjo = DB::table('pcp_arranjos')->where('business_id', $this->b98)->where('id', $r->json('arranjo_id'))->first();
    expect($arranjo)->not->toBeNull()
        ->and((string) $arranjo->metros_lineares)->toBe('1.020')
        ->and((string) $arranjo->metros_uma_a_uma)->toBe('2.060')
        ->and((int) $arranjo->aceito_por)->toBe((int) $this->pcp->id);

    $porOs = DB::table('pcp_arranjo_os')->where('business_id', $this->b98)->where('arranjo_id', $arranjo->id)->orderBy('id')->get();
    expect($porOs->pluck('os_id')->map(fn ($v) => (int) $v)->all())->toBe([$this->osA, $this->osB])
        ->and($porOs->pluck('metros_previstos')->map(fn ($v) => (string) $v)->all())->toBe(['0.507', '0.513']);
    // Cada OS ganhou o romaneio dela na etapa informada (US-PCP-023), e o arranjo aponta para ele.
    foreach ([[$this->osA, $this->etapaA], [$this->osB, $this->etapaB]] as $i => [$os, $etapa]) {
        $rom = DB::table('pcp_romaneios')->where('business_id', $this->b98)->where('os_id', $os)->first();
        expect($rom)->not->toBeNull()
            ->and((int) $rom->programacao_id)->toBe($etapa)
            ->and((int) $porOs[$i]->romaneio_id)->toBe((int) $rom->id)
            ->and($r->json("por_os.{$i}.romaneio"))->toBe($rom->numero);
    }
});

it('UC-PCAPV-05 · mídia ou OS de outra empresa → 404, e nada é gravado', function () {
    $this->actingAs($this->pcp)->putJson("/producao/bobinas/{$this->lona}", ['largura_util_m' => 1.6, 'margem_m' => 0.02])->assertOk();
    $lona99 = aprMidia($this->b99, 'Lona alheia');
    [$os99, $orc99] = aprOs($this->b99);
    aprItem($this->b99, $orc99, 'Faixa alheia', '0.500', '0.500', 1, $this->lona, 1);
    $etapa99 = aprEtapa($this->b99, $os99);

    $this->putJson("/producao/bobinas/{$lona99}", ['largura_util_m' => 1.6])->assertNotFound();
    $this->postJson('/producao/aproveitamento/simular', ['material_id' => $lona99, 'os_ids' => [$this->osA]])->assertNotFound();
    $this->postJson('/producao/aproveitamento/simular', ['material_id' => $this->lona, 'os_ids' => [$this->osA, $os99]])->assertNotFound();
    $this->postJson('/producao/aproveitamento', ['material_id' => $this->lona, 'os' => [['os_id' => $this->osA, 'programacao_id' => $this->etapaA],
        ['os_id' => $os99, 'programacao_id' => $etapa99]]])->assertNotFound();
    // Etapa de outra empresa para uma OS da 98: o romaneio recusa e o aceite inteiro volta.
    $this->postJson('/producao/aproveitamento', ['material_id' => $this->lona, 'os' => [['os_id' => $this->osA, 'programacao_id' => $etapa99]]])->assertNotFound();

    expect(DB::table('pcp_bobinas')->where('material_id', $lona99)->count())->toBe(0)
        ->and(DB::table('pcp_arranjos')->where('business_id', $this->b98)->count())->toBe(0)
        ->and(DB::table('pcp_arranjo_os')->whereIn('business_id', [$this->b98, $this->b99])->count())->toBe(0)
        ->and(DB::table('pcp_romaneios')->where('business_id', $this->b98)->where('os_id', $this->osA)->count())->toBe(0);
});
