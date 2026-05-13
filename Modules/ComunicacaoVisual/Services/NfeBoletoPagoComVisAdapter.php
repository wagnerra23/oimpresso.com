<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Services;

use Modules\ComunicacaoVisual\Entities\OrdemProducao;
use Modules\ComunicacaoVisual\Entities\Substrato;

/**
 * NfeBoletoPagoComVisAdapter — decide qual doc fiscal emitir quando boleto Asaas/Inter
 * é confirmado pago (US-COMVIS-009 / US-RB-044 ComVis adapter).
 *
 * Adapter PURE FUNCTION (stateless). Recebe `OrdemProducao` e devolve decisão estruturada:
 *
 *   [
 *     'docs'    => array<int, array{tipo: 'nfe55'|'nfse56'|'nfce65', ncm?: string, cfop?: string,
 *                                    csosn?: string, item_ls?: string, valor: float, reason: string}>,
 *     'mode'    => 'single' | 'dual',     // dual = NFe55 + NFSe56 simultâneo
 *     'reason'  => string,                 // explicação humana da decisão
 *     'alertas' => array<string>,          // soft-warn validações pendentes
 *   ]
 *
 * Quem dispara emissão é outro Job/Service (NfeBrasil emission stack) — este adapter
 * APENAS DECIDE com base nas regras LC 116/2003 + STF Súmula 156.
 *
 * Regras canon (referência agent expert §5):
 *
 *   instalacao_tipo = 'cliente_busca' + total < R$ 200 + sem contato_id (varejo balcão)
 *     → NFC-e (mod 65) — NCM 4911.10 ou substrato.ncm — CFOP 5102 (revenda)
 *
 *   instalacao_tipo = 'cliente_busca' OU 'entrega_apenas' (venda mercadoria pura)
 *     → NFe (mod 55) — NCM do substrato — CFOP do substrato (default 5101 produção própria)
 *
 *   instalacao_tipo = 'fachada_simples' | 'fachada_andaime' | 'fachada_nr35' (com serviço aplicação)
 *     → DUAL (NFe55 do material + NFSe56 do serviço — STF Súmula 156)
 *       NFe: NCM substrato + valor proporcional material (subtotal substrato)
 *       NFSe: LS 24.01 + valor proporcional serviço (custo_instalacao + extras)
 *
 *   instalacao_tipo NULL + total = 0  →  THROW (OrdemProducao inválida)
 *
 * Multi-tenant Tier 0: ler substrato via belongsTo respeita global scope ($op->substrato).
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-009
 * @see .claude/agents/comunicacao-visual-expert.md §5 (tributação)
 * @see https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm (LC 116/2003 LS 24.01)
 */
class NfeBoletoPagoComVisAdapter
{
    /** Limiar NFC-e venda balcão (mediana setor BR 2026 — varejo simples) */
    public const NFCE_VALOR_LIMIAR = 200.00;

    /** NCM default impressos publicitários (fallback se substrato sem NCM) */
    public const NCM_DEFAULT_PUBLICITARIO = '4911.10';

    /** CFOP venda produção própria UF */
    public const CFOP_PRODUCAO_PROPRIA = '5101';

    /** CFOP venda mercadoria adquirida de terceiros UF */
    public const CFOP_REVENDA = '5102';

    /** CSOSN Simples Nacional sem permissão crédito (default gráfica média BR) */
    public const CSOSN_SIMPLES_SEM_CREDITO = '102';

    /** Item Lista de Serviços LC 116/2003 — instalação/manutenção CV */
    public const LS_ITEM_INSTALACAO_CV = '24.01';

    /**
     * Decide doc(s) fiscal(is) a emitir.
     *
     * @return array{docs: list<array>, mode: string, reason: string, alertas: list<string>}
     */
    public function decide(OrdemProducao $op): array
    {
        $alertas = [];
        $total = (float) $op->total;

        if ($total <= 0) {
            throw new \InvalidArgumentException(
                "OrdemProducao {$op->codigo}: total deve ser > 0 pra decidir doc fiscal."
            );
        }

        $substrato = $op->substrato; // belongsTo (multi-tenant via global scope)
        $ncm = $substrato?->ncm ?? self::NCM_DEFAULT_PUBLICITARIO;
        $cfopSubstrato = $substrato?->cfop_padrao ?? self::CFOP_PRODUCAO_PROPRIA;
        $csosn = $substrato?->csosn_padrao ?? self::CSOSN_SIMPLES_SEM_CREDITO;

        $tipo = (string) $op->instalacao_tipo;

        // ─── CENÁRIO 1: serviço com aplicação (dual NFe55 + NFSe56) ───
        if (in_array($tipo, ['fachada_simples', 'fachada_andaime', 'fachada_nr35'], true)) {
            // Decompõe: subtotal = material, extras + custo_instalacao = serviço
            $valorMaterial = (float) ($op->subtotal ?? 0);
            $valorServico  = max(0, $total - $valorMaterial);

            if ($valorMaterial <= 0) {
                // Edge case: 100% serviço, sem material vendido (instalação pura)
                return [
                    'docs' => [[
                        'tipo'    => 'nfse56',
                        'item_ls' => self::LS_ITEM_INSTALACAO_CV,
                        'valor'   => $total,
                        'reason'  => "Instalação {$tipo} sem material vendido — NFSe pura LC 116/2003 item 24.01.",
                    ]],
                    'mode'    => 'single',
                    'reason'  => 'NFSe pura — serviço de instalação sem mercadoria.',
                    'alertas' => $alertas,
                ];
            }

            if ($valorServico <= 0) {
                // Edge case: instalacao_tipo fachada* mas só material (sem extras nem custo_instalacao)
                $alertas[] = "OrdemProducao com instalacao_tipo={$tipo} mas sem custo_instalacao + extras > 0 — emitindo só NFe (verificar se OS é realmente com instalação).";
                return [
                    'docs' => [[
                        'tipo'   => 'nfe55',
                        'ncm'    => $ncm,
                        'cfop'   => $cfopSubstrato,
                        'csosn'  => $csosn,
                        'valor'  => $total,
                        'reason' => "Material em OS de instalação sem componente serviço identificado.",
                    ]],
                    'mode'    => 'single',
                    'reason'  => 'NFe single — instalação declarada mas sem componente serviço identificado.',
                    'alertas' => $alertas,
                ];
            }

            // Caso canônico: dual-doc
            if ($tipo === 'fachada_nr35') {
                $alertas[] = "Instalação fachada_nr35: garantir docs NR-35 vigentes (ART + treinamento + ASO) ANTES de emitir NFSe.";
            }

            return [
                'docs' => [
                    [
                        'tipo'   => 'nfe55',
                        'ncm'    => $ncm,
                        'cfop'   => $cfopSubstrato,
                        'csosn'  => $csosn,
                        'valor'  => round($valorMaterial, 2),
                        'reason' => "Componente material substrato " . ($substrato?->nome ?? 'genérico') . " (R\$ {$valorMaterial}).",
                    ],
                    [
                        'tipo'    => 'nfse56',
                        'item_ls' => self::LS_ITEM_INSTALACAO_CV,
                        'valor'   => round($valorServico, 2),
                        'reason'  => "Componente serviço {$tipo} (R\$ {$valorServico}) — LC 116/2003 item 24.01.",
                    ],
                ],
                'mode'    => 'dual',
                'reason'  => "Dual-doc NFe55 (material) + NFSe56 (serviço {$tipo}) — STF Súmula 156 + LC 116/2003 24.01.",
                'alertas' => $alertas,
            ];
        }

        // ─── CENÁRIO 2: venda mercadoria pura ─── (cliente_busca | entrega_apenas)
        if (in_array($tipo, ['cliente_busca', 'entrega_apenas'], true)) {

            // Sub-cenário 2a: NFC-e (varejo balcão simples)
            if ($tipo === 'cliente_busca'
                && $total < self::NFCE_VALOR_LIMIAR
                && empty($op->contato_id)
            ) {
                return [
                    'docs' => [[
                        'tipo'   => 'nfce65',
                        'ncm'    => $ncm,
                        'cfop'   => self::CFOP_REVENDA, // NFC-e canon CFOP 5102
                        'csosn'  => $csosn,
                        'valor'  => $total,
                        'reason' => "Venda balcão R\$ {$total} < limiar R\$ " . self::NFCE_VALOR_LIMIAR . " sem contato identificado.",
                    ]],
                    'mode'    => 'single',
                    'reason'  => 'NFC-e — varejo balcão simples (cliente_busca, valor baixo, sem contato).',
                    'alertas' => $alertas,
                ];
            }

            // Sub-cenário 2b: NFe normal
            return [
                'docs' => [[
                    'tipo'   => 'nfe55',
                    'ncm'    => $ncm,
                    'cfop'   => $cfopSubstrato,
                    'csosn'  => $csosn,
                    'valor'  => $total,
                    'reason' => "Venda mercadoria {$tipo} — substrato " . ($substrato?->nome ?? 'genérico') . ".",
                ]],
                'mode'    => 'single',
                'reason'  => "NFe55 — venda mercadoria pura ({$tipo}).",
                'alertas' => $alertas,
            ];
        }

        // ─── Edge: tipo desconhecido ───
        $alertas[] = "instalacao_tipo '{$tipo}' não reconhecido — fallback NFe55 conservador.";
        return [
            'docs' => [[
                'tipo'   => 'nfe55',
                'ncm'    => $ncm,
                'cfop'   => $cfopSubstrato,
                'csosn'  => $csosn,
                'valor'  => $total,
                'reason' => "Fallback NFe55 — instalacao_tipo {$tipo} não mapeado.",
            ]],
            'mode'    => 'single',
            'reason'  => "Fallback NFe55 — instalacao_tipo desconhecido.",
            'alertas' => $alertas,
        ];
    }
}
