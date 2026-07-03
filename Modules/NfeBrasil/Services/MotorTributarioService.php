<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Modules\NfeBrasil\Exceptions\NcmObrigatorioException;
use Modules\NfeBrasil\Exceptions\TributacaoNaoConfiguradaException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;

/**
 * US-NFE-043 · Motor tributário com cascade em 4 níveis (ADR ARQ-0006).
 *
 * Fluxo:
 *   Nível 1 — override por produto (`fiscal_rule_override_id`)
 *   Nível 2 — regra exata (business + ncm + uf_origem + uf_destino)
 *   Nível 3 — regra padrão NCM (business + ncm + uf_origem, uf_destino NULL)
 *   Nível 4 — defaults business (nfe_business_configs.tributacao_default)
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

    public function calcular(
        ProdutoFiscalContext $produto,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
    ): TributoCalculado {
        // D9 Wave 26 observabilidade — wrap calcular() em span com business_id (Tier 0).
        return OtelHelper::span('nfe.motor_tributario.calcular', [
            'business_id' => $businessId,
            'ncm'         => (string) ($produto->ncm ?? ''),
            'uf_origem'   => $ufOrigem,
            'uf_destino'  => $ufDestino,
            'has_override' => $produto->fiscal_rule_override_id !== null,
        ], function () use ($produto, $businessId, $ufOrigem, $ufDestino): TributoCalculado {
            return $this->calcularInterno($produto, $businessId, $ufOrigem, $ufDestino);
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
    ): TributoCalculado {
        // Nível 1 — override por produto (curto-circuito)
        if ($produto->fiscal_rule_override_id !== null) {
            $regra = NfeFiscalRule::where('business_id', $businessId)
                ->where('id', $produto->fiscal_rule_override_id)
                ->first();

            if ($regra) {
                return $this->aplicarRegra($regra, $produto, nivel: 1);
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
        $regra = $this->buscarRegra($businessId, $produto->ncm, $ufOrigem, $ufDestino);
        if ($regra) {
            return $this->aplicarRegra($regra, $produto, nivel: 2);
        }

        // Nível 3 — regra padrão NCM (uf_destino NULL)
        $regra = $this->buscarRegra($businessId, $produto->ncm, $ufOrigem, null);
        if ($regra) {
            return $this->aplicarRegra($regra, $produto, nivel: 3);
        }

        // Nível 4 — defaults business
        $config = $this->buscarConfig($businessId);
        if ($config && ! empty($config->tributacao_default)) {
            return $this->aplicarDefaults($config->tributacao_default, $produto);
        }

        throw new TributacaoNaoConfiguradaException(
            "Business {$businessId} sem default tributário. Cadastre em " .
            "/nfe-brasil/configuracao/tributacao-default antes de emitir."
        );
    }

    private function buscarRegra(
        int $businessId,
        string $ncm,
        string $ufOrigem,
        ?string $ufDestino,
    ): ?NfeFiscalRule {
        $cacheKey = "{$businessId}|{$ncm}|{$ufOrigem}|" . ($ufDestino ?? 'NULL');

        if (array_key_exists($cacheKey, $this->cacheRegras)) {
            return $this->cacheRegras[$cacheKey];
        }

        $query = NfeFiscalRule::where('business_id', $businessId)
            ->where('ncm', $ncm)
            ->where('uf_origem', $ufOrigem);

        $query = $ufDestino === null
            ? $query->whereNull('uf_destino')
            : $query->where('uf_destino', $ufDestino);

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
    ): TributoCalculado {
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
        );
    }

    /**
     * @param array<string,mixed> $defaults
     */
    private function aplicarDefaults(array $defaults, ProdutoFiscalContext $produto): TributoCalculado
    {
        $cfop          = (string) ($defaults['cfop'] ?? '5102');
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
