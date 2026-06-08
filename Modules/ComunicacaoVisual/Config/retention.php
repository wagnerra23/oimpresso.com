<?php

/**
 * Retention policy ComunicacaoVisual — LGPD Art. 16 (eliminação).
 *
 * Define janelas de retenção por entidade, alinhadas a:
 * - Apontamento → 5 anos (registro produtivo legal — segue padrão Repair)
 * - Orcamento → 5 anos (CCom Art. 195 — guarda fiscal de documentos comerciais)
 * - Os → 5 anos (idem orçamento, registro comercial)
 * - ApontamentoEvents/logs → 12 meses (telemetria operacional)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * job de expurgo SEMPRE recebe $businessId no constructor (session() não roda em fila).
 *
 * LGPD Art. 16 II — eliminação ao fim da finalidade. Apontamento, Orcamento e Os
 * são append-only / soft-delete; expurgo é hard-delete após janela legal.
 *
 * Cliente pode requisitar antecipação (LGPD Art. 18 IV) — gerar ticket manual
 * antes de hard-delete antecipado (audit trail em activity_log).
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-LGPD-001
 * @see Modules/ComunicacaoVisual/Tests/Feature/LgpdComplianceTest.php
 */

return [
    /*
     * Janelas de retenção em dias.
     * Job `comvis:purge-expired` (TODO Sprint 3+) roda mensal e:
     *   1. SELECT WHERE created_at < NOW() - INTERVAL <dias> DAY AND business_id = ?
     *   2. Loga em activity_log (audit append-only)
     *   3. DELETE hard (purge)
     */
    'entities' => [
        'apontamento' => [
            'days'           => 1825, // 5 anos
            'basis_legal'    => 'CCom Art. 195 + Portaria MTP 671/2021 §registro produtivo',
            'append_only'    => true, // sem SoftDeletes — registro legal
            'pii_fields'     => ['observacoes'], // pode conter nome operador em texto livre
        ],
        'orcamento' => [
            'days'           => 1825, // 5 anos
            'basis_legal'    => 'CCom Art. 195 — documento comercial',
            'append_only'    => false,
            'pii_fields'     => ['observacoes'], // pode conter dados cliente em texto livre
        ],
        'os' => [
            'days'           => 1825, // 5 anos
            'basis_legal'    => 'CCom Art. 195 — registro de venda/serviço',
            'append_only'    => false,
            'pii_fields'     => ['observacoes'],
        ],
    ],

    /*
     * Telemetria operacional (logs, métricas, breadcrumbs).
     * Sem PII — janela curta otimiza armazenamento.
     */
    'telemetry' => [
        'days' => 365, // 12 meses
        'tables' => [
            'comvis_apontamento_events',
        ],
    ],

    /*
     * Cliente direito de eliminação (LGPD Art. 18 VI).
     * Quando contato (PII) for marcado pra esquecimento:
     *   1. Anonimizar referências em comvis_orcamentos.contato_id (set NULL + comentário)
     *   2. NÃO deletar registro fiscal (CCom obriga retenção até janela)
     *   3. Log em activity_log com causer = sistema + properties.reason = LGPD-18-VI
     */
    'right_to_be_forgotten' => [
        'enabled'             => true,
        'anonymize_fields'    => ['observacoes'], // limpa free-text potencialmente PII
        'preserve_fiscal_ids' => true, // mantém numero/totais pra integridade contábil
    ],
];
