<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

/**
 * Preço de venda novo pela política de preço do produto, no recebimento da compra (CU-PROD-21).
 *
 * A conta é a do protótipo do [W] (`prototipo-ui/cowork/Wagner/fsm-regras.jsx`, `calcular`), com
 * P = preço de venda com imposto de hoje, cA = custo com imposto anterior, cN = custo com imposto novo:
 *
 *   fixo       — o preço não muda (só a margem). Devolve null.
 *   acompanha  — up10(P × cN ÷ cA), que mantém o markup. Sem custo anterior (cA = 0), o preço fica.
 *   margem     — a margem é sobre o PREÇO DE VENDA (SDD §CU-PROD-21): preço sem imposto =
 *                custo sem imposto ÷ (1 − alvo/100); com imposto = isso × (100 + imposto) / 100,
 *                arredondado com up10. Alvo ausente, negativo ou ≥ 100: o preço fica.
 *
 * up10 = para cima na casa de 0,10, igual ao protótipo (`Math.ceil(v * 10 - 1e-9) / 10`); o 1e-9
 * impede que um 12,3 calculado como 12,3000000001 suba para 12,4.
 *
 * Devolve o preço COM imposto, em 4 casas, ou null quando a política não muda o preço. O preço sem
 * imposto sai deste, como na aprovação da proposta (PropostasDePreco::aprovar).
 */
final class PrecoPelaPolitica
{
    public static function novo(?string $politica, float $precoInc, float $custoAntesInc, float $custoNovoInc, float $custoNovoExc, float $imposto, ?float $alvo): ?float
    {
        return match ($politica) {
            'acompanha' => $custoAntesInc > 0 ? self::up10($precoInc * $custoNovoInc / $custoAntesInc) : null,
            'margem' => ($alvo === null || $alvo < 0 || $alvo >= 100)
                ? null
                : self::up10($custoNovoExc / (1 - $alvo / 100) * (100 + $imposto) / 100),
            default => null,
        };
    }

    public static function up10(float $valor): float
    {
        return round(ceil($valor * 10 - 1e-9) / 10, 4);
    }
}
