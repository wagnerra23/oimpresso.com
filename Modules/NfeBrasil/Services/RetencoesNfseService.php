<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Illuminate\Support\Facades\DB;

/**
 * Retenções federais de PIS/COFINS/CSLL na NFS-e — R-NFSE-001 · R-NFSE-002
 * (playbook Fiscal, thread 23 · UC-TRB-20). Mora no NfeBrasil por decisão
 * [W] 2026-10-13 (D-NFSE-CONSOLIDA): a NFS-e consolida neste módulo.
 *
 * SÓ CALCULA. Não grava em nota, não altera a DPS do `Modules/NFSe` e não
 * gera título no Financeiro — quem consumir o resultado é outra thread.
 *
 * Lei (citada literal — lei 4 do módulo), Lei nº 10.833/2003:
 *
 *   Art. 30. "Os pagamentos efetuados pelas pessoas jurídicas a outras pessoas
 *   jurídicas de direito privado, pela prestação de serviços de limpeza,
 *   conservação, manutenção, segurança, vigilância, transporte de valores e
 *   locação de mão-de-obra, pela prestação de serviços de assessoria
 *   creditícia, mercadológica, gestão de crédito, seleção e riscos,
 *   administração de contas a pagar e a receber, bem como pela remuneração de
 *   serviços profissionais, estão sujeitos a retenção na fonte da Contribuição
 *   Social sobre o Lucro Líquido - CSLL, da COFINS e da contribuição para o
 *   PIS/PASEP."
 *
 *   Art. 31. "O valor da CSLL, da COFINS e da contribuição para o PIS/PASEP,
 *   de que trata o art. 30, será determinado mediante a aplicação, sobre o
 *   montante a ser pago, do percentual de 4,65% (quatro inteiros e sessenta e
 *   cinco centésimos por cento), correspondente à soma das alíquotas de 1% (um
 *   por cento), 3% (três por cento) e 0,65% (sessenta e cinco centésimos por
 *   cento), respectivamente."
 *
 *   Art. 31, § 3º. "Fica dispensada a retenção de valor igual ou inferior a
 *   R$ 10,00 (dez reais), exceto na hipótese de Documento de Arrecadação de
 *   Receitas Federais - DARF eletrônico efetuado por meio do Siafi." (Redação
 *   dada pela Lei nº 13.137, de 2015)
 *
 *   Art. 32. "A retenção de que trata o art. 30 não será exigida na hipótese
 *   de pagamentos efetuados a: [...] III - pessoas jurídicas optantes pelo
 *   SIMPLES."
 *
 * Fonte: planalto.gov.br/ccivil_03/leis/2003/l10.833compilado.htm, lida em 2026-10-13.
 *
 * Por que `servicoSujeito` é parâmetro: o art. 30 só alcança os serviços que
 * ele lista. Qual item da LC 116 cai na lista não está cadastrado no ERP, então
 * quem chama declara; não há palpite aqui.
 *
 * Fora daqui (declarado): IRRF (depende de tabela item × alíquota que o ERP não
 * tem), INSS 11% (cessão de mão de obra), a exceção do DARF via Siafi do § 3º
 * (só órgão público federal) e o art. 30 § 2º (tomador optante não é obrigado
 * a reter — o regime do TOMADOR não está no cadastro de contato).
 */
final class RetencoesNfseService
{
    /** Alíquotas em pontos-base (1/10000) — conta em centavos inteiros, sem float. */
    public const PIS_BP = 65;     // 0,65%
    public const COFINS_BP = 300; // 3%
    public const CSLL_BP = 100;   // 1%

    /** Art. 31 § 3º: "igual ou inferior a" dez reais (1000 centavos). */
    public const DISPENSA_CENTAVOS = 1000;

    /** `nfe_business_configs.regime` que é Simples Nacional (MEI é optante). */
    private const REGIMES_SIMPLES = ['mei', 'simples'];

    private const REGIMES_NORMAIS = ['lucro_presumido', 'lucro_real'];

    public const AVISO_DISPENSA = 'Retenção de PIS/COFINS/CSLL dispensada: valor até R$ 10,00 (Lei 10.833/2003, art. 31 § 3º).';

    public const AVISO_SIMPLES = 'Prestador optante pelo Simples: sem retenção de PIS/COFINS/CSLL (Lei 10.833/2003, art. 32, III). Confirme com o contador.';

    public const AVISO_SEM_REGIME = 'Regime tributário da empresa não configurado: retenção não calculada. Configure em Fiscal › Configuração.';

    /**
     * @param  int  $valorServicosCentavos  valor dos serviços em centavos
     * @param  bool  $tomadorPj  tomador pessoa jurídica (CNPJ)
     * @param  string|null  $regimePrestador  `nfe_business_configs.regime`
     * @param  bool  $servicoSujeito  o serviço está entre os do art. 30
     * @return array{retem: bool, pis: int, cofins: int, csll: int, total: int, dispensada: bool, aviso: ?string}
     */
    public function calcular(int $valorServicosCentavos, bool $tomadorPj, ?string $regimePrestador, bool $servicoSujeito = true): array
    {
        $nada = ['retem' => false, 'pis' => 0, 'cofins' => 0, 'csll' => 0, 'total' => 0, 'dispensada' => false, 'aviso' => null];

        if (! $tomadorPj || ! $servicoSujeito || $valorServicosCentavos <= 0) {
            return $nada;
        }

        if (in_array($regimePrestador, self::REGIMES_SIMPLES, true)) {
            return [...$nada, 'aviso' => self::AVISO_SIMPLES];
        }

        if (! in_array($regimePrestador, self::REGIMES_NORMAIS, true)) {
            return [...$nada, 'aviso' => self::AVISO_SEM_REGIME];
        }

        $pis = self::aplicar($valorServicosCentavos, self::PIS_BP);
        $cofins = self::aplicar($valorServicosCentavos, self::COFINS_BP);
        $csll = self::aplicar($valorServicosCentavos, self::CSLL_BP);
        $total = $pis + $cofins + $csll;

        if ($total <= self::DISPENSA_CENTAVOS) {
            return [...$nada, 'total' => $total, 'dispensada' => true, 'aviso' => self::AVISO_DISPENSA];
        }

        return ['retem' => true, 'pis' => $pis, 'cofins' => $cofins, 'csll' => $csll, 'total' => $total, 'dispensada' => false, 'aviso' => null];
    }

    /**
     * Mesmo cálculo, com o regime lido da config fiscal DO PRÓPRIO tenant.
     *
     * @return array{retem: bool, pis: int, cofins: int, csll: int, total: int, dispensada: bool, aviso: ?string}
     */
    public function calcularParaBusiness(int $businessId, int $valorServicosCentavos, bool $tomadorPj, bool $servicoSujeito = true): array
    {
        $regime = DB::table('nfe_business_configs')
            ->where('business_id', $businessId)
            ->value('regime');

        return $this->calcular($valorServicosCentavos, $tomadorPj, $regime, $servicoSujeito);
    }

    /** Arredonda meio centavo para cima (valor positivo): 97,5 → 98. */
    private static function aplicar(int $centavos, int $pontosBase): int
    {
        return intdiv($centavos * $pontosBase + 5000, 10000);
    }
}
