<?php

declare(strict_types=1);

/**
 * Template tributário · Indústria gráfica · Simples Nacional · SP
 *
 * Cliente típico: gráfica que faz industrialização sob encomenda (livros,
 * embalagens, impressos personalizados). Setor industrial NCM 4901-4911.
 *
 * Modelo NFe: 55 (NF-e B2B obrigatório pra indústria)
 * CFOP: 5.101 (venda de produção do estabelecimento) ou 5.102 (revenda)
 * Tributação ICMS: CSOSN 101 (Simples com crédito)
 * IPI: pode ser 0% (livros NCM 4901 são imunes — CST 51) ou 5-15% (embalagens)
 *
 * **Atenção:** indústria fora do Simples pagaria CST 00 + IPI percentual real.
 * Este template é Simples — IPI fica zerado (recolhido no DAS).
 *
 * Fonte: TIPI vigente + CONFAZ + LC 123/2006.
 */
return [
    'slug'       => 'industria-grafica-simples-sp',
    'titulo'     => 'Indústria gráfica · Simples Nacional · SP',
    'descricao'  => 'Gráfica que produz sob encomenda (livros, embalagens, impressos). NFe modelo 55 com CFOP de produção.',
    'icon'       => 'printer',
    'setor'      => 'industria',
    'regime'     => 'simples',
    'uf'         => 'SP',
    'modelo_nfe' => '55',
    'recomendado_para' => 'Indústria gráfica Simples Nacional vendendo produção própria.',
    'tributacao_default' => [
        'csosn'           => '101',
        'cfop'            => '5101',
        'aliquota_icms'   => 0.00,
        'aliquota_pis'    => 0.00,
        'aliquota_cofins' => 0.00,
        'aliquota_ipi'    => 0.00,
    ],
    'observacoes' => [
        'CFOP 5.101 = venda de produção própria. Use 5.102 quando revender mercadoria de terceiros.',
        'Livros (NCM 4901) têm imunidade IPI (CST 51) e ICMS reduzido — configure regra NCM específica.',
        'Embalagens impressas (NCM 4819) podem ter IPI 5% — fora do Simples, configure regra NCM.',
        'Materiais promocionais (NCM 4911) seguem Simples normal.',
        'Pra venda interestadual com DIFAL, configurar regras por uf_destino em Tributação NCM.',
    ],
];
