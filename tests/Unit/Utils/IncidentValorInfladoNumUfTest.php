<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\Util;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GUARD MECANIZADO do incidente 2026-06-05 (ROTA LIVRE biz=4) — venda com desconto
 * PERCENTUAL gravava `final_total` inflado ~×100.000.
 *
 * Causa: React `Sells/Create` calculava `227,90 − 10,05% = 204.99605` (float 5 casas)
 * e mandava como `final_total`. Backend `Util::num_uf("204.99605")` tratava o "." como
 * separador de MILHAR (heurística pt-BR só cobria ≤2 casas) → `str_replace('.','')` →
 * `"20499605"` → **R$ [redacted Tier 0]** numa venda de **R$ [redacted Tier 0]**.
 *
 * Fix #2279: (a) `num_uf` — `afterLastDot !== 3` (milhar tem SEMPRE 3 dígitos; ≥4 casas
 * é decimal); (b) frontend arredonda `final_total` a 2 casas no submit.
 *
 * Este teste é a CONFIRMAÇÃO automatizada exigida pela REGRA MESTRE
 * (memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE"): toda mudança de cálculo de
 * valor é provada por teste — a classe de bug NÃO pode voltar pelo CI.
 *
 * @see memory/sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md
 * @see app/Utils/Util.php@num_uf
 * @see tests/Unit/Utils/NumUfHeuristicPtBRTest.php (cobertura geral do num_uf)
 */
class IncidentValorInfladoNumUfTest extends TestCase
{
    private Util $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = app(Util::class);
        session([
            'currency' => [
                'symbol' => 'R$',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
        ]);
    }

    /**
     * Simula o cálculo do desconto % igual o frontend Sells/Create JÁ COM o fix #2279
     * (Math.round a 2 casas) e devolve o final_total-string que iria pro backend.
     */
    private function finalTotalFrontend(float $beforeTax, float $discPct): string
    {
        $totalGeral = $beforeTax * (1 - $discPct / 100);
        $rounded = round($totalGeral * 100) / 100; // fix #2279 (front)

        return (string) $rounded;
    }

    #[Test]
    public function num_uf_nao_trata_decimal_de_mais_de_2_casas_como_milhar(): void
    {
        // O valor EXATO que quebrou em prod (227,90 − 10,05% = 204.99605).
        $r = $this->util->num_uf('204.99605');

        $this->assertLessThan(
            1000.0,
            $r,
            "REGRESSÃO CRÍTICA do incidente 2026-06-05: '204.99605' inflou pra {$r} (esperado ~205). num_uf voltou a tratar decimal de >2 casas como milhar."
        );
        $this->assertEqualsWithDelta(204.99605, $r, 0.001);

        // Invariante: grupo de milhar tem SEMPRE 3 dígitos → 4+ casas após o ponto = decimal.
        $this->assertEqualsWithDelta(2788.0567, $this->util->num_uf('2788.0567'), 0.001);
        $this->assertEqualsWithDelta(1329.05, $this->util->num_uf('1329.05'), 0.001);

        // E o que DEVE continuar milhar (exatamente 3 dígitos após o ponto):
        $this->assertEqualsWithDelta(25000.0, $this->util->num_uf('25.000'), 0.001);
    }

    /**
     * GUARD end-to-end (frontend round + backend num_uf): venda com desconto %
     * NUNCA pode gerar final_total inflado. Casos REAIS do incidente.
     */
    #[Test]
    public function venda_com_desconto_percentual_nao_infla_o_final_total(): void
    {
        // [total_before_tax, desconto %, esperado] — vendas reais corrigidas em prod.
        $casos = [
            [227.90, 10.05, 205.00],   // venda 81939 / inv 17702 (o caso do cliente)
            [225.90, 5.00, 214.61],    // 69334
            [139.90, 5.00, 132.91],    // 69333
            [79.90, 9.89, 72.00],      // 69324
            [205.90, 10.15, 185.00],   // 69345
        ];

        foreach ($casos as [$beforeTax, $discPct, $esperado]) {
            $sent = $this->finalTotalFrontend($beforeTax, $discPct);
            $stored = $this->util->num_uf($sent);

            // 1) NÃO infla — invariante matemático: desconto só REDUZ (final ≤ before_tax).
            $this->assertLessThanOrEqual(
                $beforeTax + 0.01,
                $stored,
                "Venda before_tax={$beforeTax} desc={$discPct}% INFLOU pra {$stored} (front enviou '{$sent}')."
            );

            // 2) Bate com o valor real esperado (dupla confirmação).
            $this->assertEqualsWithDelta(
                $esperado,
                $stored,
                0.05,
                "Venda before_tax={$beforeTax} desc={$discPct}% deu {$stored}, esperado {$esperado}."
            );
        }
    }
}
