<?php

declare(strict_types=1);

/**
 * Template tributário · Comércio varejo · Lucro Presumido · SP
 *
 * Cliente típico: gráfica/papelaria que ultrapassou limite Simples (R$ [redacted Tier 0]M/ano)
 * mas ainda não migrou pra Lucro Real. Faturamento R$ [redacted Tier 0]M-78M.
 *
 * Modelo NFe: 65 (NFC-e) ou 55 (NFe) — varejo presencial e B2B
 * CFOP: 5.102 (venda mercadoria adquirida)
 * Tributação ICMS: CST 00 (tributada integralmente)
 *   - SP alíquota interna 18% pra venda intra-estadual
 * PIS: 0.65% cumulativo (Lucro Presumido)
 * COFINS: 3% cumulativo
 *
 * Atenção: Lucro Presumido recolhe PIS/COFINS no regime CUMULATIVO (sem crédito).
 * Lucro Real é NÃO-CUMULATIVO (PIS 1.65% + COFINS 7.6% com crédito).
 *
 * Fonte: Receita Federal IN 1.911/2019 + RICMS-SP.
 */
return [
    'slug'       => 'comercio-varejo-presumido-sp',
    'titulo'     => 'Comércio varejo · Lucro Presumido · SP',
    'descricao'  => 'Varejo médio fora do Simples mas em regime cumulativo. ICMS 18% + PIS 0,65% + COFINS 3%.',
    'icon'       => 'shopping-bag',
    'setor'      => 'comercio',
    'regime'     => 'lucro_presumido',
    'uf'         => 'SP',
    'modelo_nfe' => '65',
    'recomendado_para' => 'Varejo R$ [redacted Tier 0]M-78M/ano em Lucro Presumido.',
    'tributacao_default' => [
        'cst'             => '00',
        'cfop'            => '5102',
        'aliquota_icms'   => 0.18,    // SP interna
        'aliquota_pis'    => 0.0065,  // 0,65% cumulativo
        'aliquota_cofins' => 0.03,    // 3% cumulativo
        'aliquota_ipi'    => 0.00,
    ],
    'observacoes' => [
        'CST 00 = tributada integralmente (alíquota destacada na NFe).',
        'PIS/COFINS cumulativos (Presumido) — sem direito a crédito; alíquotas menores.',
        'Pra venda interestadual SP→outras UFs, configurar regra DIFAL específica em Tributação NCM.',
        'Pra produtos com ST (cigarros, combustíveis, etc), configurar MVA em regra NCM.',
        'IPI fica zerado pra revenda; quando houver industrialização, configurar CST IPI por NCM.',
    ],
];
