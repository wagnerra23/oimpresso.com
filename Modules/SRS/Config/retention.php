<?php

/*
|--------------------------------------------------------------------------
| SRS Retention Policy — D7 LGPD compliance
|--------------------------------------------------------------------------
|
| Define janelas de retenção pra dados gerados pelo módulo SRS (Software
| Requirements System — ferramenta interna Wagner). Valores em DIAS.
|
| Princípio (LGPD Art. 16, ADR 0093/0094 §4):
|   - Retenção MÍNIMA pra cumprir finalidade declarada
|   - Audit trail (governance) com janela longa (5 anos = 1825d)
|   - Conteúdo derivado/draft com janela curta (90d)
|   - Logs operacionais janela média (365d)
|
| SRS é módulo backend doc-generation sem PII grave de cliente final.
| Mesmo assim, mensagens de chat e fontes ingeridas podem conter referência
| a nomes próprios / URLs / paths internos — aplicamos retention conservador.
|
| Limpeza efetiva via comando artisan (futuro: srs:retention-cleanup, schedule
| mensal). Por ora, este arquivo é fonte declarativa pra auditoria LGPD.
|
| @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
| @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §4
| @see Modules/Arquivos/Config/config.php (pattern retention_days_default)
*/

return [

    /**
     * Documentos gerados (relatórios SRS, validation reports finalizados).
     *
     * 1825 dias = 5 anos. Governance audit trail — preserva histórico de
     * decisões de requisitos pra rastrear "por que decidimos X em 2026".
     */
    'generated_docs_days' => (int) env('SRS_RETENTION_GENERATED_DOCS_DAYS', 1825),

    /**
     * Versões rascunho / drafts intermediários de evidências/requisitos.
     *
     * 90 dias. Janela curta — drafts que viraram canon vão pra generated_docs;
     * drafts abandonados são purgados.
     */
    'draft_versions_days' => (int) env('SRS_RETENTION_DRAFT_VERSIONS_DAYS', 90),

    /**
     * Logs de geração / ingest / validation runs (docs_validation_runs).
     *
     * 365 dias = 1 ano. Janela média — útil pra debug operacional + auditoria
     * "quando ingeriu/quando rodou validação", sem inflar tabela indefinidamente.
     * Append-only por design (ADR 0093) — só purga registros antigos.
     */
    'generation_logs_days' => (int) env('SRS_RETENTION_GENERATION_LOGS_DAYS', 365),

    /**
     * Mensagens de chat SRS (docs_chat_messages).
     *
     * 365 dias. Conteúdo já redacted via PiiRedactor antes de persistir
     * (defense-in-depth), mas mesmo assim janela limitada por princípio LGPD
     * minimização.
     */
    'chat_messages_days' => (int) env('SRS_RETENTION_CHAT_MESSAGES_DAYS', 365),

    // ============================================================================
    // Wave 27 D7 LGPD push final — base legal + notice_period explicito por categoria
    // ============================================================================

    /**
     * Base legal LGPD aplicável (Art. 7º — bases de tratamento).
     *
     * SRS é ferramenta interna Wagner com baixíssima exposição a PII de cliente
     * (mensagens de chat operacional + evidências de bugs/features). Base
     * primária é Art. 7º II (cumprimento de obrigação legal — governance audit
     * trail) + Art. 7º IX (legítimo interesse — operação interna oimpresso).
     */
    'base_legal' => [
        'art' => 'LGPD Art. 7º II (cumprimento obrigação legal) + IX (legítimo interesse interno)',
        'finalidade' => 'Governance audit trail SRS ferramenta interna — rastreabilidade decisões requisitos',
        'titular_pertence_a' => 'Wagner/admin oimpresso (não cliente externo)',
    ],

    /**
     * Notice period — Wave 27.
     *
     * LGPD Art. 9º exige informar titular sobre alteração de retenção. Para
     * dados de PII grave, exige notificação prévia (30-90d). SRS opera sem PII
     * grave (ver `base_legal.titular_pertence_a` acima) — notice 0d (nao se
     * aplica notificacao prévia) MAS Wagner deve documentar mudancas em ADR
     * antes de alterar retention janela (governance interno).
     */
    'notice_period_days' => (int) env('SRS_RETENTION_NOTICE_PERIOD_DAYS', 0),

    /**
     * Hierarquia LGPD declarativa Wave 27 — referência rapida pra audit.
     *
     * Ordem crescente de retencao:
     *   drafts (90d)  < chat (365d) = logs (365d)  < generated_docs (1825d / 5 anos)
     *
     * Justificativa:
     *   - drafts: minimização agressiva (abandonados não tem valor)
     *   - chat/logs: janela média operacional (debug + audit)
     *   - generated_docs: 5 anos = obrigação fiscal Brasil (Receita Federal +
     *     CLT Art. 11 prescrição trabalhista) — quando SRS gera doc de US/spec,
     *     ela vira artefato fiscal-relevante (auditoria MTE pode pedir).
     */
    'hierarquia' => [
        'drafts'         => 90,
        'chat_messages'  => 365,
        'logs_validation' => 365,
        'generated_docs' => 1825,
    ],

    /**
     * Strategy aplicacao limpeza — Wave 27.
     *
     * 'soft' = marca como purged (mantem row) — pra rollback acidental
     * 'hard' = DELETE definitivo (LGPD minimização padrão)
     *
     * SRS usa 'hard' por design — generated_docs governance ja preservado em
     * git canônico (memory/requisitos/...) que e fonte de verdade. Tabela
     * docs_* sao cache operacional, não fonte primaria.
     */
    'strategy' => env('SRS_RETENTION_STRATEGY', 'hard'),

    /**
     * Entities cobertas pela limpeza automatica (`srs:retention-cleanup`).
     *
     * Mapeamento explicito Entity → janela. Pest valida que toda Entity
     * tenant-scoped esta listada (defesa contra esquecer nova entity).
     */
    'entities' => [
        'DocChatMessage'   => 365,
        'DocValidationRun' => 365,
        // Wave 27 — entities adicionadas conforme nova evidence/requirement workflow
        // 'DocEvidence' => 1825,  // doc fiscal-relevante, manter 5 anos
        // 'DocRequirement' => 1825, // governance hist, manter 5 anos
    ],

];
