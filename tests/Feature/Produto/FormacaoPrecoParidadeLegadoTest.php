<?php

declare(strict_types=1);

namespace Tests\Feature\Produto;

use App\Utils\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Paridade legado — aba "Custos e Tabelas de Preços" › Formação de Preço.
 *
 * Âncora: memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md
 *   AR-PROD-006 [V0]       R$ Custo com a precisão do legado (exibido `4,300000` — SEIS casas)
 *   AR-PROD-093 [V0][calc] Margem% = (Valor − Custo) / Custo → (7000−4300)/4300 = 62,79 %
 *   AR-PROD-094 [V0][calc] Lucro Previsto = Valor − Custo    → 7000 − 4300 = R$ 2.700,00
 *   AR-PROD-095 [V0][?]    Markup como fator sobre o custo — "confirmar qual campo é mestre"
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O design da aba Custos (Claude Design, rodada 2026-07-15) respondeu o [?] do AR-PROD-095:
 * "MESTRE Markup → derivam Margem, Valor de venda, Lucro previsto". O protótipo renderiza o
 * produto SG03# do legado (custo 4.300,00 / valor 7.000,00) assim:
 *
 *     Markup 62,79 %  →  Valor de venda R$ 6.999,97  ·  Lucro previsto R$ 2.699,97
 *
 * ...enquanto o legado grava R$ 7.000,00 / R$ 2.700,00. Três centavos evaporam: a margem real
 * é 62,790697674…% e, arredondada a DUAS casas pra virar "o mestre", não reconstitui mais o
 * preço de origem. Com markup de 2 casas, R$ 7.000,00 é um preço INEXPRESSÁVEL.
 *
 * Mesma família do incidente num_uf (2026-06-05, 16 vendas ×100k) — só que silenciosa, e pra baixo.
 *
 * O QUE JÁ ESTAVA COBERTO (e este teste NÃO duplica):
 *   - markup = custo × (1 + pct/100) em precisão cheia  → tests/Feature/Calculo/CalculoValorProdutoTest.php (property 1)
 *   - round-trip inc-tax ↔ exc-tax                      → idem (property 2)
 *   - margem fecha o loop via get_percent SEM arredondar → idem (property 3) ← fecha justamente porque não arredonda
 *   - heurística / round-trip do num_uf                 → tests/Unit/Utils/NumUfHeuristicPtBRTest.php
 *
 * O QUE ESTE TESTE ADICIONA (o vizinho que faltava — o round-trip através do valor ARREDONDADO):
 *   1. GOLDEN do produto SG03# do legado — margem exibida e lucro EXATO (AR-PROD-093/094).
 *   2. DISCRIMINAÇÃO RED — prova que o round-trip através de um markup de 2 casas NÃO fecha,
 *      reproduzindo o número do print. Sem isso, "markup é o mestre" entra em prod perdendo centavos.
 *   3. PISO DE PRECISÃO — quantas casas o markup precisa pra fechar em centavo. Resposta: ≥4.
 *      O legado usa 6 (AR-PROD-006) — coerente, e agora explicado.
 *
 * ⛔ TEST-ONLY: não altera nenhum método de cálculo. Caracteriza o motor atual e crava o contrato
 *    de paridade ANTES da tela existir. Implementar a Formação de Preço é US separada sob a
 *    REGRA MESTRE (dupla confirmação + tabela antes→depois + OK [W]).
 *
 * 🔗 G-2: quando a aba Custos ganhar `<Tela>.casos.md`, os UCs de valor citam ESTE arquivo.
 */
class FormacaoPrecoParidadeLegadoTest extends TestCase
{
    /** Produto SG03# — "PARAMETRIZACAO MERCEDES BENS (AXOR)", o exemplo dos prints do legado. */
    private const CUSTO_SG03 = 4300.00;

    private const VALOR_SG03 = 7000.00;

    /** (7000 − 4300) / 4300 × 100 — a margem REAL, sem arredondar. */
    private const MARGEM_EXATA_SG03 = 62.790697674418603;

    private Util $util;

    protected function setUp(): void
    {
        parent::setUp();

        $this->util = app(Util::class);

        // Session canon BRL (mesmo setup do CalculoValorProdutoTest — paridade biz=1 dogfood).
        session([
            'currency' => [
                'symbol' => 'R$',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
        ]);
    }

    // =========================================================================
    // 1) GOLDEN — o produto SG03# do legado (AR-PROD-093 / AR-PROD-094)
    // =========================================================================

    #[Test]
    public function golden_margem_e_lucro_do_produto_sg03_batem_com_o_legado(): void
    {
        $margem = $this->util->get_percent(self::CUSTO_SG03, self::VALOR_SG03);

        // AR-PROD-093 — é ISSO que o legado EXIBE (2 casas). Note: exibe, não necessariamente guarda.
        $this->assertSame(
            '62.79',
            number_format($margem, 2, '.', ''),
            'AR-PROD-093: a margem exibida do SG03# tem que ser 62,79 % (custo 4.300,00 / valor 7.000,00).'
        );

        // Dupla confirmação por caminho independente (REGRA MESTRE): a fórmula fechada da task.
        $this->assertEqualsWithDelta(
            self::MARGEM_EXATA_SG03,
            $margem,
            0.000001,
            'O motor get_percent divergiu da fórmula fechada (Valor − Custo) / Custo × 100.'
        );

        // AR-PROD-094 — o lucro é EXATO. Nenhum centavo pode sumir aqui.
        $this->assertEqualsWithDelta(
            2700.00,
            self::VALOR_SG03 - self::CUSTO_SG03,
            0.0001,
            'AR-PROD-094: Lucro Previsto do SG03# tem que ser R$ 2.700,00 cravado.'
        );
    }

    // =========================================================================
    // 2) DISCRIMINAÇÃO RED — markup de 2 casas como MESTRE não reconstitui o valor
    //    (reproduz o número do protótipo: R$ 6.999,97 / R$ 2.699,97)
    // =========================================================================

    /**
     * Este é o dente. Se ele algum dia falhar, é porque markup de 2 casas passou a fechar em
     * centavo — o que significaria que o motor mudou. Reveja o piso de precisão antes de comemorar.
     */
    #[Test]
    public function discriminacao_markup_mestre_com_2_casas_perde_centavos_do_legado(): void
    {
        $margemExata = $this->util->get_percent(self::CUSTO_SG03, self::VALOR_SG03);
        $markupMestre = round($margemExata, 2); // 62.79 — o valor do print do design

        $valorDerivado = $this->util->calc_percentage(self::CUSTO_SG03, $markupMestre, self::CUSTO_SG03);
        $lucroDerivado = $valorDerivado - self::CUSTO_SG03;

        // Reproduz EXATAMENTE o protótipo — a prova de que o bug é do motor, não do desenho.
        $this->assertEqualsWithDelta(
            6999.97,
            $valorDerivado,
            0.005,
            'Markup 62,79 % sobre custo 4.300,00 tem que dar 6.999,97 — é o número que o protótipo renderiza.'
        );
        $this->assertEqualsWithDelta(
            2699.97,
            $lucroDerivado,
            0.005,
            'O lucro derivado do markup arredondado é 2.699,97 — três centavos abaixo do legado.'
        );

        // ...e portanto NÃO é o valor do legado. Um markup de 2 casas não consegue expressar 7.000,00.
        $this->assertGreaterThan(
            0.01,
            abs(self::VALOR_SG03 - $valorDerivado),
            'Markup de 2 casas fechou em centavo — o piso de precisão mudou; reveja o contrato AR-PROD-095.'
        );
    }

    // =========================================================================
    // 3) PISO DE PRECISÃO — de quantas casas o markup precisa pra fechar em centavo
    // =========================================================================

    /**
     * @return array<string, array{0: int,1: bool}>  [casas decimais do markup, fecha em centavo?]
     */
    public static function precisaoMarkupProvider(): array
    {
        return [
            '2 casas (o do design) — NÃO fecha'                  => [2, false],
            '3 casas — NÃO fecha'                                => [3, false],
            '4 casas — fecha'                                    => [4, true],
            '6 casas (precisão do legado, AR-PROD-006) — fecha'  => [6, true],
        ];
    }

    /**
     * Responde, com número, a pergunta que o design abriu: "se markup é o mestre, com quantas
     * casas ele tem que ser gravado?". O legado exibe custo com 6 casas (AR-PROD-006) — não é
     * capricho de Delphi, é o que faz a conta fechar.
     */
    #[Test]
    #[DataProvider('precisaoMarkupProvider')]
    public function piso_de_precisao_do_markup_para_reconstituir_o_valor(int $casas, bool $deveFechar): void
    {
        $markup = round($this->util->get_percent(self::CUSTO_SG03, self::VALOR_SG03), $casas);
        $valor = $this->util->calc_percentage(self::CUSTO_SG03, $markup, self::CUSTO_SG03);
        $erro = abs(self::VALOR_SG03 - $valor);

        if ($deveFechar) {
            $this->assertLessThanOrEqual(
                0.01,
                $erro,
                "Markup com {$casas} casas deveria reconstituir 7.000,00 em centavo, mas errou R$ {$erro}."
            );
        } else {
            $this->assertGreaterThan(
                0.01,
                $erro,
                "Markup com {$casas} casas passou a fechar em centavo — o piso de precisão mudou; reveja AR-PROD-095."
            );
        }
    }
}
