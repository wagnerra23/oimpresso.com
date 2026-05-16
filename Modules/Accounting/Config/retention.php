<?php

declare(strict_types=1);

/**
 * Retenção LGPD — Modules/Accounting (Wave 11 D7.c sessão 2026-05-16).
 *
 * Política de retenção por categoria de dado contábil. Valores em DIAS.
 * Pós-prazo, dados podem ser anonimizados/purgados via job dedicado
 * (futuro: `accounting:purge-expired`) — este config define os limites.
 *
 * Bases legais (Brasil):
 *  - **CTN Art. 195** (Código Tributário Nacional) — livros + documentos
 *    da escrituração devem ser conservados até prescrição dos créditos
 *    tributários (5 anos).
 *  - **CC Art. 206** (Código Civil) — escrituração contábil e
 *    documentos relativos a atos negociais (incluindo balancetes,
 *    razão geral) — 10 anos (decadência geral).
 *  - **Lei 8.846/94 Art. 23** — emissor obrigado a conservar via 2ª da
 *    nota fiscal pelo prazo decadencial dos tributos (5 anos).
 *  - **LGPD Art. 16** — dados pessoais devem ser eliminados após o
 *    término do tratamento, salvo cumprimento de obrigação legal — as
 *    bases acima JUSTIFICAM retenção mesmo após pedido de exclusão LGPD.
 *
 * Multi-tenant Tier 0 (ADR 0093): purga é per-business_id quando rodada.
 *
 * Override possível via .env (futuro): `ACCOUNTING_RETENTION_LANCAMENTOS_DAYS=...`
 */

return [

    /**
     * Lançamentos contábeis (`journal_entries`, `account_transactions`).
     * Base: CTN Art. 195 — prescrição tributária 5 anos.
     */
    'lancamentos' => [
        'days' => 1825,
        'tables' => ['journal_entries', 'account_transactions'],
        'legal_basis' => 'CTN Art. 195 (5 anos prescrição tributária)',
    ],

    /**
     * Balancetes / razão / livros (sumários derivados de lançamentos).
     * Base: CC Art. 206 — escrituração mercantil 10 anos.
     * Maior que `lancamentos` propositalmente (resumo histórico
     * sobrevive ao detalhe).
     */
    'balancetes' => [
        'days' => 2555,
        'tables' => ['chart_of_accounts', 'account_subtypes', 'budgets'],
        'legal_basis' => 'CC Art. 206 (10 anos escrituração mercantil)',
    ],

    /**
     * Notas fiscais (cópias / XMLs / referências contábeis em
     * `transactions` quando type=sell/purchase com vínculo SEFAZ).
     * Base: Lei 8.846/94 Art. 23 — 5 anos.
     *
     * NOTA: módulo NfeBrasil mantém retenção própria pra XMLs SEFAZ;
     * esta config cobre a perna contábil residual.
     */
    'notas_fiscais' => [
        'days' => 1825,
        'tables' => ['transactions'],
        'legal_basis' => 'Lei 8.846/94 Art. 23 (5 anos decadencial tributário)',
    ],

    /**
     * Logs de auditoria contábil (`activity_log` filtrado por
     * subject_type=Modules\Accounting\Entities\*).
     * Base: CC Art. 206 — exige rastreabilidade da escrituração 10 anos.
     * Log já é sanitizado de PII via AccountingAuditLogger antes de
     * persistir.
     */
    'logs_audit_contabil' => [
        'days' => 2555,
        'tables' => ['activity_log'],
        'legal_basis' => 'CC Art. 206 (10 anos rastreabilidade escrituração)',
    ],

    /**
     * Clientes / fornecedores (subset Accounting — não confundir com
     * `contacts` global UltimatePOS que tem retenção própria CRM).
     * Base: CTN Art. 195 — vínculo a operações tributárias 5 anos.
     */
    'clientes_fornecedores' => [
        'days' => 1825,
        'tables' => ['contacts'],
        'legal_basis' => 'CTN Art. 195 (5 anos vínculo operações tributárias)',
    ],

];
