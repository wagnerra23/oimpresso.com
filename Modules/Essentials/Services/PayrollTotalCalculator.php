<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\Utils\Util;

/**
 * Dono ÚNICO do total de um contracheque, do lado do SERVIDOR.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Até 2026-09-05 o total de cada contracheque era somado no NAVEGADOR
 * (Modules/Essentials/Resources/views/payroll/form_script.blade.php, calculateTotal:
 * `var gross_amount = total + total_allowance - total_deduction`) e escrito num hidden
 * `payrolls[<id>][final_total]` com `.val()` cru. O servidor persistia o que recebia:
 * `PayrollController::store()` fazia `$payroll['total_before_tax'] = $payroll['final_total']`
 * e repassava o array inteiro pro `Transaction::create()`. Ou seja: gravava em `transactions`
 * — a MESMA tabela das vendas — um valor escolhido pelo cliente.
 *
 * Este serviço recalcula o total a partir dos insumos que o servidor já tem
 * (duração × valor-por-unidade + verbas). A divergência contra o que o formulário mandou é
 * reconciliada por `reconciliar()` — nunca aceita em silêncio.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A PEGADINHA DO num_uf — MEDIDA, não deduzida (2026-09-05, CT 100, contra o Util REAL)
 * ─────────────────────────────────────────────────────────────────────────────
 * O reflexo de "passar tudo por num_uf" seria PIOR que não passar:
 *
 *   entrada      origem                          num_uf()   cast DECIMAL  veredito
 *   3456.79      float JS, 2 casas               3456.79    3456.79       igual
 *   330.033      float JS, 3 casas               330033     330.033       DIVERGE 1000x
 *   1234.567     float JS, 3 casas               1234567    1234.567      DIVERGE 1000x
 *   0.125        float JS, 3 casas               125        0.125         DIVERGE 1000x
 *   204.99605    float JS, 5 casas (inc. 06-05)  204.99605  204.99605     igual
 *
 * A heurística pt-BR do `num_uf` trata "1 ponto + EXATAMENTE 3 dígitos" como separador de
 * MILHAR — regra correta para "25.000", e destrutiva para um float cru de 3 casas. E o JS
 * PRODUZ 3 casas: `__calculate_amount('percentage', ...)` faz `Decimal.mul(...).toNumber()`
 * sem arredondar, e `let total = total_duration * amount_per_unit_duration` não é arredondado
 * na variável (só o input visível recebe `__write_number`).
 *
 * Daí as duas regras deste arquivo:
 *   - campo que chega FORMATADO em pt-BR (verba, percentual, valor-por-unidade — escritos por
 *     `__write_number`) → `num_uf`, que é o parser certo pra ele;
 *   - campo que chega CRU do `.val(float)` (o `final_total`) → NUNCA `num_uf`; ele não é
 *     parseado para virar valor, só para ser COMPARADO com o número do servidor.
 *
 * @see memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE" (regra mestre)
 * @see tests/Feature/Calculo/CalculoValorPayrollTest.php (as duas provas)
 */
class PayrollTotalCalculator
{
    /** Divergência até este valor absoluto é ruído de arredondamento — segue em silêncio. */
    public const TOLERANCIA = 0.01;

    /** Acima desta razão a divergência tem a FORMA de inflação (incidente 2026-06-05): recusa. */
    public const RAZAO_RECUSA = 10.0;

    public function __construct(private Util $util) {}

    /**
     * Recompõe o contracheque a partir dos insumos do formulário.
     *
     * Espelha `calculateTotal()` do form_script, mas do lado do servidor e sem confiar em
     * nenhum total que o navegador tenha somado:
     *   base  = duração × valor por unidade de duração
     *   verba = base × percentual ÷ 100  (quando `type` = 'percent'; o percentual manda)
     *         = valor informado          (quando 'fixed')
     *   total = base + Σ adicionais − Σ descontos
     *
     * @param  array<string,mixed>  $payroll  o item cru de `payrolls[]` do request
     * @return array{base:float,adicionais:list<array{nome:string,valor:float,tipo:string,percentual:float}>,descontos:list<array{nome:string,valor:float,tipo:string,percentual:float}>,total_adicionais:float,total_descontos:float,total:float}
     */
    public function calcular(array $payroll, ?int $precisao = null): array
    {
        $precisao ??= (int) session('business.currency_precision', 2);

        $duracao = (float) $this->util->num_uf($payroll['essentials_duration'] ?? 0);
        $porUnidade = (float) $this->util->num_uf($payroll['essentials_amount_per_unit_duration'] ?? 0);
        $base = round($duracao * $porUnidade, $precisao);

        $adicionais = $this->linhas($payroll, 'allowance', $base, $precisao);
        $descontos = $this->linhas($payroll, 'deduction', $base, $precisao);

        $totalAdicionais = round(array_sum(array_column($adicionais, 'valor')), $precisao);
        $totalDescontos = round(array_sum(array_column($descontos, 'valor')), $precisao);

        return [
            'base' => $base,
            'adicionais' => $adicionais,
            'descontos' => $descontos,
            'total_adicionais' => $totalAdicionais,
            'total_descontos' => $totalDescontos,
            'total' => round($base + $totalAdicionais - $totalDescontos, $precisao),
        ];
    }

    /**
     * Confronta o total do servidor com o que o formulário mandou.
     *
     * O valor do servidor SEMPRE vence — este método existe para dizer o que aconteceu, não
     * para escolher. `recusar` = true quando a divergência tem a forma de inflação.
     *
     * ⚠️ `$recebidoCru` é convertido com cast `(float)`, NUNCA com `num_uf`: ele chega do
     *    `.val(float)` do navegador, em notação en-US (ponto decimal), e a heurística pt-BR
     *    do `num_uf` inflaria 1000× um valor de 3 casas (ver o docblock da classe).
     *
     * @return array{divergente:bool,recusar:bool,servidor:float,recebido:float,delta:float,razao:float|null}
     */
    public function reconciliar(float $totalServidor, mixed $recebidoCru): array
    {
        $recebido = is_numeric($recebidoCru) ? (float) $recebidoCru : 0.0;
        $delta = round($totalServidor - $recebido, 4);
        $razao = abs($totalServidor) > 0.0001 ? abs($recebido / $totalServidor) : null;

        $divergente = abs($delta) > self::TOLERANCIA;
        $recusar = $divergente
            && $razao !== null
            && ($razao >= self::RAZAO_RECUSA || $razao <= 1 / self::RAZAO_RECUSA);

        return [
            'divergente' => $divergente,
            'recusar' => $recusar,
            'servidor' => $totalServidor,
            'recebido' => $recebido,
            'delta' => $delta,
            'razao' => $razao,
        ];
    }

    /**
     * @param  string  $prefixo  'allowance' ou 'deduction'
     * @return list<array{nome:string,valor:float,tipo:string,percentual:float}>
     */
    private function linhas(array $payroll, string $prefixo, float $base, int $precisao): array
    {
        $nomes = $payroll[$prefixo.'_names'] ?? [];
        $valores = $payroll[$prefixo.'_amounts'] ?? [];
        $tipos = $payroll[$prefixo.'_types'] ?? [];
        $percentuais = $payroll[$prefixo.'_percent'] ?? [];

        if (! is_array($nomes) || ! is_array($valores)) {
            return [];
        }

        $saida = [];
        foreach ($valores as $i => $valorCru) {
            // Mesma guarda de getAllowanceAndDeductionJson: linha sem nome não conta.
            if (empty($nomes[$i])) {
                continue;
            }

            $tipo = (string) ($tipos[$i] ?? 'fixed');
            $percentual = ! empty($percentuais[$i]) ? (float) $this->util->num_uf($percentuais[$i]) : 0.0;

            // 'percent': o PERCENTUAL é a fonte de verdade — o valor da linha é derivado pelo
            // servidor, não aceito do formulário. 'fixed': o valor informado é o dado.
            $valor = $tipo === 'percent'
                ? round($base * $percentual / 100, $precisao)
                : round((float) $this->util->num_uf($valorCru), $precisao);

            $saida[] = [
                'nome' => (string) $nomes[$i],
                'valor' => $valor,
                'tipo' => $tipo,
                'percentual' => $percentual,
            ];
        }

        return $saida;
    }
}
