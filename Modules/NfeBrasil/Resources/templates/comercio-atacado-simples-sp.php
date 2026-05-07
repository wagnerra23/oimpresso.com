<?php

declare(strict_types=1);

/**
 * Template tributário · Comércio atacado/B2B · Simples Nacional · SP
 *
 * Cliente típico: distribuidor pequeno, gráfica que faz tiragens grandes
 * pra revendedores, comércio B2B Simples até R$ [redacted Tier 0]M/ano.
 *
 * Modelo NFe: 55 (NF-e B2B)
 * CFOP: 5.102 (venda de mercadoria) ou 5.405 (venda c/ ICMS-ST destacado pelo emitente)
 * Tributação ICMS: CSOSN 101 (com permissão de crédito) — emitente Simples passa
 *   crédito de PIS/COFINS pro cliente PJ não-Simples (Lei 11.488/07).
 *
 * Fonte: CONFAZ + LC 123/2006 art. 23 (transferência de crédito Simples).
 */
return [
    'slug'       => 'comercio-atacado-simples-sp',
    'titulo'     => 'Comércio atacado/B2B · Simples Nacional · SP',
    'descricao'  => 'Distribuidor, atacado pra revendedores. Emite NFe modelo 55 com transferência de crédito.',
    'icon'       => 'package',
    'setor'      => 'comercio',
    'regime'     => 'simples',
    'uf'         => 'SP',
    'modelo_nfe' => '55',
    'recomendado_para' => 'Venda B2B Simples Nacional pra cliente PJ que aproveita crédito.',
    'tributacao_default' => [
        'csosn'           => '101',
        'cfop'            => '5102',
        'aliquota_icms'   => 0.00,
        'aliquota_pis'    => 0.00,
        'aliquota_cofins' => 0.00,
        'aliquota_ipi'    => 0.00,
    ],
    'observacoes' => [
        'CSOSN 101 = Tributada com permissão de crédito — campo `pCredSN` na NFe deve ser preenchido com alíquota efetiva DAS.',
        'Pra venda interestadual SP→outras UFs consumidor final, configurar regra DIFAL específica em Tributação NCM.',
        'Cliente PJ não-Simples pode usar crédito do PIS/COFINS — destacar `pCredSN` corretamente.',
        'Se vender pra revenda (CFOP 5.102) é uma alíquota; se pra industrialização (CFOP 5.101) outra. Configure regras NCM se mix.',
    ],
];
