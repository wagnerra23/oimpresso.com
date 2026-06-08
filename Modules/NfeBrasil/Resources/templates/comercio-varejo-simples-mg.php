<?php

declare(strict_types=1);

/**
 * Template tributário · Comércio varejo · Simples Nacional · MG (com FCP)
 *
 * Cliente típico: gráfica/varejo POS em Minas Gerais. MG tem **FCP** (Fundo de
 * Erradicação da Miséria) — adicional de 2% sobre ICMS em produtos específicos.
 *
 * Modelo NFe: 65 (NFC-e)
 * CFOP: 5.102
 * Tributação ICMS: CSOSN 102 (Simples sem crédito)
 * **FCP: 2%** — MG adiciona em bebidas alcoólicas, fumo, refrigerantes,
 * cervejas, armas. Pra varejo gráfico geral, FCP fica zero (configurar regra
 * NCM específica quando vender produto com FCP).
 *
 * **Diferença vs SP:** SP NÃO tem FCP. MG adiciona 2% conforme Lei 19.978/2011
 * + Decreto 45.971/2012 (RICMS-MG Anexo IV). Outras UFs com FCP: RJ (2%),
 * RS (2%), GO (2%), PA (2%).
 *
 * Fonte: RICMS-MG + CONFAZ + LC 123/2006.
 */
return [
    'slug'       => 'comercio-varejo-simples-mg',
    'titulo'     => 'Comércio varejo · Simples Nacional · MG',
    'descricao'  => 'Varejo balcão B2C em MG. Simples Nacional + FCP 2% em produtos específicos (bebidas/fumo/etc).',
    'icon'       => 'shopping-bag',
    'setor'      => 'comercio',
    'regime'     => 'simples',
    'uf'         => 'MG',
    'modelo_nfe' => '65',
    'recomendado_para' => 'Varejo POS em MG (estado com FCP). Configure regras NCM pra produtos sujeitos ao FCP.',
    'tributacao_default' => [
        'csosn'           => '102',
        'cfop'            => '5102',
        'aliquota_icms'   => 0.00,    // Simples — recolhe via DAS
        'aliquota_pis'    => 0.00,
        'aliquota_cofins' => 0.00,
        'aliquota_ipi'    => 0.00,
        'fcp'             => 0.00,    // default zero; regra NCM define quando aplicável
    ],
    'observacoes' => [
        'FCP em MG = 2% adicional sobre ICMS (Fundo de Erradicação da Miséria). Não se aplica a TODA mercadoria — só lista específica (bebidas alcoólicas, fumo, refrigerantes, cervejas, armas/munições).',
        'Pra varejo gráfico geral (livros, papelaria, impressos) FCP é ZERO. Default desse template reflete isso.',
        'Quando vender produto com FCP, criar regra NCM específica com `fcp: 0.02` (2%).',
        'Pra venda interestadual MG→outras UFs consumidor final, configurar DIFAL + FCP destino conforme UF.',
        'Outras UFs com FCP: RJ (2%), RS (2%), GO (2%), PA (2%) — consultar legislação específica de cada estado.',
    ],
];
