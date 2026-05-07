<?php

declare(strict_types=1);

/**
 * Template tributário · Comércio varejo · Simples Nacional · SC
 *
 * Cliente típico: gráfica rápida POS, papelaria, fotocópia em Santa Catarina.
 * Venda balcão pra consumidor final (NFC-e modelo 65).
 *
 * Modelo NFe: 65 (NFC-e) — varejo presencial
 * CFOP: 5.102 (venda de mercadoria adquirida ou recebida de terceiros)
 * Tributação ICMS: CSOSN 102 (sem permissão de crédito) — Simples Nacional
 *   recolhe via DAS unificado, sem ICMS destacado na nota.
 * PIS/COFINS: zerados (recolhidos no DAS)
 * IPI: zerado (sem IPI no varejo Simples)
 *
 * **Diferença vs SP:** SC NÃO tem FCP. Alíquota interna ICMS de SC é 17%
 * (versus 18% SP), mas pra Simples isso não importa — DAS recolhe unificado.
 * Pra venda interestadual SC→outras UFs consumidor final, configurar DIFAL
 * + FCP destino conforme UF (regra NCM específica).
 *
 * **Particularidades SC:**
 * - DIFAL Simples Nacional: SC implementa regra normal (alíquota destino)
 * - Substituição Tributária: SC tem extensa lista MVA por NCM — fora do escopo
 *   desse template (regra avulsa por NCM quando aplicável).
 *
 * Fonte: RICMS-SC + LC 123/2006 + CONFAZ.
 */
return [
    'slug'       => 'comercio-varejo-simples-sc',
    'titulo'     => 'Comércio varejo · Simples Nacional · SC',
    'descricao'  => 'Varejo balcão B2C em SC. Simples Nacional sem FCP. ICMS interno 17% (irrelevante p/ Simples — DAS unificado).',
    'icon'       => 'shopping-bag',
    'setor'      => 'comercio',
    'regime'     => 'simples',
    'uf'         => 'SC',
    'modelo_nfe' => '65',
    'recomendado_para' => 'Varejo POS em Santa Catarina sob Simples Nacional. Gráficas rápidas, papelarias, fotocópia.',
    'tributacao_default' => [
        'csosn'           => '102',
        'cfop'            => '5102',
        'aliquota_icms'   => 0.00,    // Simples — recolhe via DAS
        'aliquota_pis'    => 0.00,
        'aliquota_cofins' => 0.00,
        'aliquota_ipi'    => 0.00,
    ],
    'observacoes' => [
        'SC NÃO tem FCP — diferente de RJ/MG/RS/GO/PA. Pode deixar fcp default em zero sem regra NCM específica.',
        'ICMS interno SC = 17% (versus 18% SP), mas Simples Nacional recolhe unificado via DAS — alíquota não aparece destacada na nota.',
        'Pra venda interestadual SC→outras UFs consumidor final (DIFAL), criar regra NCM específica com alíquota destino.',
        'Substituição Tributária em SC tem lista extensa por NCM — usar regra avulsa NCM quando produto for ST (não aplicar via template).',
        'NCM padrão recomendado: 49111000 (impressos publicitários) pra gráficas rápidas; 48201000 (papelaria) pra papelaria.',
    ],
];
