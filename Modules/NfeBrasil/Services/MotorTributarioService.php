<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Modules\NfeBrasil\Exceptions\NcmObrigatorioException;
use Modules\NfeBrasil\Exceptions\TributacaoNaoConfiguradaException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeOperacaoFiscal;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

/**
 * US-NFE-043 · Motor tributário com cascade em 4 níveis (ADR ARQ-0006).
 *
 * Fluxo:
 *   Nível 1 — override por produto (`fiscal_rule_override_id`)
 *   Nível 2 — regra exata (business + ncm + uf_origem + uf_destino)
 *   Nível 3 — regra padrão NCM (business + ncm + uf_origem, uf_destino NULL)
 *   Nível 4 — regra geral da OPERAÇÃO; na padrão (Venda), o tributacao_default da empresa
 *
 * Operação + vigência (playbook Fiscal thread 07 · D-OPERACAO · R-NFE-018..020d):
 *   - toda busca filtra a versão de regra VIGENTE na data da emissão (NULL nas pontas = sem limite);
 *   - regra sem `operacao_id` é da operação padrão; exceção de outra operação não vale aqui;
 *   - CFOP com "?" vira 5/6/7 pelo destino (`NfeOperacaoFiscal::resolverCfop`).
 *   Sem a migração 2026_10_07_000001 (schema de teste SQLite montado à mão) o motor faz o de antes.
 *
 * Quem chama: NfeService::emitir() pra montar `dets[*].icms/pis/cofins/cfop`,
 * Listener `EmitirNFeAoReceberPagamento` (US-RB-044 fase 2 futura), API
 * pública pra pré-visualização de impostos no carrinho POS.
 *
 * Multi-tenant: `business_id` SEMPRE escopa as queries.
 *
 * Performance: cache em memória do worker durante 1 venda (vendas têm
 * múltiplos itens com NCMs repetidos). Cache é por instância — caller
 * cria 1 service por venda e reusa. ADR ARQ-0006 alvo p95 < 50ms.
 */
class MotorTributarioService
{
    /** @var array<string,NfeFiscalRule|null> Memoization por chave (biz, ncm, ufO, ufD) */
    private array $cacheRegras = [];

    /** @var array<int,NfeBusinessConfig|null> Memoization por business_id */
    private array $cacheConfigs = [];

    /** @var array<string,NfeOperacaoFiscal|null> Memoization por (business, operação) */
    private array $cacheOperacoes = [];

    /**
     * Assinatura de 4 parâmetros PRESERVADA de propósito: há motores falsos em teste
     * (`new class extends MotorTributarioService`) que sobrescrevem `calcular` com ela,
     * e acrescentar parâmetro aqui — mesmo opcional — torna a sobrescrita incompatível
     * (Fatal "Declaration ... must be compatible", que matou a lane nfebrasil-pest no #8874).
     * ST e DIFAL entram por `calcularComDestino`.
     */
    public function calcular(
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
    ): TributoCalculado {
        return $this->calcularComDestino($produto, $businessId, $ufOrigem, $ufDestino);
    }

    /**
     * @param float|null $aliquotaInternaDestino alíquota interna do ICMS na UF de destino
     *        (decimal, 0.20 = 20%). Sem ela o motor NÃO calcula ST nem DIFAL — devolve 0,
     *        como hoje (lei 4: não inventa número). A tabela por UF é a thread 09.
     * @param bool|null $destinatarioContribuinte true = tem IE; false = consumidor final
     *        não contribuinte; null = desconhecido → sem DIFAL.
     * @param int|null $operacaoId operação fiscal (`nfe_operacoes_fiscais`); null = a padrão (Venda).
     * @param string|null $dataEmissao Y-m-d da emissão; escolhe a versão de regra vigente. Null = hoje.
     */
    public function calcularComDestino(
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
        ?float $aliquotaInternaDestino = null,
        ?bool $destinatarioContribuinte = null,
        ?int $operacaoId = null,
        ?string $dataEmissao = null,
    ): TributoCalculado {
        $destino = [
            'uf_origem'     => $ufOrigem,
            'uf_destino'    => $ufDestino,
            'interna'       => $aliquotaInternaDestino,
            'contribuinte'  => $destinatarioContribuinte,
        ];

        // D9 Wave 26 observabilidade — wrap calcular() em span com business_id (Tier 0).
        return OtelHelper::span('nfe.motor_tributario.calcular', [
            'business_id' => $businessId,
            'ncm'         => (string) ($produto->ncm ?? ''),
            'uf_origem'   => $ufOrigem,
            'uf_destino'  => $ufDestino,
            'has_override' => $produto->fiscal_rule_override_id !== null,
        ], function () use ($produto, $businessId, $ufOrigem, $ufDestino, $destino, $operacaoId, $dataEmissao): TributoCalculado {
            $tributo = $this->calcularInterno(
                $produto, $businessId, $ufOrigem, $ufDestino, $destino, $operacaoId, $dataEmissao,
            );

            // R-NFE-020c — só age em CFOP com "?"; os de hoje (sem "?") voltam iguais.
            $cfop = NfeOperacaoFiscal::resolverCfop($tributo->cfop, $ufOrigem, $ufDestino);

            return $cfop === $tributo->cfop
                ? $tributo
                : new TributoCalculado(...array_merge(get_object_vars($tributo), ['cfop' => $cfop]));
        });
    }

    /**
     * Implementacao interna de calcular() — envolvida pelo span OTel acima (D9 Wave 26).
     */
    private function calcularInterno(
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
        array $destino = [],
        ?int $operacaoId = null,
        ?string $dataEmissao = null,
    ): TributoCalculado {
        $versionado = NfeFiscalRule::temVersionamento();
        $data       = $dataEmissao ?? now()->toDateString();
        $operacao   = $versionado ? $this->resolverOperacao($businessId, $operacaoId) : null;

        // Nível 1 — override por produto (curto-circuito)
        if ($produto->fiscal_rule_override_id !== null) {
            $regra = NfeFiscalRule::where('business_id', $businessId)
                ->where('id', $produto->fiscal_rule_override_id)
                ->first();

            // O override aponta um id; se aquela versão não vale na data, vale a versão da
            // mesma cadeia que estiver vigente (R-NFE-019 — editar não quebra o override).
            if ($regra && $versionado && ! $regra->estaVigenteEm($data)) {
                $origem = (int) ($regra->versao_origem_id ?? $regra->id);
                $regra  = NfeFiscalRule::where('business_id', $businessId)
                    ->where(fn ($q) => $q->where('id', $origem)->orWhere('versao_origem_id', $origem))
                    ->vigenteEm($data)
                    ->orderByDesc('id')
                    ->first();
            }

            if ($regra) {
                return $this->aplicarRegra($regra, $produto, nivel: 1, destino: $destino);
            }
            // Override inválido: cai pro cascade normal (defensivo)
        }

        if (empty($produto->ncm)) {
            throw new NcmObrigatorioException(
                'Produto sem NCM cadastrado. Cadastre o NCM no produto ou vincule ' .
                'fiscal_rule_override_id pra emitir.'
            );
        }

        // Nível 2 — regra exata
        $regra = $this->buscarRegra($businessId, $produto->ncm, $ufOrigem, $ufDestino, $operacao, $versionado ? $data : null);
        if ($regra) {
            return $this->aplicarRegra($regra, $produto, nivel: 2, destino: $destino);
        }

        // Nível 3 — regra padrão NCM (uf_destino NULL)
        $regra = $this->buscarRegra($businessId, $produto->ncm, $ufOrigem, null, $operacao, $versionado ? $data : null);
        if ($regra) {
            return $this->aplicarRegra($regra, $produto, nivel: 3, destino: $destino);
        }

        // Nível 4 — regra geral da operação. Fora da padrão, NÃO cai no default de venda: a
        // operação sem regra geral é erro de configuração, não "use a da venda" (R-NFE-020b).
        if ($operacao && ! $operacao->padrao) {
            if (empty($operacao->regra_geral)) {
                throw new TributacaoNaoConfiguradaException(
                    'Operação fiscal "' . $operacao->nome . '" sem regra geral. Cadastre a regra '
                    . 'geral da operação antes de emitir.'
                );
            }

            return $this->aplicarDefaults($operacao->regra_geral, $produto, $operacao->cfop);
        }

        if ($operacao && ! empty($operacao->regra_geral)) {
            return $this->aplicarDefaults($operacao->regra_geral, $produto, $operacao->cfop);
        }

        // Padrão sem regra geral própria = o tributacao_default da empresa (R-NFE-020).
        $config = $this->buscarConfig($businessId);
        if ($config && ! empty($config->tributacao_default)) {
            return $this->aplicarDefaults($config->tributacao_default, $produto, $operacao?->cfop);
        }

        throw new TributacaoNaoConfiguradaException(
            "Business {$businessId} sem default tributário. Cadastre em " .
            "/nfe-brasil/configuracao/tributacao-default antes de emitir."
        );
    }

    /**
     * A operação pedida, sempre do próprio business (R-NFE-020d). Sem id = a padrão; se a empresa
     * ainda não tem a "Venda" cadastrada, null — e o motor se comporta como a padrão.
     */
    private function resolverOperacao(int $businessId, ?int $operacaoId): ?NfeOperacaoFiscal
    {
        $chave = $businessId . '|' . ($operacaoId ?? 'padrao');
        if (array_key_exists($chave, $this->cacheOperacoes)) {
            return $this->cacheOperacoes[$chave];
        }

        $q = NfeOperacaoFiscal::withoutGlobalScopes() // SUPERADMIN: motor roda em fila/console; o escopo é o where explícito abaixo
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');
        $operacao = $operacaoId !== null
            ? $q->where('id', $operacaoId)->first()
            : $q->where('padrao', true)->orderBy('id')->first();

        if ($operacaoId !== null && $operacao === null) {
            throw new TributacaoNaoConfiguradaException(
                "Operação fiscal {$operacaoId} não existe no business {$businessId}."
            );
        }

        return $this->cacheOperacoes[$chave] = $operacao;
    }

    private function buscarRegra(
        int $businessId,
        string $ncm,
        string $ufOrigem,
        ?string $ufDestino,
        ?NfeOperacaoFiscal $operacao = null,
        ?string $data = null,
    ): ?NfeFiscalRule {
        $cacheKey = "{$businessId}|{$ncm}|{$ufOrigem}|" . ($ufDestino ?? 'NULL')
            . '|' . ($operacao?->id ?? 'padrao') . '|' . ($data ?? '-');

        if (array_key_exists($cacheKey, $this->cacheRegras)) {
            return $this->cacheRegras[$cacheKey];
        }

        $query = NfeFiscalRule::where('business_id', $businessId)
            ->where('ncm', $ncm)
            ->where('uf_origem', $ufOrigem);

        $query = $ufDestino === null
            ? $query->whereNull('uf_destino')
            : $query->where('uf_destino', $ufDestino);

        if ($data !== null) {
            // Operação: a padrão enxerga as regras sem operação (todas as de antes da thread 07);
            // as outras só as próprias. Vigência: só a versão válida na data.
            if ($operacao !== null && ! $operacao->padrao) {
                $query->where('operacao_id', $operacao->id);
            } else {
                $query->where(fn ($q) => $operacao === null
                    ? $q->whereNull('operacao_id')
                    : $q->whereNull('operacao_id')->orWhere('operacao_id', $operacao->id));
            }

            $query->vigenteEm($data)
                ->orderByRaw('operacao_id IS NULL')
                ->orderBy('id');
        }

        return $this->cacheRegras[$cacheKey] = $query->first();
    }

    private function buscarConfig(int $businessId): ?NfeBusinessConfig
    {
        if (array_key_exists($businessId, $this->cacheConfigs)) {
            return $this->cacheConfigs[$businessId];
        }

        return $this->cacheConfigs[$businessId] = NfeBusinessConfig::where('business_id', $businessId)
            ->first();
    }

    private function aplicarRegra(
        NfeFiscalRule $regra,
        ProdutoFiscalContext $produto,
        int $nivel,
        array $destino = [],
    ): TributoCalculado {
        $extra = $this->calcularStFcpDifal($regra, $produto, $destino);

        return new TributoCalculado(
            cfop:            $regra->cfop,
            csosn:           $regra->csosn,
            cst:             $regra->cst,
            aliquota_icms:   (float) $regra->aliquota_icms,
            aliquota_pis:    (float) $regra->aliquota_pis,
            aliquota_cofins: (float) $regra->aliquota_cofins,
            aliquota_ipi:    (float) $regra->aliquota_ipi,
            valor_icms:      $this->fmt($produto->valor * (float) $regra->aliquota_icms),
            valor_pis:       $this->fmt($produto->valor * (float) $regra->aliquota_pis),
            valor_cofins:    $this->fmt($produto->valor * (float) $regra->aliquota_cofins),
            valor_ipi:       $this->fmt($produto->valor * (float) $regra->aliquota_ipi),
            nivel_usado:     $nivel,
            regra_id:        $regra->id,
            mva:             $regra->mva !== null ? (float) $regra->mva : null,
            fcp:             $regra->fcp !== null ? (float) $regra->fcp : null,
            // IBS/CBS (US-FISCAL-021): colunas nullable/default-0 em nfe_fiscal_rules.
            // Regra sem IBS/CBS configurado → alíquota 0 → valor 0 (Simples/legado).
            c_class_trib:    $regra->c_class_trib,
            cst_ibs:         $regra->cst_ibs,
            cst_cbs:         $regra->cst_cbs,
            aliquota_ibs:    (float) $regra->aliquota_ibs,
            aliquota_cbs:    (float) $regra->aliquota_cbs,
            valor_ibs:       $this->fmt($produto->valor * (float) $regra->aliquota_ibs),
            valor_cbs:       $this->fmt($produto->valor * (float) $regra->aliquota_cbs),
            base_st:         $extra['base_st'],
            valor_st:        $extra['valor_st'],
            valor_fcp:       $extra['valor_fcp'],
            valor_difal:     $extra['valor_difal'],
        );
    }

    /**
     * ICMS-ST pela MVA, FCP e DIFAL — thread 06 do playbook Fiscal (D-MOTOR [W] 2026-10-06).
     *
     * ICMS-ST (R-NFE-015) — LC 87/1996, art. 8º, II: a base da ST é "obtida pelo somatório
     * das parcelas seguintes: a) o valor da operação ou prestação própria realizada pelo
     * substituto tributário ou pelo substituído intermediário; b) o montante dos valores de
     * seguro, de frete e de outros encargos cobrados ou transferíveis aos adquirentes ou
     * tomadores de serviço; c) a margem de valor agregado, inclusive lucro, relativa às
     * operações ou prestações subseqüentes". Art. 8º, § 5º: o imposto por substituição
     * "corresponderá à diferença entre o valor resultante da aplicação da alíquota prevista
     * para as operações ou prestações internas do Estado de destino sobre a respectiva base
     * de cálculo e o valor do imposto devido pela operação ou prestação própria do substituto".
     *   base_st  = (valor + IPI) × (1 + MVA)
     *   valor_st = base_st × interna_destino − ICMS próprio      (nunca negativo)
     * Frete/seguro já estão no `valor` (base rateada por item na thread 17).
     *
     * CSOSN 500 (R-NFE-016) — "ICMS cobrado anteriormente por substituição tributária
     * (substituído) ou por antecipação": a ST já foi retida, o motor NÃO recalcula.
     *
     * FCP (R-NFE-015b) — ADCT art. 82, § 1º: "poderá ser criado adicional de até dois pontos
     * percentuais na alíquota do Imposto sobre Circulação de Mercadorias e Serviços - ICMS".
     *   valor_fcp = valor × fcp da regra (em DIFAL é o FCP da UF de destino).
     *
     * DIFAL (R-NFE-017) — CF art. 155, § 2º, VII (EC 87/2015): "nas operações e prestações que
     * destinem bens e serviços a consumidor final, contribuinte ou não do imposto, localizado
     * em outro Estado, adotar-se-á a alíquota interestadual e caberá ao Estado de localização
     * do destinatário o imposto correspondente à diferença entre a alíquota interna do Estado
     * destinatário e a alíquota interestadual"; inciso VIII, b: a responsabilidade é atribuída
     * "ao remetente, quando o destinatário não for contribuinte do imposto".
     *   valor_difal = valor × (interna_destino − alíquota ICMS da regra)
     *   só quando destinatário NÃO contribuinte e UF destino ≠ UF origem.
     * O DIFAL do Simples por UF (ADI 5.464) é a thread 29 — aqui ainda não há esse corte.
     *
     * Sem alíquota interna do destino o motor devolve ST e DIFAL = 0 (comportamento de antes):
     * nunca inventa a interna. O erro explicável "interna não cadastrada" é R-NFE-021 (thread 09).
     *
     * @param array{uf_origem?:string,uf_destino?:string,interna?:float|null,contribuinte?:bool|null} $destino
     * @return array{base_st:float,valor_st:float,valor_fcp:float,valor_difal:float}
     */
    private function calcularStFcpDifal(NfeFiscalRule $regra, ProdutoFiscalContext $produto, array $destino): array
    {
        $valor        = $produto->valor;
        $interna      = isset($destino['interna']) ? (float) $destino['interna'] : null;
        $icmsProprio  = $this->fmt($valor * (float) $regra->aliquota_icms);
        $ipi          = $this->fmt($valor * (float) $regra->aliquota_ipi);
        $mva          = (float) ($regra->mva ?? 0);
        $fcp          = (float) ($regra->fcp ?? 0);

        $baseSt = 0.0;
        $valorSt = 0.0;
        $stRetidaAntes = (string) $regra->csosn === '500';
        if ($mva > 0 && $interna !== null && ! $stRetidaAntes) {
            $baseSt  = $this->fmt(($valor + $ipi) * (1 + $mva));
            $valorSt = max(0.0, $this->fmt($baseSt * $interna - $icmsProprio));
        }

        $valorDifal = 0.0;
        $interestadual = ($destino['uf_origem'] ?? '') !== ($destino['uf_destino'] ?? '');
        if ($interna !== null && ($destino['contribuinte'] ?? null) === false && $interestadual) {
            $valorDifal = max(0.0, $this->fmt($valor * ($interna - (float) $regra->aliquota_icms)));
        }

        return [
            'base_st'     => $baseSt,
            'valor_st'    => $valorSt,
            'valor_fcp'   => $fcp > 0 ? $this->fmt($valor * $fcp) : 0.0,
            'valor_difal' => $valorDifal,
        ];
    }

    /**
     * @param array<string,mixed> $defaults
     */
    private function aplicarDefaults(array $defaults, ProdutoFiscalContext $produto, ?string $cfopOperacao = null): TributoCalculado
    {
        // A operação com CFOP próprio decide o CFOP da regra geral (o "?" é resolvido no fim).
        $cfop          = $cfopOperacao !== null && $cfopOperacao !== ''
            ? $cfopOperacao
            : (string) ($defaults['cfop'] ?? '5102');
        $csosn         = isset($defaults['csosn']) ? (string) $defaults['csosn'] : null;
        $cst           = isset($defaults['cst']) ? (string) $defaults['cst'] : null;
        $aliqIcms      = (float) ($defaults['aliquota_icms'] ?? 0);
        $aliqPis       = (float) ($defaults['aliquota_pis'] ?? 0);
        $aliqCofins    = (float) ($defaults['aliquota_cofins'] ?? 0);
        $aliqIpi       = (float) ($defaults['aliquota_ipi'] ?? 0);
        // IBS/CBS defaults do business (US-FISCAL-021) — ausentes hoje (Simples) → null/0.
        $cClassTrib    = isset($defaults['c_class_trib']) ? (string) $defaults['c_class_trib'] : null;
        $cstIbs        = isset($defaults['cst_ibs']) ? (string) $defaults['cst_ibs'] : null;
        $cstCbs        = isset($defaults['cst_cbs']) ? (string) $defaults['cst_cbs'] : null;
        $aliqIbs       = (float) ($defaults['aliquota_ibs'] ?? 0);
        $aliqCbs       = (float) ($defaults['aliquota_cbs'] ?? 0);

        return new TributoCalculado(
            cfop:            $cfop,
            csosn:           $csosn,
            cst:             $cst,
            aliquota_icms:   $aliqIcms,
            aliquota_pis:    $aliqPis,
            aliquota_cofins: $aliqCofins,
            aliquota_ipi:    $aliqIpi,
            valor_icms:      $this->fmt($produto->valor * $aliqIcms),
            valor_pis:       $this->fmt($produto->valor * $aliqPis),
            valor_cofins:    $this->fmt($produto->valor * $aliqCofins),
            valor_ipi:       $this->fmt($produto->valor * $aliqIpi),
            nivel_usado:     4,
            regra_id:        null,
            c_class_trib:    $cClassTrib,
            cst_ibs:         $cstIbs,
            cst_cbs:         $cstCbs,
            aliquota_ibs:    $aliqIbs,
            aliquota_cbs:    $aliqCbs,
            valor_ibs:       $this->fmt($produto->valor * $aliqIbs),
            valor_cbs:       $this->fmt($produto->valor * $aliqCbs),
        );
    }

    /** Arredonda em 2 casas (padrão SEFAZ pra valores monetários) */
    private function fmt(float $v): float
    {
        return round($v, 2);
    }
}
