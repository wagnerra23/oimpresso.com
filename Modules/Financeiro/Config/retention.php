<?php

declare(strict_types=1);

/**
 * Retenção LGPD — Modules/Financeiro (Wave 14 D7.c sessão 2026-05-16).
 *
 * Política de retenção por categoria de dado financeiro. Valores em DIAS.
 * Pós-prazo, dados podem ser anonimizados/purgados via job dedicado
 * (futuro: `financeiro:purge-expired`) — este config define os limites.
 *
 * **Bases legais (Brasil):**
 *  - **CTN Art. 195** (Código Tributário Nacional) — prescrição dos créditos
 *    tributários: 5 anos. Títulos/baixas/movimentos de caixa que compõem
 *    apuração tributária seguem essa janela.
 *  - **CC Art. 206 §3 V** (Código Civil) — pretensão de cobrança de
 *    dívidas líquidas constantes de instrumento público/particular
 *    prescreve em 5 anos. Boletos vencidos não pagos podem ser
 *    descartados após 5 anos contados do vencimento.
 *  - **Lei 5.474/68 Art. 18** (Lei das Duplicatas) — duplicatas mercantis
 *    prescrevem em 3 anos. Boletos vinculados a duplicata = 1095 dias
 *    mínimo (defensivo: usamos 730d só pra cópias PDF/CNAB; o registro
 *    em `fin_titulos` segue 1825d pela prescrição tributária maior).
 *  - **LGPD Art. 16** — dados pessoais devem ser eliminados após o
 *    término do tratamento, salvo cumprimento de obrigação legal — as
 *    bases acima JUSTIFICAM retenção mesmo após pedido de exclusão LGPD
 *    do titular (cliente). Resposta canônica ao titular: dado mantido
 *    por obrigação tributária; será eliminado após 5 anos da última
 *    operação.
 *  - **Resolução BCB 4.658/2018** + **Circular BCB 3.978/2020** —
 *    instituições financeiras devem manter 5 anos de trilha audit; aqui
 *    não somos IF, mas a janela alinha (CaixaMovimento 1825d).
 *
 * **Multi-tenant Tier 0** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * jobs de purge respeitam `business_id` global scope — NUNCA cross-tenant
 * cleanup. Cada business pode override via business_settings (futuro).
 *
 * **Append-only contrato (FSM Pipeline ADR 0143 + ADR 0070):**
 *  - `fin_titulos` e `fin_caixa_movimentos` NÃO permitem hard delete via
 *    `Model::delete()` (override em runtime — DomainException). Purge real
 *    fica reservada a job superadmin com `withoutGlobalScopes` documentado.
 *  - `activity_log` (LogsActivity em Titulo/CaixaMovimento/ContaBancaria/
 *    BoletoRemessa/TituloBaixa — D7.b Wave 14) é AUDITORIA — NUNCA purgada
 *    junto com o dado-fonte. Tem janela própria de 10 anos (CC Art. 206).
 *
 * **Status atual (2026-05-16):** declaração canônica + base pra auditoria
 * D7 (Capterra Financeiro 65→72+ Wave 14). Job `financeiro:purge-expired`
 * fica em backlog: nasce só com sinal qualificado (ADR 0105) — titular
 * pedindo exclusão LGPD OU compliance gate detectar drift.
 *
 * Override possível via .env quando job for implementado:
 * `FINANCEIRO_RETENTION_TITULOS_DAYS=1825`.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md
 * @see memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see Modules\Financeiro\Services\FinanceiroAuditLogger (D7.a)
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Quando true, job `financeiro:purge-expired` (futuro) consulta estas
    | configs antes de anonimizar/purgar. Default false até Wagner aprovar
    | em canary (ADR 0105 — sinal qualificado).
    */
    'enabled' => env('FINANCEIRO_RETENTION_ENABLED', false),

    /**
     * Títulos financeiros (`fin_titulos` + `fin_titulo_baixas`).
     * Base: CTN Art. 195 — prescrição tributária 5 anos contados do último
     * fato gerador (baixa, cancelamento ou vencimento).
     *
     * Append-only enforce ainda vale — purge anonimiza `cliente_id` /
     * `cliente_descricao` / `observacoes` (PII), mantém valores agregados
     * pra histórico tributário.
     */
    'titulos' => [
        'days' => env('FINANCEIRO_RETENTION_TITULOS_DAYS', 1825),
        'tables' => ['fin_titulos', 'fin_titulo_baixas'],
        'pii_fields' => ['cliente_descricao', 'observacoes'],
        'preserve_fields' => ['valor_total', 'valor_aberto', 'status', 'business_id'],
        'legal_basis' => 'CTN Art. 195 (5 anos prescrição tributária) + CC Art. 206 §3 V (cobrança líquida 5 anos)',
        'strategy' => 'anonymize',
    ],

    /**
     * Boletos (`fin_boleto_remessas`) — PDFs gerados / payload CNAB / linha
     * digitável. PDF/CNAB têm vida útil curta pós-pagamento.
     *
     * Base: Lei 5.474/68 Art. 18 (duplicatas 3 anos) + buffer operacional.
     * 730d = 2 anos cobre disputa típica + janela contestação cliente.
     *
     * NOTA: registro `fin_titulos` permanece 1825d (prescrição maior);
     * boletos são "cópia operacional" — podem ser regenerados a partir
     * do Titulo se necessário.
     */
    'boletos' => [
        'days' => env('FINANCEIRO_RETENTION_BOLETOS_DAYS', 730),
        'tables' => ['fin_boleto_remessas'],
        'pii_fields' => ['linha_digitavel', 'codigo_barras', 'nosso_numero', 'pdf_path'],
        'preserve_fields' => ['titulo_id', 'business_id', 'status', 'valor_total', 'vencimento'],
        'legal_basis' => 'Lei 5.474/68 Art. 18 (duplicatas 3 anos) — boleto = cópia operacional do titulo',
        'strategy' => 'anonymize',
    ],

    /**
     * Caixa / ledger (`fin_caixa_movimentos`).
     * Base: CTN Art. 195 + Resolução BCB 4.658/2018 (audit trail 5 anos).
     *
     * Append-only IRREVOGÁVEL (`CaixaMovimento::delete()` lança
     * DomainException). Purge aqui é APENAS anonimização da `descricao`
     * e `metadata.note` (PII), preservando agregados financeiros eternos.
     */
    'caixa' => [
        'days' => env('FINANCEIRO_RETENTION_CAIXA_DAYS', 1825),
        'tables' => ['fin_caixa_movimentos'],
        'pii_fields' => ['descricao'],
        'preserve_fields' => ['valor', 'tipo', 'data', 'saldo_apos', 'conta_bancaria_id', 'business_id'],
        'legal_basis' => 'CTN Art. 195 (5 anos prescrição) + Resolução BCB 4.658/2018 (audit trail)',
        'strategy' => 'anonymize',
    ],

    /**
     * Conciliação bancária — extratos importados (`fin_extrato_lancamentos`).
     * Base: CC Art. 206 — escrituração mercantil 10 anos (defensivo); na
     * prática extratos auxiliam apuração mensal — 1825d suficiente.
     */
    'extratos' => [
        'days' => env('FINANCEIRO_RETENTION_EXTRATOS_DAYS', 1825),
        'tables' => ['fin_extrato_lancamentos'],
        'pii_fields' => ['descricao', 'documento'],
        'preserve_fields' => ['valor', 'data', 'conta_bancaria_id', 'business_id'],
        'legal_basis' => 'CTN Art. 195 (5 anos) — extrato é base de conciliação tributária',
        'strategy' => 'anonymize',
    ],

    /**
     * Logs de auditoria financeira (`activity_log` filtrado por
     * `log_name` IN financeiro.titulo, financeiro.boleto_remessa,
     * financeiro.caixa_movimento, financeiro.baixa, financeiro.conta_bancaria).
     *
     * Base: CC Art. 206 — rastreabilidade da escrituração 10 anos.
     * D7.b Wave 14: log já é sanitizado de PII pelo wrap LogsActivity
     * `->logOnly([...])` (não pega `cliente_descricao` nem `observacoes`).
     */
    'logs_audit_financeiro' => [
        'days' => env('FINANCEIRO_RETENTION_AUDIT_DAYS', 3650),
        'tables' => ['activity_log'],
        'log_names' => [
            'financeiro.titulo',
            'financeiro.boleto_remessa',
            'financeiro.caixa_movimento',
            'financeiro.baixa',
            'financeiro.conta_bancaria',
        ],
        'legal_basis' => 'CC Art. 206 (10 anos rastreabilidade escrituração)',
        'strategy' => 'hard_delete',
    ],

    /**
     * Contas bancárias (`fin_contas_bancarias`).
     * Base: CTN Art. 195 — vínculo a operações tributárias 5 anos após
     * desativação. Conta ATIVA nunca purga — purge avalia
     * `ativo_para_boleto=false AND updated_at < cutoff`.
     */
    'contas_bancarias' => [
        'days' => env('FINANCEIRO_RETENTION_CONTAS_DAYS', 1825),
        'tables' => ['fin_contas_bancarias'],
        'pii_fields' => [
            'beneficiario_documento',
            'beneficiario_razao_social',
            'beneficiario_logradouro',
            'beneficiario_cep',
            'certificado_password_encrypted',
        ],
        'preserve_fields' => ['business_id', 'account_id', 'banco_codigo', 'ativo_para_boleto'],
        'legal_basis' => 'CTN Art. 195 + LGPD Art. 16 (após desativação + 5 anos)',
        'strategy' => 'anonymize',
        'condition' => 'ativo_para_boleto = false',
    ],

];
