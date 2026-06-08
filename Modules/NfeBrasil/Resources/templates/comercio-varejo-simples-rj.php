<?php

declare(strict_types=1);

/**
 * Template tributário · Comércio varejo · Simples Nacional · RJ (com FCP)
 *
 * Cliente típico: gráfica/varejo POS no Rio de Janeiro. RJ tem **FCP** (Fundo
 * de Combate à Pobreza) — adicional de 2% sobre ICMS pra produtos específicos.
 *
 * Modelo NFe: 65 (NFC-e)
 * CFOP: 5.102
 * Tributação ICMS: CSOSN 102 (Simples sem crédito)
 * **FCP: 2%** — RJ adiciona em bebidas alcoólicas, fumo, perfumes, energia,
 * comunicação. Pra varejo gráfico geral, FCP fica zero (configurar regra NCM
 * específica quando vender produto com FCP).
 *
 * **Diferença vs SP:** SP NÃO tem FCP. RJ adiciona 2% conforme Lei 4.056/2002
 * + Decreto 27.815/2001. Outras UFs com FCP: MG (2%), RS (2%), GO (2%), PA (2%).
 *
 * Fonte: RICMS-RJ + CONFAZ + LC 123/2006.
 */
return [
    'slug'       => 'comercio-varejo-simples-rj',
    'titulo'     => 'Comércio varejo · Simples Nacional · RJ',
    'descricao'  => 'Varejo balcão B2C no RJ. Simples Nacional + FCP 2% em produtos específicos (bebidas/fumo/etc).',
    'icon'       => 'shopping-bag',
    'setor'      => 'comercio',
    'regime'     => 'simples',
    'uf'         => 'RJ',
    'modelo_nfe' => '65',
    'recomendado_para' => 'Varejo POS no RJ (estado com FCP). Configure regras NCM pra produtos sujeitos ao FCP.',
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
        'FCP no RJ = 2% adicional sobre ICMS. Não se aplica a TODA mercadoria — só lista específica (bebidas alcoólicas, fumo, perfumes premium, energia elétrica, comunicações).',
        'Pra varejo gráfico geral (livros, papelaria, impressos) FCP é ZERO. Default desse template reflete isso.',
        'Quando vender produto com FCP, criar regra NCM específica com `fcp: 0.02` (2%).',
        'Pra venda interestadual RJ→outras UFs consumidor final, configurar DIFAL + FCP destino conforme UF.',
        'Outras UFs com FCP: MG (2%), RS (2%), GO (2%), PA (2%) — consultar legislação específica de cada estado.',
    ],
];
