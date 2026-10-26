<?php

namespace Modules\ComunicacaoVisual\Services;

/**
 * Custo e margem por item e do orçamento, para a calculadora de m² (UC-FLX-03 · US-COMVIS-022).
 *
 * Só ACRESCENTA campos à saída do OrcamentoCalculator: preço, subtotal e total não mudam (regra
 * mestre de valor, proibicoes.md). O custo sai de OrcamentoGravador::custosPrevistos, a mesma
 * regra que o orçamento grava (US-COMVIS-019), então a margem da tela é a do orçamento salvo.
 *
 * Por item:
 *   custo        = custo do material (área × custo do m²) + custo da máquina (horas × custo da hora)
 *                  — nulo quando o material não tem custo cadastrado: sem ele a margem engana.
 *   margem       = subtotal − custo                                   (2 casas, HALF_UP)
 *   margem_pct   = margem ÷ subtotal × 100                            (1 casa)
 * Do orçamento (só quando TODOS os itens têm custo; senão nulo, com a contagem):
 *   receita      = subtotal − desconto  (extras, instalação e entrega não têm custo cadastrado e
 *                                        ficam fora — repassados, não margem)
 *   margem       = receita − soma dos custos ;  margem_pct = margem ÷ receita × 100
 *
 * Tier 0: `$businessId` explícito — o custo de material de outra empresa nunca entra.
 */
final class MargemDoOrcamento
{
    public function __construct(private readonly OrcamentoGravador $gravador) {}

    public function aplicar(int $businessId, array $calculado): array
    {
        $custos = $this->gravador->custosPrevistos($businessId, $calculado['itens']);

        $somaCusto = 0.0;
        $comCusto = 0;
        foreach ($calculado['itens'] as $i => $item) {
            $material = $custos[$i]['material'];
            $maquina = $custos[$i]['maquina']['custo'] ?? null;
            $custo = $material === null ? null : round($material + (float) $maquina, 2, PHP_ROUND_HALF_UP);

            $calculado['itens'][$i]['custo'] = $custo;
            $calculado['itens'][$i]['custo_m2'] = $custos[$i]['m2'];
            $calculado['itens'][$i]['custo_maquina'] = $maquina;
            $calculado['itens'][$i]['margem'] = $custo === null ? null
                : round((float) $item['subtotal'] - $custo, 2, PHP_ROUND_HALF_UP);
            $calculado['itens'][$i]['margem_pct'] = $custo === null ? null
                : self::pct((float) $item['subtotal'] - $custo, (float) $item['subtotal']);

            if ($custo !== null) {
                $somaCusto += $custo;
                $comCusto++;
            }
        }

        $total = count($calculado['itens']);
        $receita = round((float) $calculado['subtotal'] - (float) $calculado['desconto'], 2, PHP_ROUND_HALF_UP);
        $completo = $total > 0 && $comCusto === $total;
        $somaCusto = round($somaCusto, 2, PHP_ROUND_HALF_UP);

        $calculado['margem'] = [
            'itens_com_custo' => $comCusto,
            'itens'           => $total,
            'custo'           => $completo ? $somaCusto : null,
            'receita'         => $receita,
            'margem'          => $completo ? round($receita - $somaCusto, 2, PHP_ROUND_HALF_UP) : null,
            'margem_pct'      => $completo ? self::pct($receita - $somaCusto, $receita) : null,
        ];

        return $calculado;
    }

    private static function pct(float $margem, float $base): ?float
    {
        return $base > 0 ? round($margem / $base * 100, 1, PHP_ROUND_HALF_UP) : null;
    }
}
