<?php

declare(strict_types=1);

/**
 * Template tributário · Indústria gráfica · Lucro Presumido · SP
 *
 * Cliente típico: gráfica de médio porte (R$ 4.8M-78M/ano) que produz
 * embalagens, livros, impressos personalizados. Saiu do Simples mas ainda
 * em regime cumulativo PIS/COFINS.
 *
 * Modelo NFe: 55 (NFe B2B) — indústria sempre emite modelo 55
 * CFOP: 5.101 (venda de produção do estabelecimento)
 * Tributação ICMS: CST 00 (tributada integralmente, 18% SP)
 * IPI: depende do NCM — embalagens (NCM 4819) 5%, livros (NCM 4901) 0% imune
 * PIS: 0.65% cumulativo
 * COFINS: 3% cumulativo
 *
 * Fonte: TIPI vigente + RICMS-SP + IN 1.911/2019.
 */
return [
    'slug'       => 'industria-grafica-presumido-sp',
    'titulo'     => 'Indústria gráfica · Lucro Presumido · SP',
    'descricao'  => 'Gráfica produzindo sob encomenda. ICMS 18% + IPI variável por NCM + PIS 0,65% + COFINS 3%.',
    'icon'       => 'printer',
    'setor'      => 'industria',
    'regime'     => 'lucro_presumido',
    'uf'         => 'SP',
    'modelo_nfe' => '55',
    'recomendado_para' => 'Indústria gráfica R$ 4.8M-78M/ano em Lucro Presumido.',
    'tributacao_default' => [
        'cst'             => '00',
        'cfop'            => '5101',
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0.05,    // embalagens NCM 4819 default 5%
    ],
    'observacoes' => [
        'CFOP 5.101 = produção própria. Use 5.102 quando revender.',
        'Livros (NCM 4901) têm IMUNIDADE constitucional ICMS+IPI — configurar regra NCM com CST 41 (não tributada) e IPI CST 51 (saída isenta).',
        'Embalagens impressas (NCM 4819) IPI 5% default. Confirmar TIPI atual pra cada NCM específico.',
        'Materiais promocionais (NCM 4911) PIS/COFINS Tabela 1 — verificar produto específico.',
        'GIA-ST mensal obrigatória pra produtos sujeitos a substituição tributária.',
    ],
];
