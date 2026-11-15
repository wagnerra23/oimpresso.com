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
        return $this->calcularComCrt(
            $produto, $businessId, $ufOrigem, $ufDestino,
            $aliquotaInternaDestino, $destinatarioContribuinte, $operacaoId, $dataEmissao, null,
        );
    }

    /**
     * P-155 · o mesmo cálculo de `calcularComDestino`, para o local que emite com regime próprio
     * (Sabor Brasil: um CNPJ no Simples e outro no regime normal na mesma empresa). Com o CRT do
     * local, o ST retido sai do código do regime dele (CSOSN 500 no Simples, CST 60 no normal) e o
     * "é Simples" do DIFAL também.
     *
     * Método à parte, e não parâmetro novo em `calcularComDestino`: três testes sobrescrevem
     * aquele método com a assinatura de hoje, e acrescentar parâmetro tornaria a sobrescrita
     * incompatível (o mesmo Fatal do `calcular` no #8874).
     */
    public function calcularComRegimeDoLocal(
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
        int $crtEmitente,
        ?float $aliquotaInternaDestino = null,
        ?bool $destinatarioContribuinte = null,
    ): TributoCalculado {
        return $this->calcularComCrt(
            $produto, $businessId, $ufOrigem, $ufDestino,
            $aliquotaInternaDestino, $destinatarioContribuinte, null, null, $crtEmitente,
        );
    }

    /**
     * Item 2 do Sabor Brasil · cálculo dentro de uma operação fiscal que não é a padrão (ex. a
     * bonificação). Vale a regra da operação para o NCM; sem regra dela (o motor caiu no default da
     * empresa, nível 4, ou nem default achou), vale a da venda, como antes. Assim a operação só
     * precisa das regras que mudam.
     *
     * Método à parte pelo mesmo motivo do `calcularComRegimeDoLocal`: testes sobrescrevem os outros.
     */
    public function calcularNaOperacao(
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
        int $operacaoId,
        ?int $crtEmitente = null,
        ?float $aliquotaInternaDestino = null,
        ?bool $destinatarioContribuinte = null,
    ): TributoCalculado {
        $semOperacao = fn () => $this->calcularComCrt(
            $produto, $businessId, $ufOrigem, $ufDestino,
            $aliquotaInternaDestino, $destinatarioContribuinte, null, null, $crtEmitente,
        );

        // Natureza criada pela tela (onda 4): o resultado dela é nível 4 por construção, e a recusa
        // é o aviso de que falta fonte. Cair na venda aqui calcularia a nota como venda em silêncio.
        $operacao = NfeFiscalRule::temVersionamento() ? $this->resolverOperacao($businessId, $operacaoId) : null;
        if ($operacao !== null && empty($operacao->regra_geral) && ! empty($operacao->config_tela)) {
            return $this->calcularComCrt(
                $produto, $businessId, $ufOrigem, $ufDestino,
                $aliquotaInternaDestino, $destinatarioContribuinte, $operacaoId, null, $crtEmitente,
            );
        }

        try {
            $tributo = $this->calcularComCrt(
                $produto, $businessId, $ufOrigem, $ufDestino,
                $aliquotaInternaDestino, $destinatarioContribuinte, $operacaoId, null, $crtEmitente,
            );
        } catch (TributacaoNaoConfiguradaException) {
            return $semOperacao();
        }

        return $tributo->nivel_usado >= 4 ? $semOperacao() : $tributo;
    }

    private function calcularComCrt(
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
        ?float $aliquotaInternaDestino,
        ?bool $destinatarioContribuinte,
        ?int $operacaoId,
        ?string $dataEmissao,
        ?int $crtEmitente,
    ): TributoCalculado {
        $destino = [
            'uf_origem'     => $ufOrigem,
            'uf_destino'    => $ufDestino,
            'interna'       => $aliquotaInternaDestino,
            'contribuinte'  => $destinatarioContribuinte,
            'crt'           => $crtEmitente,
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
        $crt = isset($destino['crt']) ? (int) $destino['crt'] : null;
        $regra = $this->buscarRegra($businessId, $produto->ncm, $ufOrigem, $ufDestino, $operacao, $versionado ? $data : null, $crt);
        if ($regra) {
            return $this->aplicarRegra($regra, $produto, nivel: 2, destino: $destino);
        }

        // Nível 3 — regra padrão NCM (uf_destino NULL)
        $regra = $this->buscarRegra($businessId, $produto->ncm, $ufOrigem, null, $operacao, $versionado ? $data : null, $crt);
        if ($regra) {
            return $this->aplicarRegra($regra, $produto, nivel: 3, destino: $destino);
        }

        // Nível 4 — regra geral da operação. Fora da padrão, NÃO cai no default de venda: a
        // operação sem regra geral é erro de configuração, não "use a da venda" (R-NFE-020b).
        if ($operacao && ! $operacao->padrao) {
            // Natureza criada pela tela da Tributação (onda 4): guarda a ORIGEM das alíquotas em
            // `config_tela`, não números. Antes desta onda este caminho lançava a exceção abaixo,
            // então nenhuma nota que já sai passa por aqui.
            if (empty($operacao->regra_geral) && ! empty($operacao->config_tela)) {
                return $this->aplicarNaturezaTela($operacao, $produto, $businessId, $ufOrigem, $ufDestino, $destino, $data);
            }
            if (empty($operacao->regra_geral)) {
                throw new TributacaoNaoConfiguradaException(
                    'Operação fiscal "' . $operacao->nome . '" sem regra geral. Cadastre a regra '
                    . 'geral da operação antes de emitir.'
                );
            }

            return $this->aplicarDefaults($operacao->regra_geral, $produto, $operacao->cfop, $businessId, $destino);
        }

        if ($operacao && ! empty($operacao->regra_geral)) {
            return $this->aplicarDefaults($operacao->regra_geral, $produto, $operacao->cfop, $businessId, $destino);
        }

        // Padrão sem regra geral própria = o tributacao_default da empresa (R-NFE-020).
        $config = $this->buscarConfig($businessId);
        if ($config && ! empty($config->tributacao_default)) {
            return $this->aplicarDefaults($config->tributacao_default, $produto, $operacao?->cfop, $businessId, $destino);
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
        ?int $crt = null,
    ): ?NfeFiscalRule {
        $cacheKey = "{$businessId}|{$ncm}|{$ufOrigem}|" . ($ufDestino ?? 'NULL')
            . '|' . ($operacao?->id ?? 'padrao') . '|' . ($data ?? '-') . '|' . ($crt ?? '-');

        if (array_key_exists($cacheKey, $this->cacheRegras)) {
            return $this->cacheRegras[$cacheKey];
        }

        $query = NfeFiscalRule::where('business_id', $businessId)
            ->where('ncm', $ncm)
            ->where('uf_origem', $ufOrigem);

        $query = $ufDestino === null
            ? $query->whereNull('uf_destino')
            : $query->where('uf_destino', $ufDestino);

        // P-155 · regra marcada com um regime (CRT) só vale para o local desse regime, e ganha da
        // regra sem regime. Emitente sem CRT próprio usa só as regras sem regime — as de sempre.
        if (NfeFiscalRule::temRegraPorRegime()) {
            if ($crt === null) {
                $query->whereNull('crt');
            } else {
                $query->where(fn ($q) => $q->whereNull('crt')->orWhere('crt', $crt))
                    ->orderByRaw('crt IS NULL');
            }
        }

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
        $extra = $this->calcularStFcpDifal([
            'business_id'   => (int) $regra->business_id,
            'aliquota_icms' => (float) $regra->aliquota_icms,
            'aliquota_ipi'  => (float) $regra->aliquota_ipi,
            'mva'           => $regra->mva,
            'fcp'           => $regra->fcp,
            'csosn'         => $regra->csosn,
            'cst'           => $regra->cst,
        ], $produto, $destino);

        // Benefício fiscal — redução da base do ICMS (thread 25 · R-NFE-030).
        //   base_icms  = valor × (1 − reducao_base)
        //   valor_icms = base_icms × aliquota_icms
        // O percentual e o ato legal que o concede vêm da regra (`reducao_base` +
        // `beneficio_fundamento`, citado pelo contador — lei 4: o motor não inventa benefício).
        // Sem redução, a fórmula de antes fica intacta (valor × alíquota), byte a byte.
        // Fora deste item: ST e DIFAL continuam sobre a base cheia — ver `_saida-25.md`.
        $reducao   = (float) ($regra->reducao_base ?? 0);
        $baseIcms  = $reducao > 0 ? $this->fmt($produto->valor * (1 - $reducao)) : null;
        $valorIcms = $baseIcms !== null
            ? $this->fmt($baseIcms * (float) $regra->aliquota_icms)
            : $this->fmt($produto->valor * (float) $regra->aliquota_icms);
        $cBenef    = trim((string) ($regra->c_benef ?? ''));

        return new TributoCalculado(
            cfop:            $regra->cfop,
            csosn:           $regra->csosn,
            cst:             $regra->cst,
            aliquota_icms:   (float) $regra->aliquota_icms,
            aliquota_pis:    (float) $regra->aliquota_pis,
            aliquota_cofins: (float) $regra->aliquota_cofins,
            aliquota_ipi:    (float) $regra->aliquota_ipi,
            valor_icms:      $valorIcms,
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
            aviso:           $extra['aviso'],
            reducao_base:    $reducao > 0 ? $reducao : 0.0,
            base_icms:       $baseIcms,
            c_benef:         $cBenef !== '' ? $cBenef : null,
            cst_pis:         $this->cstPisCofins($regra->cst_pis ?? null),
            cst_cofins:      $this->cstPisCofins($regra->cst_cofins ?? null),
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
     * Recebe os números da tributação, não a regra: o nível 4 (regra geral da operação ou
     * tributacao_default da empresa) não tem linha em nfe_fiscal_rules e passa pelo mesmo cálculo.
     * Antes disso o nível 4 pulava este método e a nota interestadual a consumidor saía com o
     * grupo de DIFAL zerado (sonda do PR #10377).
     *
     * @param array{business_id:int,aliquota_icms:float,aliquota_ipi:float,mva:mixed,fcp:mixed,csosn:?string,cst:?string} $t
     * @param array{uf_origem?:string,uf_destino?:string,interna?:float|null,contribuinte?:bool|null,crt?:int|null} $destino
     * @return array{base_st:float,valor_st:float,valor_fcp:float,valor_difal:float,aviso:string|null}
     */
    private function calcularStFcpDifal(array $t, ProdutoFiscalContext $produto, array $destino): array
    {
        $regra = (object) $t;
        $valor        = $produto->valor;
        $interna      = isset($destino['interna']) ? (float) $destino['interna'] : null;
        $icmsProprio  = $this->fmt($valor * (float) $regra->aliquota_icms);
        $ipi          = $this->fmt($valor * (float) $regra->aliquota_ipi);
        $mva          = (float) ($regra->mva ?? 0);
        $fcp          = (float) ($regra->fcp ?? 0);

        $baseSt = 0.0;
        $valorSt = 0.0;
        // P-155: com o CRT do local, vale o código do regime dele — CSOSN 500 no Simples, CST 60 no
        // regime normal. A mesma regra pode trazer os dois (um CNPJ de cada regime na empresa). Sem
        // CRT, como antes.
        $crt = isset($destino['crt']) ? (int) $destino['crt'] : null;
        $stRetidaAntes = $crt === null
            ? (string) $regra->csosn === '500'
            : ($crt === 3 ? (string) $regra->cst === '60' : (string) $regra->csosn === '500');
        if ($mva > 0 && $interna !== null && ! $stRetidaAntes) {
            $baseSt  = $this->fmt(($valor + $ipi) * (1 + $mva));
            $valorSt = max(0.0, $this->fmt($baseSt * $interna - $icmsProprio));
        }

        $valorDifal = 0.0;
        $interestadual = ($destino['uf_origem'] ?? '') !== ($destino['uf_destino'] ?? '');
        if ($interna !== null && ($destino['contribuinte'] ?? null) === false && $interestadual) {
            $valorDifal = max(0.0, $this->fmt($valor * ($interna - (float) $regra->aliquota_icms)));
        }

        // Thread 29 · R-NFE-035 (D-DIFAL-UF, [W] 2026-10-06): empresa do Simples não recolhe o DIFAL
        // na venda a consumidor de outra UF — STF ADI 5.464 suspendeu a cláusula nona do Convênio
        // ICMS 93/2015 para os optantes; a SEFAZ-SP (Consulta 32359/2025) confirma. A exceção é a UF
        // que cobra por lei estadual própria (STF Tema 1284), que o contador marca na tabela por UF
        // (`nfe_icms_uf.cobra_difal_simples`). Empresa fora do Simples, ou sem regime cadastrado,
        // segue a regra de antes.
        $aviso = null;
        $simples = $crt === null ? $this->ehSimples((int) $regra->business_id) : $crt !== 3;
        if ($valorDifal > 0 && $simples
            && ! $this->ufCobraDifalSimples((int) $regra->business_id, (string) $destino['uf_origem'], (string) $destino['uf_destino'])) {
            $valorDifal = 0.0;
            $aviso = 'DIFAL não recolhido: empresa do Simples Nacional (STF ADI 5.464). '
                . 'Marque a UF ' . strtoupper((string) $destino['uf_destino']) . ' se ela cobra por lei própria.';
        }

        return [
            'base_st'     => $baseSt,
            'valor_st'    => $valorSt,
            'valor_fcp'   => $fcp > 0 ? $this->fmt($valor * $fcp) : 0.0,
            'valor_difal' => $valorDifal,
            'aviso'       => $aviso,
        ];
    }

    /** PIS e COFINS do regime, em decimal. */
    private const PIS_COFINS_DO_REGIME = [
        // Cumulativo (Lucro Presumido): PIS 0,65% — Lei 9.715/1998, art. 8º, I; COFINS 3% — Lei 9.718/1998, art. 8º.
        'LP' => ['pis' => 0.0065, 'cofins' => 0.03],
        // Não cumulativo (Lucro Real): PIS 1,65% — Lei 10.637/2002, art. 2º; COFINS 7,6% — Lei 10.833/2003, art. 2º.
        'LR' => ['pis' => 0.0165, 'cofins' => 0.076],
    ];

    /** IBS e CBS do período de teste — Lei Complementar 214/2025 (2026: IBS 0,1%, CBS 0,9%). Ano sem linha = sem padrão. */
    private const IBS_CBS_DO_ANO = [
        2026 => ['ibs' => 0.001, 'cbs' => 0.009],
    ];

    /** CST/CSOSN sem ICMS próprio destacado (isento, não tributado, suspenso, ST retida antes, só ST…). */
    private const SEM_ICMS_PROPRIO = ['30', '40', '41', '50', '60', '102', '103', '202', '203', '300', '400', '500'];

    /**
     * Origem da mercadoria (tabela A do CST) que leva a alíquota interestadual de 4%:
     * Resolução do Senado Federal 13/2012 — importado (1, 2) e nacional com conteúdo de importação
     * acima de 40% (3, 8). Fora: 6 e 7, importado sem similar nacional (lista CAMEX), e 0, 4, 5.
     */
    private const ORIGEM_QUATRO_POR_CENTO = [1, 2, 3, 8];

    /**
     * Calcula pela natureza criada na tela da Tributação (RUNBOOK-tributacao §Naturezas · onda 4).
     *
     * A natureza guarda CÓDIGOS e a ORIGEM de cada alíquota; aqui a origem vira número:
     *   - fixa      → o percentual da natureza;
     *   - uf        → ICMS: interna da UF de origem dentro do estado, interestadual da `nfe_icms_uf`
     *                 fora (4% para a origem da mercadoria da Resolução SF 13/2012);
     *   - produto   → IPI e MVA da regra por NCM (a mesma `nfe_fiscal_rules` da tela de regras);
     *   - regime    → PIS/COFINS do regime (constante com a lei citada; Simples = 0, recolhe no DAS);
     *   - padrao    → IBS/CBS do ano da emissão (constante com a lei citada);
     *   - origem    → da nota de origem: o motor não enxerga a nota referenciada → recusa.
     * Faltou a fonte do número? Recusa com o motivo (TributacaoNaoConfiguradaException). Nunca zero em silêncio.
     *
     * Fora desta onda, declarado: a exclusão do ICMS da base do PIS/COFINS (exige o vBC do PIS no
     * XML) e o CST/cEnq do IPI (o XML segue montando como antes). ST e DIFAL sobre a base cheia,
     * como em `aplicarRegra`.
     *
     * @param array{uf_origem?:string,uf_destino?:string,interna?:float|null,contribuinte?:bool|null,crt?:int|null} $destino
     */
    private function aplicarNaturezaTela(
        NfeOperacaoFiscal $natureza,
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
        array $destino,
        string $data,
    ): TributoCalculado {
        $t = (array) $natureza->config_tela;
        $icms = (array) ($t['icms'] ?? []);
        $ipi = (array) ($t['ipi'] ?? []);
        $ibs = (array) ($t['ibs'] ?? []);
        $nome = '"' . $natureza->nome . '"';
        $recusa = fn (string $motivo) => new TributacaoNaoConfiguradaException("Natureza {$nome}: {$motivo}");

        $crt = isset($destino['crt']) ? (int) $destino['crt'] : null;
        $simples = $crt === null ? $this->ehSimples($businessId) : $crt !== 3;
        $regime = $simples ? 'SN' : (((string) ($this->buscarConfig($businessId)?->regime ?? '')) === 'lucro_real' ? 'LR' : 'LP');

        // CFOP pelo destino.
        $exterior = strtoupper($ufDestino) === 'EX';
        $dentro = ! $exterior && strtoupper($ufDestino) === strtoupper($ufOrigem);
        $cfop = (string) ($exterior ? $natureza->cfop_exterior : ($dentro ? $natureza->cfop : $natureza->cfop_interestadual));
        if ($cfop === '') {
            throw $recusa('sem CFOP para ' . ($exterior ? 'o exterior' : ($dentro ? 'dentro do estado' : 'outro estado')) . '.');
        }

        // Regra por NCM (operação padrão): fonte de IPI e MVA "da regra do NCM".
        $regraNcm = fn () => $this->buscarRegra($businessId, (string) $produto->ncm, $ufOrigem, $ufDestino, null, $data, $crt)
            ?? $this->buscarRegra($businessId, (string) $produto->ncm, $ufOrigem, null, null, $data, $crt);

        // ICMS próprio.
        $codigo = (string) ($simples ? ($icms['csosn'] ?? '') : ($icms['cst'] ?? ''));
        $semProprio = in_array($codigo, self::SEM_ICMS_PROPRIO, true) || ($simples && $codigo !== '900');
        $aliqIcms = 0.0;
        if (! $semProprio) {
            $aliqIcms = match ((string) ($icms['origem'] ?? 'uf')) {
                'fixa'   => (float) ($icms['p'] ?? 0) / 100,
                'origem' => throw $recusa('ICMS "da nota de origem" só existe na devolução, que tem fluxo próprio.'),
                default  => $this->icmsPelaUf($businessId, $ufOrigem, $ufDestino, $exterior, $dentro, $produto, $data, $recusa),
            };
        }
        $reducao = (! $simples && in_array($codigo, ['20', '70'], true)) ? (float) ($icms['red'] ?? 0) / 100 : 0.0;
        $baseIcms = $reducao > 0 ? $this->fmt($produto->valor * (1 - $reducao)) : null;
        $valorIcms = $this->fmt(($baseIcms ?? $produto->valor) * $aliqIcms);

        // IPI.
        $aliqIpi = 0.0;
        if (! empty($ipi['on'])) {
            $aliqIpi = match ((string) ($ipi['origem'] ?? 'produto')) {
                'fixa'   => (float) ($ipi['p'] ?? 0) / 100,
                'origem' => throw $recusa('IPI "da nota de origem" só existe na devolução, que tem fluxo próprio.'),
                default  => (float) (($regraNcm() ?? throw $recusa('IPI "da regra do NCM", mas o NCM ' . $produto->ncm . ' não tem regra.'))->aliquota_ipi),
            };
        }

        // MVA (só com CST/CSOSN de ST).
        $mva = null;
        if (in_array($codigo, ['10', '30', '70', '201', '202', '203'], true)) {
            $st = (array) ($icms['st'] ?? []);
            $mva = ($st['mvaOrig'] ?? 'produto') === 'fixa'
                ? (float) ($st['mva'] ?? 0) / 100
                : (($regraNcm() ?? throw $recusa('MVA "da regra do NCM", mas o NCM ' . $produto->ncm . ' não tem regra.'))->mva);
            $mva = $mva !== null ? (float) $mva : null;
        }

        // DIFAL e FCP: desligados na natureza → o motor não calcula (o grupo sai zerado).
        $destinoSt = $destino;
        if (empty($icms['difal'])) {
            $destinoSt['contribuinte'] = null;
        }
        $fcp = ! empty($icms['fcp']) && ! $dentro && ! $exterior
            ? \Modules\NfeBrasil\Models\NfeIcmsUf::fcp($businessId, $ufOrigem, $ufDestino, $data)
            : null;
        $extra = $this->calcularStFcpDifal([
            'business_id'   => $businessId,
            'aliquota_icms' => $aliqIcms,
            'aliquota_ipi'  => $aliqIpi,
            'mva'           => $mva,
            'fcp'           => $fcp,
            'csosn'         => $simples ? $codigo : null,
            'cst'           => $simples ? null : $codigo,
        ], $produto, $destinoSt);

        // PIS e COFINS.
        $pc = [];
        foreach (['pis', 'cofins'] as $k) {
            $x = (array) ($t[$k] ?? []);
            $cst = (string) ($simples ? ($x['cstSN'] ?? '') : ($x['cst'] ?? ''));
            $tributado = in_array($cst, ['01', '02', '50'], true);
            $aliq = ! $tributado ? 0.0 : match ((string) ($x['origem'] ?? 'regime')) {
                'fixa'   => (float) ($x['p'] ?? 0) / 100,
                'origem' => throw $recusa(strtoupper($k) . ' "da nota de origem" só existe na devolução, que tem fluxo próprio.'),
                default  => self::PIS_COFINS_DO_REGIME[$regime][$k] ?? 0.0,
            };
            $pc[$k] = ['cst' => $cst, 'aliq' => $aliq];
        }

        // IBS e CBS.
        $aliqIbs = 0.0;
        $aliqCbs = 0.0;
        if (! empty($ibs['on'])) {
            if (($ibs['origem'] ?? 'padrao') === 'fixa') {
                $aliqIbs = ((float) ($ibs['pUF'] ?? 0) + (float) ($ibs['pMun'] ?? 0)) / 100;
                $aliqCbs = (float) ($ibs['pCBS'] ?? 0) / 100;
            } else {
                $ano = (int) substr($data, 0, 4);
                $padrao = self::IBS_CBS_DO_ANO[$ano] ?? throw $recusa("IBS/CBS pelo padrão do ano, mas {$ano} não tem padrão cadastrado.");
                [$aliqIbs, $aliqCbs] = [$padrao['ibs'], $padrao['cbs']];
            }
            if (($ibs['cst'] ?? '') === '200') {
                $fator = 1 - (float) ($ibs['pRed'] ?? 0) / 100;
                [$aliqIbs, $aliqCbs] = [$aliqIbs * $fator, $aliqCbs * $fator];
            }
        }

        return new TributoCalculado(
            cfop:            $cfop,
            csosn:           $simples ? $codigo : null,
            cst:             $simples ? null : $codigo,
            aliquota_icms:   $aliqIcms,
            aliquota_pis:    $pc['pis']['aliq'],
            aliquota_cofins: $pc['cofins']['aliq'],
            aliquota_ipi:    $aliqIpi,
            valor_icms:      $valorIcms,
            valor_pis:       $this->fmt($produto->valor * $pc['pis']['aliq']),
            valor_cofins:    $this->fmt($produto->valor * $pc['cofins']['aliq']),
            valor_ipi:       $this->fmt($produto->valor * $aliqIpi),
            nivel_usado:     4,
            regra_id:        null,
            mva:             $mva,
            fcp:             $fcp,
            c_class_trib:    ! empty($ibs['on']) ? (string) ($ibs['cClass'] ?? '') : null,
            cst_ibs:         ! empty($ibs['on']) ? (string) ($ibs['cst'] ?? '') : null,
            cst_cbs:         ! empty($ibs['on']) ? (string) ($ibs['cst'] ?? '') : null,
            aliquota_ibs:    $aliqIbs,
            aliquota_cbs:    $aliqCbs,
            valor_ibs:       $this->fmt($produto->valor * $aliqIbs),
            valor_cbs:       $this->fmt($produto->valor * $aliqCbs),
            base_st:         $extra['base_st'],
            valor_st:        $extra['valor_st'],
            valor_fcp:       $extra['valor_fcp'],
            valor_difal:     $extra['valor_difal'],
            aviso:           $extra['aviso'],
            reducao_base:    $reducao,
            base_icms:       $baseIcms,
            cst_pis:         $this->cstPisCofins($pc['pis']['cst']),
            cst_cofins:      $this->cstPisCofins($pc['cofins']['cst']),
        );
    }

    /** ICMS "pela UF": interna da origem dentro do estado; interestadual (ou 4% de importado) fora. */
    private function icmsPelaUf(
        int $businessId, string $ufOrigem, string $ufDestino, bool $exterior, bool $dentro,
        ProdutoFiscalContext $produto, string $data, \Closure $recusa,
    ): float {
        if ($exterior) {
            throw $recusa('exportação com ICMS "pela UF": use um CST sem ICMS próprio ou alíquota fixa.');
        }
        if ($dentro) {
            return \Modules\NfeBrasil\Models\NfeIcmsUf::aliquotaInterna($businessId, $ufOrigem, $ufOrigem, $data);
        }
        if (in_array((int) ($produto->origem_mercadoria ?? -1), self::ORIGEM_QUATRO_POR_CENTO, true)) {
            return 0.04;
        }

        return \Modules\NfeBrasil\Models\NfeIcmsUf::aliquotaInterestadual($businessId, $ufOrigem, $ufDestino, $data)
            ?? throw $recusa('alíquota interestadual ' . strtoupper($ufOrigem) . '→' . strtoupper($ufDestino) . ' não cadastrada.');
    }

    /** Optante do Simples (ou MEI) pelo regime cadastrado em `nfe_business_configs`. */
    private function ehSimples(int $businessId): bool
    {
        return in_array((string) ($this->buscarConfig($businessId)?->regime ?? ''), ['simples', 'mei'], true);
    }

    /** A UF de destino foi marcada pelo contador como cobradora do DIFAL do Simples. */
    private function ufCobraDifalSimples(int $businessId, string $ufOrigem, string $ufDestino): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('nfe_icms_uf', 'cobra_difal_simples')) {
            return false;
        }

        return (bool) \Modules\NfeBrasil\Models\NfeIcmsUf::withoutGlobalScopes() // SUPERADMIN: motor roda em fila; escopo no where
            ->where('business_id', $businessId)
            ->where('uf_origem', strtoupper($ufOrigem))
            ->where('uf_destino', strtoupper($ufDestino))
            ->vigenteEm(now()->toDateString())
            ->orderByDesc('id')
            ->value('cobra_difal_simples');
    }

    /**
     * @param array<string,mixed> $defaults
     * @param array{uf_origem?:string,uf_destino?:string,interna?:float|null,contribuinte?:bool|null,crt?:int|null} $destino
     */
    private function aplicarDefaults(
        array $defaults,
        ProdutoFiscalContext $produto,
        ?string $cfopOperacao,
        int $businessId,
        array $destino = [],
    ): TributoCalculado {
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
        $mva           = isset($defaults['mva']) ? (float) $defaults['mva'] : null;
        $fcp           = isset($defaults['fcp']) ? (float) $defaults['fcp'] : null;

        // ST, FCP e DIFAL pelo mesmo cálculo dos níveis 1 a 3, com as alíquotas do default.
        $extra = $this->calcularStFcpDifal([
            'business_id'   => $businessId,
            'aliquota_icms' => $aliqIcms,
            'aliquota_ipi'  => $aliqIpi,
            'mva'           => $mva,
            'fcp'           => $fcp,
            'csosn'         => $csosn,
            'cst'           => $cst,
        ], $produto, $destino);

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
            mva:             $mva,
            fcp:             $fcp,
            c_class_trib:    $cClassTrib,
            cst_ibs:         $cstIbs,
            cst_cbs:         $cstCbs,
            aliquota_ibs:    $aliqIbs,
            aliquota_cbs:    $aliqCbs,
            valor_ibs:       $this->fmt($produto->valor * $aliqIbs),
            valor_cbs:       $this->fmt($produto->valor * $aliqCbs),
            base_st:         $extra['base_st'],
            valor_st:        $extra['valor_st'],
            valor_fcp:       $extra['valor_fcp'],
            valor_difal:     $extra['valor_difal'],
            aviso:           $extra['aviso'],
            cst_pis:         $this->cstPisCofins($defaults['cst_pis'] ?? null),
            cst_cofins:      $this->cstPisCofins($defaults['cst_cofins'] ?? null),
        );
    }

    /** R-NFE-025b: '1' vira '01'; vazio vira null (a emissão cai no 07 de antes). */
    private function cstPisCofins(mixed $valor): ?string
    {
        $cst = trim((string) ($valor ?? ''));

        return $cst === '' ? null : str_pad($cst, 2, '0', STR_PAD_LEFT);
    }

    /** Arredonda em 2 casas (padrão SEFAZ pra valores monetários) */
    private function fmt(float $v): float
    {
        return round($v, 2);
    }
}
