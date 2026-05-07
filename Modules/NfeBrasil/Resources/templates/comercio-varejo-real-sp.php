<?php

declare(strict_types=1);

/**
 * Template tributário · Comércio varejo · Lucro Real · SP
 *
 * Cliente típico: varejo de grande porte (>R$ 78M/ano) ou que optou Lucro Real
 * por benefício fiscal. Regime NÃO-CUMULATIVO (PIS/COFINS com direito a crédito
 * sobre insumos e mercadorias).
 *
 * Modelo NFe: 65 (NFC-e) ou 55 (NFe)
 * CFOP: 5.102
 * Tributação ICMS: CST 00 (tributada integralmente, 18% SP)
 * PIS: 1.65% NÃO-CUMULATIVO (com crédito sobre entradas)
 * COFINS: 7.6% NÃO-CUMULATIVO (com crédito)
 *
 * **Atenção crítica:** Lucro Real exige escrituração contábil completa,
 * cálculo trimestral/anual de IRPJ+CSLL real, EFD-Contribuições mensal.
 * Este template cobre só a NFe; contabilidade fiscal full está fora do escopo.
 *
 * Fonte: Lei 10.637/2002 (PIS) + Lei 10.833/2003 (COFINS) + RICMS-SP.
 */
return [
    'slug'       => 'comercio-varejo-real-sp',
    'titulo'     => 'Comércio varejo · Lucro Real · SP',
    'descricao'  => 'Varejo de grande porte. ICMS 18% + PIS 1,65% + COFINS 7,6% (não-cumulativo, com crédito).',
    'icon'       => 'shopping-bag',
    'setor'      => 'comercio',
    'regime'     => 'lucro_real',
    'uf'         => 'SP',
    'modelo_nfe' => '65',
    'recomendado_para' => 'Varejo > R$ 78M/ano ou que optou Lucro Real por benefício fiscal.',
    'tributacao_default' => [
        'cst'             => '00',
        'cfop'            => '5102',
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0165,  // 1,65% NÃO-cumulativo
        'aliquota_cofins' => 0.076,   // 7,6% NÃO-cumulativo
        'aliquota_ipi'    => 0.00,
    ],
    'observacoes' => [
        'PIS/COFINS NÃO-CUMULATIVOS — alíquotas maiores mas com direito a crédito sobre entradas (Leis 10.637 e 10.833).',
        'Aproveitamento de crédito exige EFD-Contribuições mensal corretamente preenchida.',
        'Pra exportação (CFOP 7.x) PIS/COFINS são zero — configurar regra específica.',
        'Cálculo IRPJ/CSLL feito por escrituração contábil mensal — fora do escopo da NFe.',
        'Pra produtos com alíquotas zero/reduzidas (Lei 10.865/2004) configurar NCM específico.',
    ],
];
