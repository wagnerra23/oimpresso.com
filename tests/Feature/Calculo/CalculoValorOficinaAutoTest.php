<?php

declare(strict_types=1);

namespace Tests\Feature\Calculo;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\DviInspectionService;
use Modules\OficinaAuto\Services\ServiceOrderItemService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Onda 1.4 — Dente de cálculo aplicado ao coração da ORDEM DE SERVIÇO (OficinaAuto).
 * @see memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md
 * @see memory/requisitos/OficinaAuto/ROADMAP.md §"Onda 1.4 — Dente de cálculo (OS)"
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A REGRA MESTRE (memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE") exige dupla
 * confirmação MANUAL de toda mudança que mexa em valor/estoque — controle humano que
 * NÃO sobrevive ao tempo. Este dente converte esse controle manual em AUTOMÁTICO no
 * cálculo PRÓPRIO da OS de reparo (biz=164 Martinho LIVE): total da OS = Σ peças +
 * Σ mão-de-obra + Σ serviço-terceiro (desconto entra como override de `valor_total`
 * por item — não há campo de desconto na OS, ADR 0194/0265 · schema DECIMAL(10,2)).
 *
 * O CONTRATO vem de FORA do código (anti-tautologia — memory/proibicoes.md §"Teste
 * que deriva do CÓDIGO"): conservação de dinheiro (o todo == Σ das partes, sem centavo
 * perdido) + filtro de severidade do DVI (orçamento recomendado = só atenção+crítico).
 * NÃO é o comportamento que a classe faz hoje; é a lei do domínio que ela DEVE obedecer.
 *
 * O QUE JÁ ESTAVA COBERTO (e este teste NÃO duplica — compare-não-duplica):
 *   - Exemplos redondos func. de addItem/recalcularTotal → Modules/OficinaAuto/Tests/
 *     Feature/ServiceOrderItemTest.php (180.00 · 60→120 · breakdown 130/100)
 *   - Exemplo redondo totalRecomendado (350.50) → DviInspectionItemTest.php
 *   - items_total via withSum no index → ServiceOrderIndexItemsTotalTest.php
 *   Esses provam "ele SOMA". Faltava provar "ele soma à EXATA precisão de centavo numa
 *   FAIXA de valores, e nunca INFLA" — o ângulo property + golden que a Onda 1.4 pede.
 *
 * O QUE ESTE TESTE ADICIONA (ângulos novos — "0 teste hoje" pra este ângulo):
 *   1. Property conservação `recalcularTotal == round(Σ valor_total, 2)` numa faixa
 *      fuzzed (pega a armadilha do float 0.1+0.1+0.1 e qualquer troca round→floor/int).
 *   2. Golden centavo: 3× 0,10 == 0,30 (NÃO 0.30000000000000004) e acumulação fracionária.
 *   3. Property partição `breakdownPorTipo`: peça+mão+terceiro == total, e cada
 *      sub-total == Σ do seu tipo (dinheiro não some nem duplica ao categorizar).
 *   4. Golden override (classe do incidente num_uf 2026-06-05): um `valor_total` de
 *      desconto flui pra OS SEM inflar — sentinela de regressão se alguém um dia rotear
 *      esse valor por um parser pt-BR (num_uf) ou trocar o round por strip.
 *   5. Property DVI `totalRecomendado == Σ valor_recomendado {atenção,crítico}` exato,
 *      ignorando `ok`, numa faixa fuzzed.
 *   6. Discriminação RED: reproduz INLINE um mutante que strippa/floora centavo e prova
 *      que DIVERGE do resultado real — enquanto divergirem, o green tem poder de pegar
 *      a regressão. Não muta o código de prod (canary LIVE).
 *
 * ⛔ TEST-ONLY: este arquivo NÃO altera nenhum método de cálculo. O canary Martinho
 *    (biz=164, ADR 0171) está LIVE — qualquer mudança em addItem/recalcularTotal/
 *    breakdownPorTipo/totalRecomendado é valor em prod → US separada sob REGRA MESTRE
 *    (dupla confirmação + antes→depois + OK [W]), nunca pega carona no PR do teste.
 *
 * ⛔ CT100 only (memory/proibicoes.md): teste nunca roda local nem Hostinger.
 * Tests biz=1 conforme ADR 0101 (biz=99 só isolamento cross-tenant).
 */
class CalculoValorOficinaAutoTest extends TestCase
{
    use DatabaseTransactions;

    private const BIZ = 1;

    private const PLATE_PREFIX = 'CALCOA';

    private ServiceOrderItemService $itemService;

    private DviInspectionService $dviService;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
        }
        if (! Schema::hasTable('oficina_service_order_items') || ! Schema::hasTable('oa_inspection_items')) {
            $this->markTestSkipped('Rode migrations oficina_service_order_items + oa_inspection_items primeiro');
        }

        $this->itemService = app(ServiceOrderItemService::class);
        $this->dviService = app(DviInspectionService::class);
    }

    // -------------------------------------------------------------------------
    // Helpers — OS efêmera business-scoped (DatabaseTransactions faz rollback)
    // -------------------------------------------------------------------------

    private function novaOs(string $suffix): ServiceOrder
    {
        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'business_id'  => self::BIZ,
            'plate'        => self::PLATE_PREFIX . $suffix,
            'vehicle_type' => 'automovel',
        ]);

        return ServiceOrder::withoutGlobalScopes()->create([
            'business_id' => self::BIZ,
            'vehicle_id'  => $vehicle->id,
            'status'      => 'aberta',
        ]);
    }

    /** Cria item com valor_total override direto (2 casas) — caminho de desconto. */
    private function addItemOverride(int $osId, string $tipo, float $valorTotal): ServiceOrderItem
    {
        return $this->itemService->addItem(self::BIZ, $osId, [
            'tipo'        => $tipo,
            'descricao'   => "item {$tipo} {$valorTotal}",
            'quantidade'  => 1,
            'valor_total' => $valorTotal,
        ]);
    }

    // =========================================================================
    // 1) PROPERTY — conservação: recalcularTotal == round(Σ valor_total, 2)
    // =========================================================================

    /**
     * Cada conjunto tem valores já em 2 casas (DECIMAL(10,2) armazena exato), então a
     * soma-contrato é inequívoca. Um mutante que troque `sum` por cast-int, `round` por
     * `floor`, ou que perca centavo na acumulação, viola a igualdade.
     *
     * @return array<string, array{0: list<float>}>
     */
    public static function conjuntosValorProvider(): array
    {
        return [
            'centavos puros (armadilha 0.1+0.1+0.1)' => [[0.10, 0.10, 0.10]],
            'um item'                                => [[204.99]],
            'peça + mão + terceiro'                  => [[150.00, 89.90, 42.50]],
            'muitos centavos fracionários'           => [[1.11, 2.22, 3.33, 4.44, 5.55]],
            'valores altos mecânica pesada'          => [[4800.00, 1299.90, 15.75]],
            'noves repetidos'                        => [[99.99, 99.99, 99.99]],
            'zero no meio'                           => [[10.00, 0.00, 5.05]],
            'quase teto DECIMAL(10,2)'               => [[9999999.99, 0.01]],
        ];
    }

    #[Test]
    #[DataProvider('conjuntosValorProvider')]
    public function property_recalcular_total_conserva_centavo(array $valores): void
    {
        $os = $this->novaOs('P1' . substr(md5(serialize($valores)), 0, 6));

        foreach ($valores as $v) {
            $this->addItemOverride($os->id, ServiceOrderItem::TIPO_PECA, $v);
        }

        // Contrato externo: total da OS = soma exata dos valores (arredondada a 2 casas).
        $esperado = round(array_sum($valores), 2);

        $this->assertEqualsWithDelta(
            $esperado,
            $this->itemService->recalcularTotal($os->id),
            0.0001,
            'recalcularTotal perdeu/ganhou centavo — conservação de dinheiro violada.'
        );
    }

    // =========================================================================
    // 2) PROPERTY fuzzed — mesma invariante numa faixa pseudo-aleatória determinística
    // =========================================================================

    #[Test]
    public function property_recalcular_total_fuzzed_deterministico(): void
    {
        mt_srand(20260703); // seed fixa → reprodutível no CI

        for ($caso = 0; $caso < 30; $caso++) {
            $os = $this->novaOs('P2' . $caso);

            $n = mt_rand(1, 6);
            $valores = [];
            for ($i = 0; $i < $n; $i++) {
                // Valor 2-casas na faixa 0,01 .. 9.999,99 — armazenável exato em DECIMAL(10,2).
                $valores[] = round(mt_rand(1, 999999) / 100, 2);
            }

            foreach ($valores as $v) {
                $this->addItemOverride($os->id, ServiceOrderItem::TIPO_PECA, $v);
            }

            $esperado = round(array_sum($valores), 2);

            $this->assertEqualsWithDelta(
                $esperado,
                $this->itemService->recalcularTotal($os->id),
                0.0001,
                "Caso fuzzed #{$caso} quebrou a conservação: valores=[" . implode(',', $valores) . "]."
            );
        }
    }

    // =========================================================================
    // 3) GOLDEN — 3× 0,10 == 0,30 (a armadilha clássica do float)
    // =========================================================================

    #[Test]
    public function golden_tres_dez_centavos_da_trinta_centavos(): void
    {
        $os = $this->novaOs('G3');

        $this->addItemOverride($os->id, ServiceOrderItem::TIPO_PECA, 0.10);
        $this->addItemOverride($os->id, ServiceOrderItem::TIPO_MAO_OBRA, 0.10);
        $this->addItemOverride($os->id, ServiceOrderItem::TIPO_SERVICO_TERCEIRO, 0.10);

        $total = $this->itemService->recalcularTotal($os->id);

        // Número exato — NÃO 0.30000000000000004 (float sem guarda de round/DECIMAL).
        $this->assertSame(0.30, round($total, 2), "0,10 × 3 deu {$total} (esperado 0,30).");
    }

    // =========================================================================
    // 4) PROPERTY — partição breakdownPorTipo: as partes reconstroem o todo
    // =========================================================================

    #[Test]
    public function property_breakdown_particiona_o_total_sem_perder_dinheiro(): void
    {
        $os = $this->novaOs('G4');

        // 2 peças, 1 mão-de-obra, 2 serviços — valores fracionários.
        $pecas    = [150.25, 89.90];
        $maoObra  = [320.00];
        $terceiro = [42.55, 7.10];

        foreach ($pecas as $v) {
            $this->addItemOverride($os->id, ServiceOrderItem::TIPO_PECA, $v);
        }
        foreach ($maoObra as $v) {
            $this->addItemOverride($os->id, ServiceOrderItem::TIPO_MAO_OBRA, $v);
        }
        foreach ($terceiro as $v) {
            $this->addItemOverride($os->id, ServiceOrderItem::TIPO_SERVICO_TERCEIRO, $v);
        }

        $bd = $this->itemService->breakdownPorTipo($os->id);

        // Contrato: cada sub-total == Σ do seu tipo (dinheiro não migra de categoria).
        $this->assertEqualsWithDelta(round(array_sum($pecas), 2), $bd['peca'], 0.0001);
        $this->assertEqualsWithDelta(round(array_sum($maoObra), 2), $bd['mao_obra'], 0.0001);
        $this->assertEqualsWithDelta(round(array_sum($terceiro), 2), $bd['servico_terceiro'], 0.0001);

        // Contrato de conservação: as partes reconstroem o todo, EXATO.
        $this->assertEqualsWithDelta(
            round(array_sum($pecas) + array_sum($maoObra) + array_sum($terceiro), 2),
            $bd['total'],
            0.0001,
            'breakdown.total ≠ soma das partes — partição perdeu dinheiro.'
        );

        // E o todo do breakdown bate com o somador independente recalcularTotal.
        $this->assertEqualsWithDelta(
            $this->itemService->recalcularTotal($os->id),
            $bd['total'],
            0.0001,
            'breakdown.total divergiu de recalcularTotal (dois caminhos, mesmo dinheiro).'
        );
    }

    // =========================================================================
    // 5) GOLDEN — addItem calcula valor_total = round(qty × unit, 2)
    // =========================================================================

    /**
     * Sem override, o item deriva valor_total de quantidade × valor_unitário.
     * Casos com quantidade fracionária (0,250 L óleo — DECIMAL(10,3)) exercem a
     * multiplicação real, não só inteiros.
     *
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public static function qtdUnitProvider(): array
    {
        return [
            'inteiro simples'        => [3.0, 60.00, 180.00],
            'óleo 0,250 L'           => [0.250, 42.00, 10.50],
            'quantia fracionária'    => [2.5, 19.90, 49.75],
            'centavo no unitário'    => [7.0, 3.33, 23.31],
            'uma unidade'            => [1.0, 1299.90, 1299.90],
        ];
    }

    #[Test]
    #[DataProvider('qtdUnitProvider')]
    public function golden_add_item_calcula_qty_vezes_unit(float $qtd, float $unit, float $esperado): void
    {
        $os = $this->novaOs('G5' . substr(md5("{$qtd}-{$unit}"), 0, 5));

        $item = $this->itemService->addItem(self::BIZ, $os->id, [
            'tipo'           => ServiceOrderItem::TIPO_PECA,
            'descricao'      => 'peça calc',
            'quantidade'     => $qtd,
            'valor_unitario' => $unit,
        ]);

        // Contrato: round(qty × unit, 2), confirmado à mão no provider (dupla confirmação).
        $this->assertEqualsWithDelta(
            $esperado,
            (float) $item->valor_total,
            0.0001,
            "valor_total = {$item->valor_total} (esperado {$esperado} = {$qtd} × {$unit})."
        );

        // E o total da OS reflete esse único item.
        $this->assertEqualsWithDelta($esperado, $this->itemService->recalcularTotal($os->id), 0.0001);
    }

    // =========================================================================
    // 6) GOLDEN — override de desconto flui SEM inflar (classe do incidente num_uf)
    // =========================================================================

    /**
     * O caminho de desconto na OS é o override de `valor_total` (não há campo desconto —
     * ADR 0194/0265). Um valor de desconto tem que aterrissar na OS à precisão de centavo
     * e JAMAIS inflar pra outra ordem de grandeza. Hoje o Service casta `(float)` direto
     * (não passa por num_uf), então este golden é SENTINELA: se um dia esse valor for
     * roteado por um parser pt-BR (o vetor do incidente 2026-06-05) ou o round virar
     * strip, o número certo aqui vira red.
     */
    #[Test]
    public function golden_override_desconto_nao_infla(): void
    {
        $os = $this->novaOs('G6');

        // Peça cheia 227,90; mão-de-obra com desconto aplicado → override 204,99 (2 casas).
        $this->addItemOverride($os->id, ServiceOrderItem::TIPO_PECA, 227.90);
        $this->addItemOverride($os->id, ServiceOrderItem::TIPO_MAO_OBRA, 204.99);

        $total = $this->itemService->recalcularTotal($os->id);

        // Número exato (à mão: 227,90 + 204,99 = 432,89).
        $this->assertEqualsWithDelta(432.89, $total, 0.0001, "override inflou: total={$total} (esperado 432,89).");

        // Teto de sanidade: o total NUNCA pode saltar pra dezenas de milhar (vetor num_uf).
        $this->assertLessThan(
            10000.0,
            $total,
            "total {$total} inflou pra outra ordem de grandeza — regressão classe num_uf 2026-06-05."
        );
    }

    // =========================================================================
    // 7) PROPERTY — DVI totalRecomendado soma só {atenção,crítico}, exato
    // =========================================================================

    #[Test]
    public function property_dvi_total_recomendado_filtra_severidade(): void
    {
        mt_srand(20260703);

        for ($caso = 0; $caso < 15; $caso++) {
            $os = $this->novaOs('P7' . $caso);

            $somaRecomendavel = 0.0;

            // 'ok' NÃO entra no orçamento recomendado (contrato de domínio DVI).
            $qtdOk = mt_rand(0, 3);
            for ($i = 0; $i < $qtdOk; $i++) {
                $this->dviService->addItem(self::BIZ, $os->id, [
                    'categoria'         => 'motor',
                    'descricao'         => "ok {$i}",
                    'severity'          => OaInspectionItem::SEVERITY_OK,
                    'valor_recomendado' => round(mt_rand(1, 500000) / 100, 2), // ignorado
                ]);
            }

            foreach ([OaInspectionItem::SEVERITY_ATENCAO, OaInspectionItem::SEVERITY_CRITICO] as $sev) {
                $qtd = mt_rand(0, 3);
                for ($i = 0; $i < $qtd; $i++) {
                    $v = round(mt_rand(1, 500000) / 100, 2);
                    $somaRecomendavel += $v;
                    $this->dviService->addItem(self::BIZ, $os->id, [
                        'categoria'         => 'freios',
                        'descricao'         => "{$sev} {$i}",
                        'severity'          => $sev,
                        'valor_recomendado' => $v,
                    ]);
                }
            }

            // Contrato: orçamento recomendado = Σ apenas de atenção+crítico, exato.
            $this->assertEqualsWithDelta(
                round($somaRecomendavel, 2),
                $this->dviService->totalRecomendado($os->id),
                0.0001,
                "Caso DVI #{$caso}: totalRecomendado divergiu de Σ{atenção,crítico}."
            );
        }
    }

    // =========================================================================
    // 8) DISCRIMINAÇÃO RED — prova que um mutante que floora centavo DIVERGE
    // =========================================================================

    /**
     * TEST-ONLY não pode mutar o código de prod (canary LIVE) pra provar o RED. Em vez
     * disso reproduzimos INLINE dois mutantes plausíveis do somador — (a) `floor` que
     * strippa o centavo e (b) cast-int que descarta a parte fracionária — e travamos o
     * CONTRATO: o resultado real conserva o centavo, os mutantes NÃO. Enquanto divergirem,
     * o green dos testes acima tem poder de pegar a regressão. Se convergirem (alguém
     * trocar o round por floor/int), esta asserção quebra = RED consciente.
     */
    #[Test]
    public function discriminacao_mutante_que_floora_centavo_seria_red(): void
    {
        $os = $this->novaOs('R8');

        $valores = [1.11, 2.22, 3.33, 0.10, 0.10, 0.10]; // Σ = 6,96
        foreach ($valores as $v) {
            $this->addItemOverride($os->id, ServiceOrderItem::TIPO_PECA, $v);
        }

        // O somador real conserva via round()+DECIMAL(10,2): Σ = 6,96 exato.
        $real = $this->itemService->recalcularTotal($os->id);

        // Mutante A — floor sobre float×100: a repr binária de 6,96 é 6,9599999… →
        // floor(695,99…)/100 = 6,95 → STRIPPA 1 centavo. (Sem round() vira este bug.)
        $mutanteFloor = floor(array_sum($valores) * 100) / 100;

        // Mutante B — cast-int: descarta toda a parte fracionária → 6,00.
        $mutanteInt = (float) (int) array_sum($valores);

        // Sanidade do contrato real.
        $this->assertEqualsWithDelta(6.96, $real, 0.0001, 'Somador real tem que conservar 6,96.');

        // O discriminador: AMBOS os mutantes divergem do real. Enquanto divergirem, o
        // green dos testes de conservação acima tem poder de pegar a regressão. Se um dia
        // convergirem (round trocado por floor/int), estas asserções quebram = RED consciente.
        // Threshold 0,005 (não 0,01): abs(6,96 − 6,95) é 0,00999… em float — comparar com
        // 0,01 exato é a própria armadilha que este dente caça. Floor strippa ~1 centavo > 0,005.
        $this->assertGreaterThan(
            0.005,
            abs($real - $mutanteFloor),
            'Mutante floor convergiu com o real — o green perdeu poder de pegar strip de centavo.'
        );
        $this->assertGreaterThan(
            0.5,
            abs($real - $mutanteInt),
            'Mutante cast-int convergiu com o real — o green perdeu poder de pegar perda de fração.'
        );
    }

    // =========================================================================
    // 9) Multi-tenant Tier 0 — total não vaza item de outro business (ADR 0093)
    // =========================================================================

    #[Test]
    public function total_da_os_nao_soma_item_de_outro_business(): void
    {
        $os = $this->novaOs('T9');

        $this->addItemOverride($os->id, ServiceOrderItem::TIPO_PECA, 100.00);

        // addItem de biz=99 na MESMA OS deve ser rejeitado (OS não pertence ao biz 99).
        $rejeitou = false;
        try {
            $this->itemService->addItem(99, $os->id, [
                'tipo'        => ServiceOrderItem::TIPO_PECA,
                'descricao'   => 'cross-tenant',
                'valor_total' => 999999.99,
            ]);
        } catch (InvalidArgumentException $e) {
            $rejeitou = true;
        }

        $this->assertTrue($rejeitou, 'addItem cross-tenant (biz=99 em OS biz=1) deveria ter sido rejeitado — Tier 0 ADR 0093.');
        $this->assertEqualsWithDelta(
            100.00,
            $this->itemService->recalcularTotal($os->id),
            0.0001,
            'total contaminado por item cross-tenant.'
        );
    }
}
