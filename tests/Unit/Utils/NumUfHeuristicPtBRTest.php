<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\Util;
use Tests\TestCase;

/**
 * Guard regressão pro fix do bug Larissa @ Rota Livre biz=4 (2026-05-28):
 * `num_uf("80.00")` retornava **8000** porque legacy assumia ponto = milhar
 * SEMPRE, sem heurística. Cliente cola "80.00" de calculadora/Excel US →
 * R$ [redacted Tier 0] gravado no banco.
 *
 * Fix em app/Utils/Util.php@num_uf — heurística canônica pt-BR (paridade com
 * resources/js/Lib/numberPtBR.ts `parseDecimalPtBR` commit 9b842ab8b).
 *
 * Refs:
 *   - app/Utils/Util.php@num_uf (fix)
 *   - resources/js/Lib/numberPtBR.ts (front, mesma heurística)
 *   - commit 9b842ab8b (R8 2026-05-27 fix front)
 *   - Wagner report 2026-05-28 "blusa R$ [redacted Tier 0] vira R$ [redacted Tier 0]+ na impressão"
 */
class NumUfHeuristicPtBRTest extends TestCase
{
    private Util $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = app(Util::class);

        // Session canon BRL — paridade com biz=4 Larissa.
        session([
            'currency' => [
                'symbol' => 'R$',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ],
        ]);
    }

    /**
     * @return array<string, array{0: string|int|float|null, 1: float}>
     */
    public static function casosCanonicosProvider(): array
    {
        return [
            // Bug exato do reporte Wagner 2026-05-28
            'BUG REPORTADO 80.00 (en-US) → 80, não 8000' => ['80.00', 80.0],

            // Pt-BR canônico — vírgula decimal
            '80,00 → 80'                  => ['80,00', 80.0],
            '1.234,56 → 1234.56'          => ['1.234,56', 1234.56],
            '25.000,00 → 25000'           => ['25.000,00', 25000.0],
            '1.234.567,89 → 1234567.89'   => ['1.234.567,89', 1234567.89],

            // Apenas inteiro
            '80 → 80'                     => ['80', 80.0],
            '2788 → 2788'                 => ['2788', 2788.0],

            // Heurística decimal en-US tolerado (1 ponto + ≤2 dígitos)
            '147.77 → 147.77'             => ['147.77', 147.77],
            '1.5 → 1.5'                   => ['1.5', 1.5],
            '0.5 → 0.5'                   => ['0.5', 0.5],

            // Heurística milhar pt-BR (1 ponto + EXATAMENTE 3 dígitos, ou múltiplos pontos)
            '25.000 → 25000'              => ['25.000', 25000.0],
            '1.234 → 1234'                => ['1.234', 1234.0],
            '1.234.567 → 1234567'         => ['1.234.567', 1234567.0],

            // INCIDENTE 2026-06-05 (Larissa @ Rota Livre): desconto percentual gera
            // total fracionado de >3 casas no React (227,90 − 10,05% = 204.99605).
            // Antes o else stripava o ponto → 20499605 (R$ [redacted Tier 0]). Grupo de
            // milhar tem SEMPRE 3 dígitos; ≥4 casas após o ponto = decimal.
            'INCIDENTE 204.99605 → 204.99605' => ['204.99605', 204.99605],
            '20.5005 (4 casas) → 20.5005'     => ['20.5005', 20.5005],
            '227.9 (1 casa) → 227.9'          => ['227.9', 227.9],
            '1329.0567 → 1329.0567'           => ['1329.0567', 1329.0567],

            // Símbolo de moeda + espaços (bug secundário antes retornava 0)
            'R$ 80,00 → 80'               => ['R$ 80,00', 80.0],
            'R$ 2.500,80 → 2500.80'       => ['R$ 2.500,80', 2500.80],
            'R$ 80 → 80'                  => ['R$ 80', 80.0],

            // Negativos (devolução, troco)
            '-80,00 → -80'                => ['-80,00', -80.0],
            '-2.500,50 → -2500.50'        => ['-2.500,50', -2500.50],

            // Edge: vazio / null
            'string vazia → 0'            => ['', 0.0],
            'null → 0'                    => [null, 0.0],

            // Numérico nativo passa through (idempotente)
            '80 (int) → 80'               => [80, 80.0],
            '80.5 (float) → 80.5'         => [80.5, 80.5],
        ];
    }

    /**
     * @param  string|int|float|null  $input
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('casosCanonicosProvider')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function num_uf_aplica_heuristica_canonica($input, float $esperado): void
    {
        $resultado = $this->util->num_uf($input);

        $this->assertEqualsWithDelta(
            $esperado,
            $resultado,
            0.001,
            "num_uf(".json_encode($input).") esperado={$esperado}, recebido={$resultado}"
        );
    }

    /**
     * Bug GIGANTE histórico (anti-regressão dedicada): 80.00 NUNCA mais pode virar 8000.
     * Este teste é o caso de uso REPORTADO pelo cliente — guarda separado pra ficar
     * óbvio em qualquer regressão futura.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function caso_larissa_80_ponto_zero_zero_nunca_mais_vira_oito_mil(): void
    {
        $resultado = $this->util->num_uf('80.00');

        $this->assertSame(
            80.0,
            $resultado,
            'REGRESSÃO CRÍTICA: bug Larissa @ Rota Livre voltou. "80.00" virou '.$resultado.' em vez de 80.0. Ver app/Utils/Util.php@num_uf heurística pt-BR.'
        );

        $this->assertNotSame(
            8000.0,
            $resultado,
            'BUG histórico: legacy retornava 8000 pra "80.00" porque assumia ponto = milhar SEMPRE.'
        );
    }

    /**
     * Fallback robusto: SEM session('currency'), ainda assim aplica heurística pt-BR
     * (não cai mais no `decimal_separator = ""` que quebrava cast pra float).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function num_uf_sem_session_currency_ainda_aplica_heuristica(): void
    {
        session()->forget('currency');

        $this->assertSame(80.0, $this->util->num_uf('80,00'));
        $this->assertSame(80.0, $this->util->num_uf('80.00'));
        $this->assertSame(2500.80, $this->util->num_uf('R$ 2.500,80'));
        $this->assertSame(25000.0, $this->util->num_uf('25.000'));
    }
}
