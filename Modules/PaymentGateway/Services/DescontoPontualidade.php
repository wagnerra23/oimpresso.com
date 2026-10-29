<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use Illuminate\Support\Facades\DB;

/**
 * Desconto de pontualidade do cliente no boleto (P-086 · Sabor Brasil).
 *
 * O % vem de `contacts.boleto_desconto_pontualidade_pct` (no Delphi,
 * `PESSOAS.BOLETO_PERC_DESCONTO_PADRAO`, com `BOLETOS.DIADESCONTO = 0`):
 * pago até o vencimento, o cliente paga o valor menos o desconto.
 *
 * Uma conta só, em centavos inteiros, usada nos três lugares:
 *   - emissão Inter (API v3, objeto `desconto` de valor fixo);
 *   - emissão BB (CNAB 240, segmento P posições 142-165, código 1 = valor fixo até a data);
 *   - baixa do título (o desconto só entra se o pagamento chegar até a data limite).
 *
 * Arredondamento: meio centavo para cima (round half-up sobre inteiros), feito
 * aqui uma vez e gravado na cobrança. Banco e baixa leem o valor gravado,
 * nunca recalculam.
 *
 * Sem desconto quando:
 *   - o cliente não tem % (nulo ou ≤ 0) ou o % passa de 100;
 *   - o boleto nasce de um título já vencido (a emissão recai o vencimento pra hoje;
 *     pontualidade não vale para quem já atrasou);
 *   - a data limite já passou.
 */
final class DescontoPontualidade
{
    /**
     * @return array{tipo:string, percentual:float, valor_centavos:int, data_limite:string}|array{}
     */
    public static function calcular(
        int $businessId,
        int $contactId,
        int $valorCentavos,
        \DateTimeImmutable $vencimento,
        ?string $origemType = null,
        ?int $origemId = null,
        ?\DateTimeImmutable $hoje = null,
    ): array {
        if ($contactId <= 0 || $valorCentavos <= 0) {
            return [];
        }

        $pct = DB::table('contacts')
            ->where('business_id', $businessId)
            ->where('id', $contactId)
            ->value('boleto_desconto_pontualidade_pct');

        // Percentual em centésimos de ponto (5,00% → 500) para a conta ficar toda em inteiros.
        $pctCentesimos = $pct === null ? 0 : (int) round(((float) $pct) * 100);
        if ($pctCentesimos <= 0 || $pctCentesimos > 10000) {
            return [];
        }

        $limite = $vencimento->setTime(0, 0);

        if ($origemType === 'fin_titulo' && $origemId) {
            $vencTitulo = DB::table('fin_titulos')
                ->where('business_id', $businessId)
                ->where('id', $origemId)
                ->value('vencimento');
            if ($vencTitulo !== null && new \DateTimeImmutable(substr((string) $vencTitulo, 0, 10)) < $limite) {
                return []; // título já vencido: a emissão recaiu o vencimento pra hoje
            }
        }

        $hoje = ($hoje ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        if ($limite < $hoje) {
            return [];
        }

        return [
            'tipo'           => 'valor_fixo_ate_data',
            'percentual'     => $pctCentesimos / 100,
            'valor_centavos' => self::valorCentavos($valorCentavos, $pctCentesimos),
            'data_limite'    => $limite->format('Y-m-d'),
        ];
    }

    /** valor × % em centavos, meio centavo para cima. */
    public static function valorCentavos(int $valorCentavos, int $pctCentesimos): int
    {
        return intdiv($valorCentavos * $pctCentesimos + 5000, 10000);
    }

    /**
     * Quanto de desconto a baixa reconhece.
     *
     * Só quando o pagamento chega até a data limite e cobre o valor menos o desconto.
     * Devolve o que falta para quitar (nunca mais que o desconto gravado); fora disso, 0.
     *
     * @param  array<string, mixed>  $desconto  o que `calcular()` gravou na cobrança
     */
    public static function reconhecidoNaBaixaCentavos(
        array $desconto,
        int $abertoCentavos,
        int $pagoCentavos,
        \DateTimeImmutable $pagaEm,
    ): int {
        $valor = (int) ($desconto['valor_centavos'] ?? 0);
        $limite = (string) ($desconto['data_limite'] ?? '');
        if ($valor <= 0 || $limite === '' || $pagoCentavos <= 0) {
            return 0;
        }
        if ($pagaEm->format('Y-m-d') > $limite) {
            return 0;
        }
        $falta = $abertoCentavos - $pagoCentavos;
        if ($falta <= 0 || $falta > $valor) {
            return 0;
        }

        return $falta;
    }
}
