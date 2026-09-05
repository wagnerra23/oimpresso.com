<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

/**
 * SalesTargetFaixaValidator — regra pura das faixas de meta de venda (HRM-O6 / PR-4, achado A5).
 *
 * As colunas target_start/target_end de essentials_user_sales_targets NÃO são datas:
 * são a FAIXA DE VALOR de venda acumulada do colaborador no período (decimal 22,4 —
 * migration 2021_09_28_091541), e commission_percent é o percentual pago quando o
 * total vendido cai dentro dela.
 *
 * O consumidor que transforma isso em dinheiro é PayrollController (~L258):
 *
 *     EssentialsUserSalesTarget::where('user_id', $employee->id)
 *         ->where('target_start', '<=', $total_sales)
 *         ->where('target_end',   '>=', $total_sales)
 *         ->first();                       // <- sem orderBy
 *     $comissao = calc_percentage($total_sales, $target->commission_percent);
 *
 * Daí as três regras, cada uma derivada de um defeito MEDIDO nessa query:
 *
 *  1. end > start — faixa invertida nunca casa (exigiria total >= start E <= end com
 *     end < start): a comissão devida some em silêncio, sem erro nenhum.
 *  2. sem sobreposição — duas faixas cobrindo o mesmo total fazem o ->first() SEM
 *     orderBy escolher uma arbitrariamente: a mesma venda paga percentuais diferentes
 *     conforme a ordem que o MySQL devolver. Não-determinismo em VALOR.
 *  3. 0 <= commission_percent <= 100 — calc_percentage() é ($total * $pct / 100) sem
 *     teto: 150 paga 1,5x o faturado; negativo vira abatimento na folha.
 *
 * A comparação de sobreposição é INCLUSIVA nos dois extremos porque a query é (<= e >=):
 * [0, 1000] e [1000, 2000] ambas casam um total de exatamente 1000, então encostar
 * ponta com ponta JÁ é o não-determinismo do item 2. Faixas contíguas se escrevem
 * [0, 1000] e [1000,01, 2000].
 *
 * Puro de propósito — sem Request, sem Eloquent, sem sessão: recebe faixas já
 * normalizadas (o controller aplica num_uf antes) e devolve mensagens. Isso deixa a
 * regra exercitável sem banco e mantém o pipeline de conversão de número intocado
 * (a heurística pt-BR de Util::num_uf continua sendo o único ponto que interpreta o
 * texto digitado — ver incidente 2026-06-05 em memory/proibicoes.md).
 *
 * @see \Modules\Essentials\Http\Controllers\SalesTargetController::saveSalesTarget
 * @see \Modules\Essentials\Http\Controllers\PayrollController (consumidor do valor)
 */
final class SalesTargetFaixaValidator
{
    public const COMISSAO_MIN = 0.0;

    public const COMISSAO_MAX = 100.0;

    /**
     * Valida o CONJUNTO final de faixas (as editadas + as novas, juntas).
     *
     * @param  list<array{rotulo: string, start: float, end: float, commission: float}>  $faixas
     * @return list<string> mensagens de erro em PT-BR; vazio = conjunto válido
     */
    public static function erros(array $faixas): array
    {
        $erros = [];

        foreach ($faixas as $faixa) {
            $rotulo = $faixa['rotulo'];

            if ($faixa['end'] <= $faixa['start']) {
                $erros[] = sprintf(
                    'Faixa %s: o valor final (%s) precisa ser MAIOR que o inicial (%s).',
                    $rotulo,
                    self::moeda($faixa['end']),
                    self::moeda($faixa['start'])
                );
            }

            if ($faixa['commission'] < self::COMISSAO_MIN || $faixa['commission'] > self::COMISSAO_MAX) {
                $erros[] = sprintf(
                    'Faixa %s: a comissão (%s%%) precisa ficar entre %d%% e %d%%.',
                    $rotulo,
                    self::numero($faixa['commission']),
                    (int) self::COMISSAO_MIN,
                    (int) self::COMISSAO_MAX
                );
            }
        }

        $total = count($faixas);
        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                if (self::sobrepoem($faixas[$i], $faixas[$j])) {
                    $erros[] = sprintf(
                        'Faixas %s e %s se sobrepõem (%s–%s e %s–%s): um mesmo total de vendas cairia nas duas e a comissão paga ficaria indefinida.',
                        $faixas[$i]['rotulo'],
                        $faixas[$j]['rotulo'],
                        self::moeda($faixas[$i]['start']),
                        self::moeda($faixas[$i]['end']),
                        self::moeda($faixas[$j]['start']),
                        self::moeda($faixas[$j]['end'])
                    );
                }
            }
        }

        return $erros;
    }

    /**
     * Sobreposição inclusiva — espelha o (target_start <= X AND target_end >= X) do
     * PayrollController. Faixa degenerada/invertida não entra aqui: o erro dela já é
     * o item 1, e cruzá-la produziria mensagem confusa sobre um intervalo vazio.
     */
    private static function sobrepoem(array $a, array $b): bool
    {
        if ($a['end'] <= $a['start'] || $b['end'] <= $b['start']) {
            return false;
        }

        return $a['start'] <= $b['end'] && $b['start'] <= $a['end'];
    }

    private static function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    private static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
    }
}
