<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Apuração do DAS do Simples Nacional pelo Anexo — US-FISCAL-030 (fatia 1, só o cálculo).
 *
 * SÓ CALCULA. Não grava, não lança título, não transmite PGDAS-D e não escolhe o
 * anexo: quem diz o anexo é o contador (Fator R já informa III × V, `FatorRService`).
 * Nenhum chamador ainda — a tela e o "lançar a pagar" chegam em fatia própria, atrás
 * de flag. Até lá o Financeiro segue com a estimativa ≈6% (`ImpostosController`).
 *
 * Lei (LC 123/2006, redação da LC 155/2016, vigente desde 01/01/2018):
 *
 *   art. 18, § 1º "Para efeito de determinação da alíquota nominal, o sujeito passivo
 *   utilizará a receita bruta acumulada nos doze meses anteriores ao do período de
 *   apuração."
 *
 *   art. 18, § 1º-A "A alíquota efetiva é o resultado de: (RBT12 x Aliq - PD)/RBT12,
 *   em que: I - RBT12: receita bruta acumulada nos doze meses anteriores ao período de
 *   apuração; II - Aliq: alíquota nominal constante dos Anexos I a V desta Lei
 *   Complementar; III - PD: parcela a deduzir constante dos Anexos I a V desta Lei
 *   Complementar."
 *
 * Fonte: planalto.gov.br/ccivil_03/leis/lcp/lcp123.htm (Anexos I a V).
 *
 * O que fica de fora, de propósito (perguntas ao contador na US-FISCAL-030):
 *  - início de atividade (art. 18 §2º, RBT12 proporcional): sem RBT12 devolve sem_dado;
 *  - receita acima do sublimite (art. 13-A): ICMS/ISS saem do DAS — aqui só a marca;
 *  - segregação (ST retida, exportação, monofásico, várias atividades): uma receita, um anexo;
 *  - regime de caixa (Res. CGSN 140/2018 art. 18): receita lida por competência.
 */
final class ApuracaoDasService
{
    /** Teto do Simples (art. 3º, II) e sublimite do ICMS/ISS (art. 13-A), em centavos. */
    public const TETO_CENT = 480_000_000;
    public const SUBLIMITE_CENT = 360_000_000;

    private const TRANSACAO_FINAL = 'final';

    /**
     * Anexos I a V: [limite superior da faixa em reais, alíquota nominal em centésimos
     * de ponto percentual (730 = 7,30%), parcela a deduzir em reais].
     */
    public const ANEXOS = [
        'I' => [[180000, 400, 0], [360000, 730, 5940], [720000, 950, 13860], [1800000, 1070, 22500], [3600000, 1430, 87300], [4800000, 1900, 378000]],
        'II' => [[180000, 450, 0], [360000, 780, 5940], [720000, 1000, 13860], [1800000, 1120, 22500], [3600000, 1470, 85500], [4800000, 3000, 720000]],
        'III' => [[180000, 600, 0], [360000, 1120, 9360], [720000, 1350, 17640], [1800000, 1600, 35640], [3600000, 2100, 125640], [4800000, 3300, 648000]],
        'IV' => [[180000, 450, 0], [360000, 900, 8100], [720000, 1020, 12420], [1800000, 1400, 39780], [3600000, 2200, 183780], [4800000, 3300, 828000]],
        'V' => [[180000, 1550, 0], [360000, 1800, 4500], [720000, 1950, 9900], [1800000, 2050, 17100], [3600000, 2300, 62100], [4800000, 3050, 540000]],
    ];

    /**
     * DAS do mês para uma receita e um anexo.
     *
     * Conta em centavos e centésimos de ponto: o DAS sai de
     * receita × (RBT12 × Aliq − PD) ÷ RBT12, sem arredondar a alíquota efetiva no meio.
     *
     * @return array{anexo: string, rbt12: float, receita_mes: float, faixa: ?int, aliquota_nominal: ?float,
     *     parcela_deduzir: ?float, aliquota_efetiva: ?float, das: ?float, acima_sublimite: bool, motivo: ?string}
     */
    public function calcular(string $anexo, float $rbt12, float $receitaMes): array
    {
        if (! isset(self::ANEXOS[$anexo])) {
            throw new \InvalidArgumentException("Anexo do Simples inválido: {$anexo}. Use I, II, III, IV ou V.");
        }

        $rbtCent = (int) round($rbt12 * 100);
        $recCent = (int) round($receitaMes * 100);
        $base = [
            'anexo' => $anexo, 'rbt12' => $rbtCent / 100.0, 'receita_mes' => $recCent / 100.0,
            'faixa' => null, 'aliquota_nominal' => null, 'parcela_deduzir' => null,
            'aliquota_efetiva' => null, 'das' => null, 'acima_sublimite' => $rbtCent > self::SUBLIMITE_CENT,
            'motivo' => null,
        ];

        if ($rbtCent <= 0) {
            return ['motivo' => 'sem RBT12: início de atividade usa a receita proporcional (art. 18 §2º) — confirmar com o contador'] + $base;
        }
        if ($rbtCent > self::TETO_CENT) {
            return ['motivo' => 'RBT12 acima do teto de 4,8 milhões: fora do Simples (art. 3º, II)'] + $base;
        }

        foreach (self::ANEXOS[$anexo] as $i => [$limite, $aliqCentPp, $pd]) {
            if ($rbtCent <= $limite * 100) {
                // (RBT12 × Aliq − PD) em centavos × 10.000, para não perder a 4ª casa da alíquota.
                $numerador = $rbtCent * $aliqCentPp - $pd * 100 * 10_000;
                $denominador = $rbtCent * 10_000;

                return [
                    'faixa' => $i + 1,
                    'aliquota_nominal' => $aliqCentPp / 10_000,
                    'parcela_deduzir' => (float) $pd,
                    'aliquota_efetiva' => round($numerador / $denominador, 6),
                    'das' => round($recCent * ($numerador / $denominador)) / 100,
                ] + $base;
            }
        }

        throw new \LogicException('Faixa não encontrada para RBT12 dentro do teto.');
    }

    /**
     * Apura a competência de uma empresa: receita do mês e RBT12 dos doze meses
     * anteriores (art. 18 §1º), vendas finalizadas menos devoluções finalizadas.
     * Só lê `transactions`, sempre filtrado por `business_id` (ADR 0093).
     *
     * @return array<string, mixed>
     */
    public function apurar(int $businessId, CarbonImmutable $competencia, string $anexo): array
    {
        $inicio = $competencia->startOfMonth();
        $fim = $inicio->addMonthNoOverflow();
        $inicio12 = $inicio->subMonthsNoOverflow(12);

        $receita = fn (CarbonImmutable $de, CarbonImmutable $ate): float => $this->soma($businessId, 'sell', $de, $ate)
            - $this->soma($businessId, 'sell_return', $de, $ate);

        return $this->calcular($anexo, $receita($inicio12, $inicio), $receita($inicio, $fim)) + [
            'competencia' => $inicio->format('Y-m'),
            'rbt12_de' => $inicio12->toDateString(),
            'rbt12_ate' => $inicio->subDay()->toDateString(),
        ];
    }

    private function soma(int $businessId, string $tipo, CarbonImmutable $de, CarbonImmutable $ate): float
    {
        return (float) DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', $tipo)
            ->where('status', self::TRANSACAO_FINAL)
            ->where('transaction_date', '>=', $de->toDateString().' 00:00:00')
            ->where('transaction_date', '<', $ate->toDateString().' 00:00:00')
            ->sum('final_total');
    }
}
