<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Depreciação GERENCIAL linear (US-ASSET-009 · decisão [W] 2026-10-26: linear, prazo padrão editável
 * no bem, mostrada como gerencial e nunca como contábil).
 *
 * Por bem: `assets.depreciation` = vida útil em ANOS (o campo do formulário "Depreciação (anos)").
 * Medido em produção em 2026-10-26: 1 bem cadastrado e nenhum com o campo preenchido, então dar esse
 * sentido ao campo não reinterpreta dado de ninguém.
 *
 *   meses decorridos = dias desde `purchase_date` ÷ 30,44, arredondado (a regra do protótipo do
 *                      Patrimônio, `patrimonio-data.jsx::depreciacao`)
 *   total de meses   = anos × 12, arredondado
 *   residual         = valor de compra × (total − min(meses, total)) ÷ total
 * Valor de compra = `unit_price × quantity`. Conta em CENTAVOS inteiros, nunca float.
 *
 * Bem sem vida útil ou sem data de compra não deprecia: fica fora da soma e é contado em
 * `sem_prazo`. Se nenhum bem tem prazo, `residual` é null — o painel mostra "—" (UC-PAT-02).
 * Tier 0: tudo pelo `$businessId` recebido.
 */
final class DepreciacaoGerencial
{
    /** Residual de um bem, em centavos. Null quando o bem não tem vida útil ou data de compra. */
    public static function residualCentavos(string $unitPrice, string $quantity, ?string $anos, ?string $dataCompra, Carbon $hoje): ?int
    {
        if ($anos === null || (float) $anos <= 0 || $dataCompra === null || $dataCompra === '') {
            return null;
        }
        $totalMeses = (int) round((float) $anos * 12);
        if ($totalMeses <= 0) {
            return null;
        }
        $compra = Carbon::parse($dataCompra)->startOfDay();
        $meses = $compra->greaterThan($hoje) ? 0 : (int) round($compra->diffInDays($hoje->copy()->startOfDay()) / 30.44);
        $bruto = self::centavos($unitPrice, $quantity);

        return intdiv($bruto * ($totalMeses - min($meses, $totalMeses)) * 2 + $totalMeses, $totalMeses * 2);
    }

    /**
     * Soma do patrimônio da empresa.
     *
     * @return array{residual: ?float, com_prazo: int, sem_prazo: int}
     */
    public function doPatrimonio(int $businessId, ?Carbon $hoje = null): array
    {
        $hoje = ($hoje ?? Carbon::today())->copy()->startOfDay();
        $soma = 0;
        $com = 0;
        $sem = 0;
        foreach (DB::table('assets')->where('business_id', $businessId)
            ->get(['unit_price', 'quantity', 'depreciation', 'purchase_date']) as $a) {
            $r = self::residualCentavos((string) $a->unit_price, (string) $a->quantity,
                $a->depreciation === null ? null : (string) $a->depreciation, $a->purchase_date, $hoje);
            if ($r === null) {
                $sem++;
                continue;
            }
            $soma += $r;
            $com++;
        }

        return ['residual' => $com > 0 ? $soma / 100 : null, 'com_prazo' => $com, 'sem_prazo' => $sem];
    }

    /** unit_price × quantity em centavos, das strings DECIMAL(22,4), sem passar por float. */
    private static function centavos(string $unitPrice, string $quantity): int
    {
        $dm = static function (string $v): int {
            [$i, $f] = array_pad(explode('.', ltrim(trim($v) === '' ? '0' : trim($v), '-'), 2), 2, '');

            return (int) $i * 10000 + (int) substr(str_pad($f, 4, '0'), 0, 4);
        };
        $produto = $dm($unitPrice) * $dm($quantity); // dez-milésimos²

        return intdiv($produto + 500000, 1000000);    // → centavos, arredondado
    }
}
