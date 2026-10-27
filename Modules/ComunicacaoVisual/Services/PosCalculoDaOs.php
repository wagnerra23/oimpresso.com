<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Services;

use App\Contracts\Producao\CustoRealDaOs;
use Illuminate\Support\Facades\DB;

/**
 * Pós-cálculo da OS da Comunicação Visual: custo orçado × custo real e a margem de cada um
 * (US-COMVIS-005). Só LÊ: nenhum preço, total ou custo gravado muda (regra mestre de valor).
 *
 *   preço          = comvis_os.valor_total (o que o cliente paga)
 *   orçado         = Σ custo_previsto + custo_maquina_previsto dos itens do orçamento (US-COMVIS-019),
 *                    nulo quando algum item não tem custo previsto — margem com buraco engana
 *   real.material  = m² impresso nas etapas do roteiro (o operador informa ao concluir, US-COMVIS-004)
 *                    × custo médio do m² da OS no dia do orçamento (Σ custo previsto ÷ Σ m² das peças
 *                    com custo). A perda aparece aqui: imprimiu mais m² que o orçado, custou mais.
 *                    Nulo quando nenhuma peça tem custo do m²: sem preço o material engana.
 *                    O spool da CV (`comvis_apontamentos`) não entra: não tem tela, e somar os dois
 *                    contaria o mesmo m² duas vezes.
 *   real.maquina   = horas de máquina apontadas nas etapas do roteiro (contrato CustoRealDaOs)
 *   real.pessoa    = horas de pessoa idem
 *   margem         = preço − custo ; margem_pct = margem ÷ preço × 100 (1 casa)
 *
 * Conta em inteiros: m² impresso (milésimos) × Σ custo (centavos) ÷ Σ m² (milésimos), arredonda UMA
 * vez, meio para cima. Sem float. Tier 0: tudo pelo `$businessId` recebido; OS de outra empresa = null.
 */
final class PosCalculoDaOs
{
    public const OS_TIPO = RoteiroPadraoDaOs::OS_TIPO;

    /** @return array<string, mixed>|null */
    public function daOs(int $businessId, int $osId): ?array
    {
        $os = DB::table('comvis_os')->where('business_id', $businessId)->where('id', $osId)
            ->whereNull('deleted_at')->first(['id', 'numero', 'status_etapa', 'valor_total']);
        if ($os === null) {
            return null;
        }

        $itens = DB::table('comvis_os_itens as oi')
            ->leftJoin('comvis_orcamento_itens as it', function ($j) {
                $j->on('it.id', '=', 'oi.orcamento_item_id')->on('it.business_id', '=', 'oi.business_id');
            })
            ->where('oi.business_id', $businessId)->where('oi.os_id', $osId)
            ->get(['it.area_m2', 'it.custo_previsto', 'it.custo_previsto_m2', 'it.custo_maquina_previsto']);

        $orcadoMaterial = 0;
        $orcadoMaquina = 0;
        $comCusto = 0;
        $areaComCusto = 0; // milésimos de m²
        foreach ($itens as $i) {
            if ($i->custo_previsto !== null) {
                $comCusto++;
                $orcadoMaterial += self::centavos($i->custo_previsto);
                $orcadoMaquina += self::centavos($i->custo_maquina_previsto ?? '0');
                $areaComCusto += self::milesimos($i->area_m2 ?? '0');
            }
        }
        $orcadoCompleto = $itens->isNotEmpty() && $comCusto === $itens->count();

        $pcp = app()->bound(CustoRealDaOs::class) ? app(CustoRealDaOs::class) : null;
        $horas = $pcp?->horas($businessId, self::OS_TIPO, $osId) ?? ['maquina' => '0.00', 'pessoa' => '0.00'];
        $m2Impresso = self::milesimos($pcp?->m2Impresso($businessId, self::OS_TIPO, $osId) ?? '0');
        $material = $areaComCusto > 0
            ? intdiv(2 * $m2Impresso * $orcadoMaterial + $areaComCusto, 2 * $areaComCusto)
            : null;

        $preco = self::centavos($os->valor_total);
        $real = [
            'material' => $material,
            'maquina'  => self::centavos($horas['maquina']),
            'pessoa'   => self::centavos($horas['pessoa']),
            'terceiros' => 0,
        ];
        $real['total'] = $material === null ? null : array_sum($real);
        $orcado = $orcadoCompleto ? ['material' => $orcadoMaterial, 'maquina' => $orcadoMaquina, 'total' => $orcadoMaterial + $orcadoMaquina] : null;

        return [
            'os_id'        => (int) $os->id,
            'numero'       => $os->numero,
            'fechada'      => $os->status_etapa === 'concluida',
            'preco'        => self::reais($preco),
            'orcado'       => $orcado === null ? null : array_map([self::class, 'reais'], $orcado),
            'm2_impresso'  => sprintf('%d.%03d', intdiv($m2Impresso, 1000), $m2Impresso % 1000),
            'real'         => array_map(fn (?int $c) => $c === null ? null : self::reais($c), $real),
            'margem_orcada' => $orcado === null ? null : self::margem($preco, $orcado['total']),
            'margem_real'  => $real['total'] === null ? null : self::margem($preco, $real['total']),
            'itens_com_custo' => $comCusto,
            'itens'        => $itens->count(),
        ];
    }

    /** @return array{valor: string, pct: ?float} */
    private static function margem(int $preco, int $custo): array
    {
        $m = $preco - $custo;

        return ['valor' => self::reais($m), 'pct' => $preco > 0 ? round($m * 100 / $preco, 1, PHP_ROUND_HALF_UP) : null];
    }

    private static function centavos(string|int|float $valor): int
    {
        return self::fixo((string) $valor, 2);
    }

    private static function milesimos(string|int|float $valor): int
    {
        return self::fixo((string) $valor, 3);
    }

    private static function fixo(string $valor, int $casas): int
    {
        $neg = str_starts_with($valor, '-');
        [$inteiro, $fracao] = array_pad(explode('.', ltrim($valor, '-'), 2), 2, '0');
        $n = (int) $inteiro * (10 ** $casas) + (int) str_pad(substr($fracao, 0, $casas), $casas, '0');

        return $neg ? -$n : $n;
    }

    private static function reais(int $centavos): string
    {
        $neg = $centavos < 0 ? '-' : '';
        $c = abs($centavos);

        return sprintf('%s%d.%02d', $neg, intdiv($c, 100), $c % 100);
    }
}
