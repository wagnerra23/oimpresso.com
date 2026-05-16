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

];
