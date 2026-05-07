<?php

declare(strict_types=1);

/**
 * Template tributário · Comércio varejo · Simples Nacional · SP
 *
 * Cliente típico: gráfica rápida POS, papelaria de bairro, fotocópia.
 * Vende serviço/produto pra consumidor final no balcão.
 *
 * Modelo NFe: 65 (NFC-e) — varejo presencial
 * CFOP: 5.102 (venda de mercadoria adquirida ou recebida de terceiros)
 * Tributação ICMS: CSOSN 102 (sem permissão de crédito) — Simples Nacional
 *   recolhe via DAS unificado, não tem ICMS destacado na nota.
 * PIS/COFINS: zerados (recolhidos no DAS)
 *
 * Fonte: CONFAZ + Receita Federal Simples Nacional Anexo I.
 */
return [
    'slug'       => 'comercio-varejo-simples-sp',
    'titulo'     => 'Comércio varejo · Simples Nacional · SP',
    'descricao'  => 'Gráfica rápida, papelaria, varejo de balcão. Emite NFC-e modelo 65 pra consumidor final.',
    'icon'       => 'shopping-bag',
    'setor'      => 'comercio',
    'regime'     => 'simples',
    'uf'         => 'SP',
    'modelo_nfe' => '65',
    'recomendado_para' => 'Venda balcão B2C, sem ICMS destacado, varejo até R$ [redacted Tier 0]M/ano.',
    'tributacao_default' => [
        'csosn'           => '102',
        'cfop'            => '5102',
        'aliquota_icms'   => 0.00,
        'aliquota_pis'    => 0.00,
        'aliquota_cofins' => 0.00,
        'aliquota_ipi'    => 0.00,
    ],
    'observacoes' => [
        'Simples Nacional recolhe ICMS/PIS/COFINS via DAS unificado — alíquotas na NFe ficam zeradas.',
        'CSOSN 102 = Tributada sem permissão de crédito (cliente PF não aproveita crédito ICMS).',
        'Quando vender pra cliente PJ que aproveita crédito, pode emitir NFe modelo 55 com CSOSN 101.',
        'DIFAL não se aplica em Simples Nacional vendendo dentro do estado de SP.',
    ],
];
