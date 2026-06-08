<?php

declare(strict_types=1);

/**
 * Template tributário · MEI · Varejo · SP
 *
 * Microempreendedor Individual (MEI) — limite R$ [redacted Tier 0]/ano (R$ [redacted Tier 0] a partir
 * de 2026 com PL 108/2024 aprovado, mas operacional 2025+).
 *
 * Modelo NFe: 65 (NFC-e) ou 55 (NFe) — MEI pode emitir ambos
 * CFOP: 5.102 (venda mercadoria adquirida)
 * Tributação: TODAS as alíquotas zeradas — MEI recolhe DAS-MEI fixo mensal
 *   (R$ [redacted Tier 0]-77 dependendo do tipo de atividade)
 * CSOSN: 102 (Simples sem permissão de crédito)
 *
 * **Atenção:**
 * - MEI emite NFe **opcionalmente** pra B2C (CPF) e **obrigatoriamente** pra B2B (CNPJ)
 * - Valor mínimo emissão: R$ [redacted Tier 0] (sem mínimo legal)
 * - DASN-SIMEI declaração anual obrigatória
 *
 * Fonte: Resolução CGSN 140/2018 + Lei Complementar 123/2006 art. 18-A.
 */
return [
    'slug'       => 'mei-varejo-sp',
    'titulo'     => 'MEI · Varejo · SP',
    'descricao'  => 'Microempreendedor Individual. Tudo zero — MEI recolhe via DAS-MEI fixo mensal.',
    'icon'       => 'shopping-bag',
    'setor'      => 'comercio',
    'regime'     => 'mei',
    'uf'         => 'SP',
    'modelo_nfe' => '65',
    'recomendado_para' => 'MEI (faturamento até R$ [redacted Tier 0]k/ano em 2025; R$ [redacted Tier 0]k em 2026 com nova lei).',
    'tributacao_default' => [
        'csosn'           => '102',
        'cfop'            => '5102',
        'aliquota_icms'   => 0.00,
        'aliquota_pis'    => 0.00,
        'aliquota_cofins' => 0.00,
        'aliquota_ipi'    => 0.00,
    ],
    'observacoes' => [
        'MEI recolhe via DAS-MEI mensal fixo (R$ [redacted Tier 0]-77 conforme atividade) — NFe sai com tudo zerado.',
        'Emissão NFe é OPCIONAL pra B2C (CPF) mas OBRIGATÓRIA pra B2B (CNPJ).',
        'CSOSN 102 = Simples sem permissão de crédito (igual ao varejo Simples).',
        'Quando ultrapassar R$ [redacted Tier 0]k/ano (limite 2025), DESEnquadrar pra Simples Nacional sem desativar empresa.',
        'DASN-SIMEI declaração anual obrigatória até maio.',
    ],
];
