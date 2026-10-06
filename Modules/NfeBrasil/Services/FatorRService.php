<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fator R do Simples Nacional — R-NFE-029 · UC-TRB-22 (playbook Fiscal, thread 24).
 *
 * SÓ INFORMA. Não muda regime, não muda anexo cadastrado, não calcula DAS.
 * Quem decide o anexo na apuração é o contador; este service mostra a conta
 * e avisa quando ela cruza 28%.
 *
 * Lei (citada literal — lei 4 do módulo), LC 123/2006, art. 18:
 *
 *   § 5º-J. "As atividades de prestação de serviços a que se refere o § 5º-I
 *   serão tributadas na forma do Anexo III desta Lei Complementar caso a razão
 *   entre a folha de salários e a receita bruta da pessoa jurídica seja igual
 *   ou superior a 28% (vinte e oito por cento)." (Incluído pela Lei
 *   Complementar nº 155, de 2016)
 *
 *   § 5º-K. "Para o cálculo da razão a que se referem os §§ 5º-J e 5º-M, serão
 *   considerados, respectivamente, os montantes pagos e auferidos nos doze
 *   meses anteriores ao período de apuração para fins de enquadramento no
 *   regime tributário do Simples Nacional." (Incluído pela Lei Complementar
 *   nº 155, de 2016)
 *
 *   § 5º-M. "Quando a relação entre a folha de salários e a receita bruta da
 *   microempresa ou da empresa de pequeno porte for inferior a 28% (vinte e
 *   oito por cento), serão tributadas na forma do Anexo V desta Lei
 *   Complementar as atividades previstas: [...]" (Incluído pela Lei
 *   Complementar nº 155, de 2016)
 *
 *   § 24. "Para efeito de aplicação do § 5º-K, considera-se folha de salários,
 *   incluídos encargos, o montante pago, nos doze meses anteriores ao período
 *   de apuração, a título de remunerações a pessoas físicas decorrentes do
 *   trabalho, acrescido do montante efetivamente recolhido a título de
 *   contribuição patronal previdenciária e FGTS, incluídas as retiradas de
 *   pró-labore." (Redação dada pela Lei Complementar nº 155, de 2016)
 *
 * Fonte: planalto.gov.br/ccivil_03/leis/lcp/lcp123.htm, lida em 2026-10-06.
 *
 * Por que a folha do ERP é PARCIAL: a folha do Essentials é gerencial (soma
 * ganhos, subtrai deduções; não calcula INSS, FGTS, nem pró-labore — ver
 * `payroll_gerencial_aviso`). O § 24 manda somar encargos patronais, FGTS e
 * pró-labore. Por isso `apurar()` aceita o complemento informado pelo
 * contador e, sem ele, devolve `folha_parcial = true`: a razão sai subestimada
 * e o anexo pode aparecer como V quando é III. Quem renderiza mostra o aviso.
 */
final class FatorRService
{
    /** 28% como fração inteira (28/100), comparada em centavos — sem float na fronteira. */
    private const LIMITE_NUMERADOR = 28;
    private const LIMITE_DENOMINADOR = 100;

    /** Status de transação finalizada no core (UltimatePOS `transactions`), não vocabulário fiscal. */
    private const TRANSACAO_FINAL = 'final';

    public const PENDENCIA_ABAIXO = 'Fator R abaixo de 28%';
    public const PENDENCIA_ACIMA = 'Fator R atingiu 28%';

    /**
     * Razão folha ÷ receita bruta e o anexo que ela indica.
     *
     * Receita zero (ou negativa) não divide: devolve `sem_dado = true`,
     * `razao = null`, `anexo = null`.
     *
     * @return array{folha: float, receita: float, razao: ?float, anexo: ?string, sem_dado: bool}
     */
    public function calcular(float $folha12m, float $receita12m): array
    {
        $folhaCent = (int) round($folha12m * 100);
        $receitaCent = (int) round($receita12m * 100);

        if ($receitaCent <= 0) {
            return [
                'folha' => $folhaCent / 100.0,
                'receita' => $receitaCent / 100.0,
                'razao' => null,
                'anexo' => null,
                'sem_dado' => true,
            ];
        }

        // § 5º-J "igual ou superior a 28%" → III · § 5º-M "inferior a 28%" → V.
        // Comparação em inteiros: folha × 100 ≥ receita × 28.
        $anexo = $folhaCent * self::LIMITE_DENOMINADOR >= $receitaCent * self::LIMITE_NUMERADOR
            ? 'III'
            : 'V';

        return [
            'folha' => $folhaCent / 100.0,
            'receita' => $receitaCent / 100.0,
            'razao' => round($folhaCent / $receitaCent, 4),
            'anexo' => $anexo,
            'sem_dado' => false,
        ];
    }

    /**
     * Pendência da Saúde fiscal quando o anexo indicado muda entre duas apurações.
     * Sem dado em qualquer ponta = sem pendência (não se afirma cruzamento sem conta).
     *
     * @param  array{anexo: ?string}|null  $anterior
     * @param  array{anexo: ?string}  $atual
     */
    public function pendencia(?array $anterior, array $atual): ?string
    {
        $de = $anterior['anexo'] ?? null;
        $para = $atual['anexo'] ?? null;

        if ($de === null || $para === null || $de === $para) {
            return null;
        }

        return $para === 'V' ? self::PENDENCIA_ABAIXO : self::PENDENCIA_ACIMA;
    }

    /**
     * Apura o Fator R de uma empresa para a competência (mês de apuração).
     *
     * Janela (§ 5º-K): os doze meses ANTERIORES à competência — para 2026-10,
     * de 2025-10-01 a 2026-09-30. A competência não entra.
     *
     * Receita bruta: vendas finalizadas (`sell`/`final`) menos devoluções
     * finalizadas (`sell_return`/`final`), pela data da transação.
     * Folha: folha gerencial finalizada (`payroll`/`final`) + o complemento do
     * § 24 informado pelo contador (encargos patronais, FGTS e pró-labore).
     *
     * Só lê `transactions`, sempre filtrado por `business_id` (ADR 0093).
     *
     * @return array{folha: float, receita: float, razao: ?float, anexo: ?string, sem_dado: bool, folha_parcial: bool, de: string, ate: string}
     */
    public function apurar(int $businessId, CarbonImmutable $competencia, ?float $complementoFolha12m = null): array
    {
        $inicioCompetencia = $competencia->startOfMonth();
        $de = $inicioCompetencia->subMonthsNoOverflow(12);
        $ate = $inicioCompetencia->subDay();

        $soma = fn (string $tipo): float => (float) DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', $tipo)
            ->where('status', self::TRANSACAO_FINAL)
            ->where('transaction_date', '>=', $de->toDateString().' 00:00:00')
            ->where('transaction_date', '<', $inicioCompetencia->toDateString().' 00:00:00')
            ->sum('final_total');

        $receita = $soma('sell') - $soma('sell_return');
        $folha = $soma('payroll') + ($complementoFolha12m ?? 0.0);

        return $this->calcular($folha, $receita) + [
            'folha_parcial' => $complementoFolha12m === null,
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
        ];
    }
}
